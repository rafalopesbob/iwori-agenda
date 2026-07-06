<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\Client;
use App\Models\ClientSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_e_convertido_para_o_enum(): void
    {
        $session = ClientSession::factory()->completed()->create();

        $this->assertInstanceOf(SessionStatus::class, $session->fresh()->status);
        $this->assertSame(SessionStatus::Completed, $session->fresh()->status);
    }

    public function test_faturamento_soma_apenas_sessoes_realizadas(): void
    {
        $client = Client::factory()->create();

        ClientSession::factory()->for($client)->completed()->create(['value' => 100]);
        ClientSession::factory()->for($client)->completed()->create(['value' => 150]);
        ClientSession::factory()->for($client)->noShow()->create(['value' => 100]);
        ClientSession::factory()->for($client)->canceled()->create(['value' => 100]);
        ClientSession::factory()->for($client)->create(['value' => 100]); // agendada

        $this->assertEquals(250, $client->sessions()->billable()->sum('value'));
    }

    public function test_scope_scheduled_between_delimita_o_ciclo(): void
    {
        $client = Client::factory()->create();

        ClientSession::factory()->for($client)->create(['scheduled_at' => '2026-06-15 10:00:00']);
        ClientSession::factory()->for($client)->create(['scheduled_at' => '2026-07-01 10:00:00']);
        ClientSession::factory()->for($client)->create(['scheduled_at' => '2026-07-31 23:00:00']);
        ClientSession::factory()->for($client)->create(['scheduled_at' => '2026-08-01 08:00:00']);

        $doCiclo = $client->sessions()
            ->scheduledBetween('2026-07-01 00:00:00', '2026-07-31 23:59:59')
            ->count();

        $this->assertSame(2, $doCiclo);
    }

    public function test_fechamento_mensal_combina_os_dois_scopes(): void
    {
        $client = Client::factory()->create();

        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-07-10 09:00:00',
            'value' => 200,
        ]);
        ClientSession::factory()->for($client)->noShow()->create([
            'scheduled_at' => '2026-07-12 09:00:00',
            'value' => 200,
        ]);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-06-10 09:00:00',
            'value' => 200,
        ]);

        $faturamentoJulho = $client->sessions()
            ->billable()
            ->scheduledBetween('2026-07-01 00:00:00', '2026-07-31 23:59:59')
            ->sum('value');

        $this->assertEquals(200, $faturamentoJulho);
    }

    public function test_client_id_nao_e_atribuivel_em_massa(): void
    {
        $session = new ClientSession(['duration_minutes' => 60, 'client_id' => 999]);

        $this->assertNull($session->client_id);
        $this->assertSame(60, $session->duration_minutes);
    }
}
