<?php

namespace App\Services;

use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class GoogleCalendarService
{
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    protected const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    /**
     * Único escopo pedido: ver e editar eventos. Não lê nem apaga agendas.
     */
    public const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    /**
     * Credenciais do Google configuradas no ambiente?
     */
    public function isConfigured(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret');
    }

    /**
     * Fuso em que os horários das sessões são digitados e gravados.
     *
     * O app roda em UTC, mas o scheduled_at guarda o horário de parede do
     * consultório (14:00 digitado = 14:00 gravado). O Google precisa saber
     * desse fuso para não deslocar o evento.
     */
    public function timezone(): string
    {
        return config('services.google.timezone') ?: 'America/Sao_Paulo';
    }

    /**
     * URL de consentimento OAuth. O state protege o callback contra CSRF.
     */
    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('google.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * Troca o código de autorização pelos tokens e os guarda no profissional.
     *
     * Na tela de consentimento o Google deixa a pessoa desmarcar a permissão
     * da agenda. Sem ela nenhum evento seria criado, então a conexão é
     * recusada (retorna false) e o token recebido é revogado.
     */
    public function connect(User $user, string $code): bool
    {
        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('google.callback'),
        ])->throw()->json();

        // O Google devolve em "scope" o que foi de fato concedido.
        if (isset($tokens['scope']) && ! in_array(self::SCOPE, explode(' ', $tokens['scope']), true)) {
            Http::asForm()->post(self::REVOKE_URL, ['token' => $tokens['access_token']]);

            return false;
        }

        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_refresh_token' => $tokens['refresh_token'] ?? $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
        ])->save();

        return true;
    }

    /**
     * Revoga o acesso no Google (melhor esforço) e limpa os tokens.
     */
    public function disconnect(User $user): void
    {
        if ($user->google_refresh_token) {
            Http::asForm()->post(self::REVOKE_URL, ['token' => $user->google_refresh_token]);
        }

        $user->forceFill([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ])->save();
    }

    /**
     * Cria o evento da sessão no calendário do profissional.
     */
    public function createEvent(ClientSession $session): void
    {
        $user = $session->client->user;

        if (! $this->isConfigured() || ! $user->hasGoogleCalendar()) {
            return;
        }

        $event = Http::withToken($this->accessToken($user))
            ->post(self::EVENTS_URL, $this->eventPayload($session))
            ->throw()
            ->json();

        $session->forceFill(['google_event_id' => $event['id']])->save();
    }

    /**
     * Atualiza o evento existente da sessão (reagendamento).
     * Se o evento sumiu no Google, recria.
     */
    public function updateEvent(ClientSession $session): void
    {
        $user = $session->client->user;

        if (! $session->google_event_id || ! $this->isConfigured() || ! $user->hasGoogleCalendar()) {
            return;
        }

        $response = Http::withToken($this->accessToken($user))
            ->patch(self::EVENTS_URL.'/'.$session->google_event_id, $this->eventPayload($session));

        if (in_array($response->status(), [404, 410])) {
            $session->forceFill(['google_event_id' => null])->save();
            $this->createEvent($session);

            return;
        }

        $response->throw();
    }

    /**
     * Remove o evento da sessão do calendário (ex.: cancelamento).
     */
    public function deleteEvent(ClientSession $session): void
    {
        $user = $session->client->user;

        if (! $session->google_event_id || ! $this->isConfigured() || ! $user->hasGoogleCalendar()) {
            return;
        }

        $response = Http::withToken($this->accessToken($user))
            ->delete(self::EVENTS_URL.'/'.$session->google_event_id);

        // 404/410 significam que o evento já não existe no Google — ok limpar.
        if ($response->successful() || in_array($response->status(), [404, 410])) {
            $session->forceFill(['google_event_id' => null])->save();

            return;
        }

        $response->throw();
    }

    /**
     * Token de acesso válido, renovando pelo refresh token quando expirado.
     */
    protected function accessToken(User $user): string
    {
        if ($user->google_token_expires_at?->isFuture() && $user->google_access_token) {
            return $user->google_access_token;
        }

        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token',
        ])->throw()->json();

        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
        ])->save();

        return $tokens['access_token'];
    }

    /**
     * Monta o corpo do evento a partir da sessão.
     *
     * @return array<string, mixed>
     */
    protected function eventPayload(ClientSession $session): array
    {
        // Horário de parede, sem offset, acompanhado do fuso do consultório.
        // Mandar o offset do app (UTC) deslocaria o evento em 3 horas.
        $format = 'Y-m-d\TH:i:s';

        return [
            'summary' => 'Sessão — '.$session->client->name,
            // Sem anotações nem valores: o evento fica numa conta de terceiros.
            'description' => "Agendado pelo Iwori Agenda.\nAbrir a sessão: ".route('sessions.edit', $session),
            'start' => [
                'dateTime' => $session->scheduled_at->format($format),
                'timeZone' => $this->timezone(),
            ],
            'end' => [
                'dateTime' => $session->scheduled_at->addMinutes($session->duration_minutes)->format($format),
                'timeZone' => $this->timezone(),
            ],
        ];
    }
}
