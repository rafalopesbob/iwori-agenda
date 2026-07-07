<?php

namespace Tests\Feature;

use App\Jobs\RemoveSessionFromGoogleCalendar;
use App\Jobs\SyncSessionToGoogleCalendar;
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
