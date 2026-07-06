<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/sessions')->assertRedirect(route('login'));
    }

    public function test_calendario_mostra_apenas_sessoes_dos_proprios_clientes(): void
    {
        $user = User::factory()->create();
        $meuCliente = Client::factory()->for($user)->create(['name' => 'Cliente Meu']);
        $clienteAlheio = Client::factory()->create(['name' => 'Cliente Alheio']);

        ClientSession::factory()->for($meuCliente)->create(['scheduled_at' => '2026-07-15 10:00:00']);
        ClientSession::factory()->for($clienteAlheio)->create(['scheduled_at' => '2026-07-15 11:00:00']);

        $response = $this->actingAs($user)->get('/sessions?month=2026-07');

        $response->assertOk()
            ->assertSee('Cliente Meu')
            ->assertDontSee('Cliente Alheio');
    }

    public function test_calendario_filtra_pelo_mes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Cliente Pontual']);

        ClientSession::factory()->for($client)->create(['scheduled_at' => '2026-05-15 10:00:00']);

        $this->actingAs($user)->get('/sessions?month=2026-07')
            ->assertOk()
            ->assertDontSee('Cliente Pontual');

        $this->actingAs($user)->get('/sessions?month=2026-05')
            ->assertOk()
            ->assertSee('Cliente Pontual');
    }

    public function test_agenda_sessao_para_o_proprio_cliente(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['session_value' => 175.00]);

        $response = $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        $response->assertRedirect(route('sessions.index', ['month' => '2026-07']));
        $this->assertDatabaseHas('client_sessions', [
            'client_id' => $client->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_valor_em_branco_congela_o_valor_de_contrato(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['session_value' => 175.00]);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        $session = ClientSession::first();
        $this->assertEquals('175.00', $session->value);

        // Reajuste de contrato não altera sessões já agendadas.
        $client->update(['session_value' => 300.00]);
        $this->assertEquals('175.00', $session->fresh()->value);
    }

    public function test_valor_explicito_e_respeitado(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['session_value' => 175.00]);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
            'value' => '90.00',
        ]);

        $this->assertEquals('90.00', ClientSession::first()->value);
    }

    public function test_nao_e_possivel_agendar_para_cliente_de_outro_profissional(): void
    {
        $user = User::factory()->create();
        $clienteAlheio = Client::factory()->create();

        $response = $this->actingAs($user)->post('/sessions', [
            'client_id' => $clienteAlheio->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        $response->assertSessionHasErrors('client_id');
        $this->assertSame(0, ClientSession::count());
    }

    public function test_marca_sessao_como_realizada(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->create();

        $response = $this->actingAs($user)->patch("/sessions/{$session->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertRedirect();
        $this->assertSame(SessionStatus::Completed, $session->fresh()->status);
    }

    public function test_status_invalido_e_rejeitado(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->create();

        $this->actingAs($user)->patch("/sessions/{$session->id}/status", [
            'status' => 'inexistente',
        ])->assertSessionHasErrors('status');

        $this->assertSame(SessionStatus::Scheduled, $session->fresh()->status);
    }

    public function test_nao_e_possivel_marcar_sessao_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $session = ClientSession::factory()->create();

        $this->actingAs($intruso)->patch("/sessions/{$session->id}/status", [
            'status' => 'completed',
        ])->assertForbidden();

        $this->assertSame(SessionStatus::Scheduled, $session->fresh()->status);
    }
}
