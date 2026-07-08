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
     * Credenciais do Google configuradas no ambiente?
     */
    public function isConfigured(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret');
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
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * Troca o código de autorização pelos tokens e os guarda no profissional.
     */
    public function connect(User $user, string $code): void
    {
        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('google.callback'),
        ])->throw()->json();

        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_refresh_token' => $tokens['refresh_token'] ?? $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
        ])->save();
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
        return [
            'summary' => 'Sessão — '.$session->client->name,
            'description' => 'Agendado pelo Iwori Agenda.',
            'start' => [
                'dateTime' => $session->scheduled_at->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $session->scheduled_at->addMinutes($session->duration_minutes)->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
        ];
    }
}
