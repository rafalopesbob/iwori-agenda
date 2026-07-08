<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Jobs\RemoveSessionFromGoogleCalendar;
use App\Jobs\SyncSessionToGoogleCalendar;
use App\Mail\SessionScheduledMail;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SessionRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function connectGoogle(User $user): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $user->forceFill([
            'google_access_token' => 'token-de-acesso',
            'google_refresh_token' => 'token-de-refresh',
            'google_token_expires_at' => now()->addHour(),
        ])->save();
    }

    public function test_sessao_sem_recorrencia_nao_gera_grupo(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
        ]);

        $session = ClientSession::first();
        $this->assertNull($session->recurrence_group_id);
        $this->assertFalse($session->isRecurring());
        $this->assertSame(1, ClientSession::count());
    }

    public function test_recorrencia_semanal_gera_as_ocorrencias_espacadas(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['email' => 'cliente@teste.com']);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
            'recurrence' => 'weekly',
            'recurrence_count' => 4,
        ])->assertRedirect();

        $sessions = ClientSession::orderBy('scheduled_at')->get();

        $this->assertCount(4, $sessions);
        $this->assertSame(
            ['2026-07-07', '2026-07-14', '2026-07-21', '2026-07-28'],
            $sessions->map(fn (ClientSession $s) => $s->scheduled_at->format('Y-m-d'))->all(),
        );

        $groupIds = $sessions->pluck('recurrence_group_id')->unique();
        $this->assertCount(1, $groupIds);
        $this->assertNotNull($groupIds->first());
        $this->assertTrue($sessions->every(fn (ClientSession $s) => $s->isRecurring()));
    }

    public function test_apenas_a_primeira_ocorrencia_envia_confirmacao_por_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['email' => 'cliente@teste.com']);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
            'recurrence' => 'biweekly',
            'recurrence_count' => 3,
        ]);

        Mail::assertQueued(SessionScheduledMail::class, 1);
    }

    public function test_todas_as_ocorrencias_sincronizam_com_o_google(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->connectGoogle($user);
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
            'recurrence' => 'weekly',
            'recurrence_count' => 5,
        ]);

        Queue::assertPushed(SyncSessionToGoogleCalendar::class, 5);
    }

    public function test_recorrencia_personalizada_gera_ocorrencias_no_intervalo_informado(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-01T10:00',
            'duration_minutes' => 50,
            'recurrence' => 'custom',
            'recurrence_count' => 3,
            'recurrence_custom_days' => 10,
        ])->assertRedirect();

        $sessions = ClientSession::orderBy('scheduled_at')->get();

        $this->assertCount(3, $sessions);
        $this->assertSame(
            ['2026-07-01', '2026-07-11', '2026-07-21'],
            $sessions->map(fn (ClientSession $s) => $s->scheduled_at->format('Y-m-d'))->all(),
        );
    }

    public function test_recorrencia_personalizada_exige_o_intervalo_em_dias(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-01T10:00',
            'duration_minutes' => 50,
            'recurrence' => 'custom',
            'recurrence_count' => 3,
        ])->assertSessionHasErrors('recurrence_custom_days');
    }

    public function test_recorrencia_exige_quantidade_de_repeticoes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
            'recurrence' => 'weekly',
        ])->assertSessionHasErrors('recurrence_count');
    }

    public function test_quantidade_de_repeticoes_tem_limite_maximo(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
            'recurrence' => 'weekly',
            'recurrence_count' => 53,
        ])->assertSessionHasErrors('recurrence_count');
    }

    public function test_cancela_apenas_as_ocorrencias_futuras_agendadas(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-07T14:00',
            'duration_minutes' => 50,
            'recurrence' => 'weekly',
            'recurrence_count' => 4,
        ]);

        $sessions = ClientSession::orderBy('scheduled_at')->get();
        // A primeira já aconteceu (realizada) e não deve ser tocada.
        $sessions[0]->update(['status' => SessionStatus::Completed]);
        $sessions->each(fn (ClientSession $s) => $s->forceFill(['google_event_id' => 'evt-'.$s->id])->save());

        $response = $this->actingAs($user)->post("/sessions/{$sessions[1]->id}/cancel-recurrence");

        $response->assertRedirect();

        $this->assertSame(SessionStatus::Completed, $sessions[0]->fresh()->status);
        $this->assertSame(SessionStatus::Canceled, $sessions[1]->fresh()->status);
        $this->assertSame(SessionStatus::Canceled, $sessions[2]->fresh()->status);
        $this->assertSame(SessionStatus::Canceled, $sessions[3]->fresh()->status);

        Queue::assertPushed(RemoveSessionFromGoogleCalendar::class, 3);
    }

    public function test_cancelar_recorrencia_de_sessao_avulsa_nao_altera_nada(): void
    {
        $user = User::factory()->create();
        $session = ClientSession::factory()->for(Client::factory()->for($user))->create();

        $this->actingAs($user)->post("/sessions/{$session->id}/cancel-recurrence")
            ->assertRedirect();

        $this->assertSame(SessionStatus::Scheduled, $session->fresh()->status);
    }

    public function test_nao_e_possivel_cancelar_recorrencia_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $session = ClientSession::factory()->create();
        $session->forceFill(['recurrence_group_id' => (string) \Illuminate\Support\Str::ulid()])->save();

        $this->actingAs($intruso)->post("/sessions/{$session->id}/cancel-recurrence")
            ->assertForbidden();

        $this->assertSame(SessionStatus::Scheduled, $session->fresh()->status);
    }
}
