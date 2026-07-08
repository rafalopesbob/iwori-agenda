<?php

namespace Tests\Feature;

use App\Jobs\SyncSessionToGoogleCalendar;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SessionRescheduleTest extends TestCase
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

    protected function connectGoogle(User $user): void
    {
        $user->forceFill([
            'google_access_token' => 'token-de-acesso',
            'google_refresh_token' => 'token-de-refresh',
            'google_token_expires_at' => now()->addHour(),
        ])->save();
    }

    public function test_tela_de_reagendamento_renderiza(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Cliente Reagendado']);
        $session = ClientSession::factory()->for($client)->create();

        $this->actingAs($user)->get("/sessions/{$session->id}/edit")
            ->assertOk()
            ->assertSee('Cliente Reagendado');
    }

    public function test_reagendar_altera_data_e_duracao(): void
    {
        $user = User::factory()->create();
        $session = ClientSession::factory()
            ->for(Client::factory()->for($user))
            ->create(['scheduled_at' => '2026-07-10 14:30:00', 'duration_minutes' => 50]);

        $response = $this->actingAs($user)->put("/sessions/{$session->id}", [
            'scheduled_at' => '2026-07-22T16:00',
            'duration_minutes' => 80,
            'value' => $session->value,
        ]);

        $response->assertRedirect(route('sessions.index', ['month' => '2026-07']));

        $session = $session->fresh();
        $this->assertSame('2026-07-22 16:00:00', $session->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(80, $session->duration_minutes);
    }

    public function test_reagendamento_redispara_a_sincronizacao_google(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();
        $session->forceFill(['google_event_id' => 'evt-123'])->save();

        $this->actingAs($user)->put("/sessions/{$session->id}", [
            'scheduled_at' => '2026-07-22T16:00',
            'duration_minutes' => 50,
        ]);

        Queue::assertPushed(SyncSessionToGoogleCalendar::class);
    }

    public function test_nao_e_possivel_reagendar_sessao_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $session = ClientSession::factory()->create(['scheduled_at' => '2026-07-10 14:30:00']);

        $this->actingAs($intruso)->get("/sessions/{$session->id}/edit")->assertForbidden();

        $this->actingAs($intruso)->put("/sessions/{$session->id}", [
            'scheduled_at' => '2026-07-22T16:00',
            'duration_minutes' => 50,
        ])->assertForbidden();

        $this->assertSame('2026-07-10 14:30:00', $session->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_mover_troca_o_dia_e_mantem_o_horario(): void
    {
        $user = User::factory()->create();
        $session = ClientSession::factory()
            ->for(Client::factory()->for($user))
            ->create(['scheduled_at' => '2026-07-10 14:30:00']);

        $response = $this->actingAs($user)->patchJson("/sessions/{$session->id}/move", [
            'date' => '2026-07-20',
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame('2026-07-20 14:30:00', $session->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_mover_com_data_invalida_e_rejeitado(): void
    {
        $user = User::factory()->create();
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();

        $this->actingAs($user)->patchJson("/sessions/{$session->id}/move", [
            'date' => 'nao-e-data',
        ])->assertStatus(422);
    }

    public function test_mover_redispara_a_sincronizacao_google(): void
    {
        $this->withGoogleConfigured();
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();
        $session->forceFill(['google_event_id' => 'evt-123'])->save();

        $this->actingAs($user)->patchJson("/sessions/{$session->id}/move", ['date' => '2026-07-20']);

        Queue::assertPushed(SyncSessionToGoogleCalendar::class);
    }

    public function test_nao_e_possivel_mover_sessao_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $session = ClientSession::factory()->create(['scheduled_at' => '2026-07-10 14:30:00']);

        $this->actingAs($intruso)->patchJson("/sessions/{$session->id}/move", [
            'date' => '2026-07-20',
        ])->assertForbidden();

        $this->assertSame('2026-07-10', $session->fresh()->scheduled_at->format('Y-m-d'));
    }

    public function test_upsert_atualiza_o_evento_existente_sem_duplicar(): void
    {
        $this->withGoogleConfigured();
        Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'evt-123'])]);

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();
        $session->forceFill(['google_event_id' => 'evt-123'])->save();

        (new SyncSessionToGoogleCalendar($session->fresh()))->handle(app(GoogleCalendarService::class));

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), 'events/evt-123');
        });
    }

    public function test_upsert_recria_o_evento_quando_sumiu_no_google(): void
    {
        $this->withGoogleConfigured();
        Http::fake(function ($request) {
            if ($request->method() === 'PATCH') {
                return Http::response([], 404); // evento apagado no Google
            }

            return Http::response(['id' => 'evt-novo']);
        });

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();
        $session->forceFill(['google_event_id' => 'evt-sumido'])->save();

        (new SyncSessionToGoogleCalendar($session->fresh()))->handle(app(GoogleCalendarService::class));

        $this->assertSame('evt-novo', $session->fresh()->google_event_id);
    }
}
