<?php

namespace Tests\Feature;

use App\Jobs\RemoveSessionFromGoogleCalendar;
use App\Jobs\SyncSessionToGoogleCalendar;
use App\Jobs\SyncUpcomingSessionsToGoogleCalendar;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function withGoogleConfigured(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
    }

    protected function connectGoogle(User $user, bool $expired = false): void
    {
        $user->forceFill([
            'google_access_token' => 'token-de-acesso',
            'google_refresh_token' => 'token-de-refresh',
            'google_token_expires_at' => $expired ? now()->subMinute() : now()->addHour(),
        ])->save();
    }

    public function test_sem_credenciais_a_conexao_fica_indisponivel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/google/connect')->assertNotFound();
        $this->actingAs($user)->get('/dashboard')->assertDontSee('Google Calendar');
    }

    public function test_conectar_redireciona_para_o_google_com_state(): void
    {
        $this->withGoogleConfigured();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/google/connect');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $response->headers->get('Location'));
        $this->assertStringContainsString('test-client-id', $response->headers->get('Location'));
        $response->assertSessionHas('google_oauth_state');
    }

    public function test_callback_troca_o_codigo_e_guarda_tokens_criptografados(): void
    {
        $this->withGoogleConfigured();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'novo-access-token',
                'refresh_token' => 'novo-refresh-token',
                'expires_in' => 3600,
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['google_oauth_state' => 'state-valido'])
            ->get('/google/callback?code=codigo-abc&state=state-valido');

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasGoogleCalendar());

        // No banco os tokens não ficam em texto puro.
        $raw = DB::table('users')->where('id', $user->id)->value('google_refresh_token');
        $this->assertNotSame('novo-refresh-token', $raw);
    }

    public function test_callback_com_state_invalido_e_bloqueado(): void
    {
        $this->withGoogleConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['google_oauth_state' => 'state-original'])
            ->get('/google/callback?code=codigo-abc&state=state-forjado')
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasGoogleCalendar());
    }

    public function test_agendamento_com_calendario_conectado_dispara_sincronizacao(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $client = Client::factory()->for($user)->create(['email' => null]);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        Queue::assertPushed(SyncSessionToGoogleCalendar::class);
    }

    public function test_agendamento_sem_conexao_nao_dispara_sincronizacao(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['email' => null]);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        Queue::assertNotPushed(SyncSessionToGoogleCalendar::class);
    }

    public function test_cancelamento_remove_o_evento_do_calendario(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->create();
        $session->forceFill(['google_event_id' => 'evt-123'])->save();

        $this->actingAs($user)->patch("/sessions/{$session->id}/status", ['status' => 'canceled']);

        Queue::assertPushed(RemoveSessionFromGoogleCalendar::class);
    }

    public function test_servico_cria_o_evento_e_guarda_o_id(): void
    {
        $this->withGoogleConfigured();
        Http::fake([
            'www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'evt-criado-123']),
        ]);

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $client = Client::factory()->for($user)->create(['name' => 'Maria Silva']);
        $session = ClientSession::factory()->for($client)->create([
            'scheduled_at' => '2026-07-20 14:00:00',
            'duration_minutes' => 50,
        ]);

        app(GoogleCalendarService::class)->createEvent($session);

        $this->assertSame('evt-criado-123', $session->fresh()->google_event_id);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'calendar/v3/calendars/primary/events')
                && $request['summary'] === 'Sessão — Maria Silva'
                && str_contains($request['start']['dateTime'], '2026-07-20T14:00');
        });
    }

    public function test_servico_renova_token_expirado_antes_de_criar_o_evento(): void
    {
        $this->withGoogleConfigured();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-renovado',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'evt-999']),
        ]);

        $user = User::factory()->create();
        $this->connectGoogle($user, expired: true);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();

        app(GoogleCalendarService::class)->createEvent($session);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token'));
        $this->assertSame('evt-999', $session->fresh()->google_event_id);
        $this->assertSame('token-renovado', $user->fresh()->google_access_token);
    }

    public function test_callback_dispara_o_envio_das_sessoes_ja_marcadas(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'novo-access-token',
                'refresh_token' => 'novo-refresh-token',
                'expires_in' => 3600,
                'scope' => GoogleCalendarService::SCOPE,
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['google_oauth_state' => 'state-valido'])
            ->get('/google/callback?code=codigo-abc&state=state-valido')
            ->assertRedirect(route('dashboard'));

        Queue::assertPushed(SyncUpcomingSessionsToGoogleCalendar::class, fn ($job) => $job->user->is($user));
    }

    public function test_callback_sem_a_permissao_da_agenda_nao_conecta(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-sem-agenda',
                'refresh_token' => 'refresh-sem-agenda',
                'expires_in' => 3600,
                'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
            ]),
            'oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['google_oauth_state' => 'state-valido'])
            ->get('/google/callback?code=codigo-abc&state=state-valido')
            ->assertRedirect(route('help.google-calendar'))
            ->assertSessionHas('status');

        $this->assertFalse($user->fresh()->hasGoogleCalendar());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/revoke'));
        Queue::assertNotPushed(SyncUpcomingSessionsToGoogleCalendar::class);
    }

    public function test_envio_inicial_inclui_so_as_sessoes_agendadas_de_hoje_em_diante(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $client = Client::factory()->for($user)->create();
        $today = now('America/Sao_Paulo')->startOfDay();

        $futura = ClientSession::factory()->for($client)->create(['scheduled_at' => $today->copy()->addDays(3)->setTime(14, 0)]);
        $hojeCedo = ClientSession::factory()->for($client)->create(['scheduled_at' => $today->copy()->setTime(0, 30)]);
        ClientSession::factory()->for($client)->create(['scheduled_at' => $today->copy()->subDay()->setTime(14, 0)]);
        ClientSession::factory()->for($client)->canceled()->create(['scheduled_at' => $today->copy()->addDays(2)]);
        ClientSession::factory()->for(Client::factory())->create(['scheduled_at' => $today->copy()->addDays(2)]);

        (new SyncUpcomingSessionsToGoogleCalendar($user))->handle(app(GoogleCalendarService::class));

        Queue::assertPushed(SyncSessionToGoogleCalendar::class, 2);
        Queue::assertPushed(SyncSessionToGoogleCalendar::class, fn ($job) => $job->session->is($futura));
        Queue::assertPushed(SyncSessionToGoogleCalendar::class, fn ($job) => $job->session->is($hojeCedo));
    }

    public function test_envio_inicial_nao_faz_nada_sem_conexao(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        ClientSession::factory()->for(Client::factory()->for($user))->create(['scheduled_at' => now()->addDays(2)]);

        (new SyncUpcomingSessionsToGoogleCalendar($user))->handle(app(GoogleCalendarService::class));

        Queue::assertNotPushed(SyncSessionToGoogleCalendar::class);
    }

    public function test_desfazer_cancelamento_recria_o_evento(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->canceled()->create();

        $this->actingAs($user)->patch("/sessions/{$session->id}/status", ['status' => 'scheduled']);

        Queue::assertPushed(SyncSessionToGoogleCalendar::class, fn ($job) => $job->session->is($session));
        Queue::assertNotPushed(RemoveSessionFromGoogleCalendar::class);
    }

    public function test_marcar_presenca_nao_mexe_no_google(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();
        $session->forceFill(['google_event_id' => 'evt-123'])->save();

        $this->actingAs($user)->patch("/sessions/{$session->id}/status", ['status' => 'completed']);

        Queue::assertNotPushed(SyncSessionToGoogleCalendar::class);
        Queue::assertNotPushed(RemoveSessionFromGoogleCalendar::class);
    }

    public function test_job_nao_cria_evento_de_sessao_cancelada_enquanto_esperava_na_fila(): void
    {
        $this->withGoogleConfigured();
        Http::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->canceled()->create();

        (new SyncSessionToGoogleCalendar($session))->handle(app(GoogleCalendarService::class));

        Http::assertNothingSent();
        $this->assertNull($session->fresh()->google_event_id);
    }

    public function test_evento_usa_o_horario_digitado_no_fuso_do_consultorio(): void
    {
        $this->withGoogleConfigured();
        Http::fake([
            'www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'evt-fuso']),
        ]);

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create([
            'scheduled_at' => '2026-07-20 14:00:00',
            'duration_minutes' => 50,
        ]);

        app(GoogleCalendarService::class)->createEvent($session);

        // 14:00 digitado tem de chegar como 14:00 de Brasília, e não 14:00 UTC (11:00 em Brasília).
        Http::assertSent(function ($request) use ($session) {
            return $request['start'] === ['dateTime' => '2026-07-20T14:00:00', 'timeZone' => 'America/Sao_Paulo']
                && $request['end'] === ['dateTime' => '2026-07-20T14:50:00', 'timeZone' => 'America/Sao_Paulo']
                && str_contains($request['description'], route('sessions.edit', $session));
        });
    }

    public function test_pagina_de_ajuda_mostra_o_passo_a_passo(): void
    {
        $this->withGoogleConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/ajuda/google-agenda')
            ->assertOk()
            ->assertSee('Conectar o Google Agenda')
            ->assertSee(route('google.connect'), false)
            ->assertSee('O Google não verificou este app');

        config(['services.google.verified' => true]);

        $this->actingAs($user)->get('/ajuda/google-agenda')
            ->assertOk()
            ->assertDontSee('O Google não verificou este app');
    }

    public function test_pagina_de_ajuda_avisa_quando_a_integracao_esta_desligada(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/ajuda/google-agenda')
            ->assertOk()
            ->assertSee('A integração ainda não foi ativada')
            ->assertDontSee(route('google.connect'), false);
    }

    public function test_desconectar_revoga_e_limpa_os_tokens(): void
    {
        $this->withGoogleConfigured();
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([], 200)]);

        $user = User::factory()->create();
        $this->connectGoogle($user);

        $this->actingAs($user)->post('/google/disconnect')->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->hasGoogleCalendar());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/revoke'));
    }
}
