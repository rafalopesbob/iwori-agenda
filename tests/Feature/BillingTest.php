<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/billing')->assertRedirect(route('login'));
    }

    public function test_fechamento_soma_faturaveis_do_mes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-07-10 09:00:00', 'value' => 150,
        ]);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-07-17 09:00:00', 'value' => 100,
        ]);
        ClientSession::factory()->for($client)->noShow()->create([
            'scheduled_at' => '2026-07-20 09:00:00', 'value' => 200, // não avisou: cobrada
        ]);
        ClientSession::factory()->for($client)->noShowExcused()->create([
            'scheduled_at' => '2026-07-22 09:00:00', 'value' => 500, // avisou: abonada
        ]);
        ClientSession::factory()->for($client)->create([
            'scheduled_at' => '2026-07-25 09:00:00', 'value' => 200, // ainda agendada
        ]);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-06-10 09:00:00', 'value' => 300, // mês anterior
        ]);

        $report = app(BillingService::class)->monthlyReport($user, '2026-07');

        $this->assertCount(1, $report['rows']);
        $this->assertSame(2, $report['rows'][0]['completed']);
        $this->assertSame(1, $report['rows'][0]['no_show']);
        $this->assertSame(1, $report['rows'][0]['no_show_excused']);
        $this->assertSame(1, $report['rows'][0]['scheduled']);
        // 150 + 100 realizadas + 200 falta não informada; a informada (500) fica fora.
        $this->assertEquals(450.0, $report['rows'][0]['total']);
        $this->assertEquals(450.0, $report['totals']['total']);
        $this->assertSame(1, $report['totals']['no_show_excused']);
    }

    public function test_tela_exibe_totais_do_mes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Cliente Faturado']);

        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-07-10 09:00:00', 'value' => 250,
        ]);

        $response = $this->actingAs($user)->get('/billing?month=2026-07');

        $response->assertOk()
            ->assertSee('Cliente Faturado')
            ->assertSee('250,00');
    }

    public function test_fechamento_isola_por_profissional(): void
    {
        $user = User::factory()->create();
        $clienteAlheio = Client::factory()->create(['name' => 'Cliente Alheio']);

        ClientSession::factory()->for($clienteAlheio)->completed()->create([
            'scheduled_at' => '2026-07-10 09:00:00', 'value' => 999,
        ]);

        $response = $this->actingAs($user)->get('/billing?month=2026-07');

        $response->assertOk()
            ->assertDontSee('Cliente Alheio')
            ->assertDontSee('999');
    }

    public function test_fechamento_agrupa_varios_clientes_em_ordem_alfabetica(): void
    {
        $user = User::factory()->create();
        $zeca = Client::factory()->for($user)->create(['name' => 'Zeca']);
        $ana = Client::factory()->for($user)->create(['name' => 'Ana']);

        ClientSession::factory()->for($zeca)->completed()->create([
            'scheduled_at' => '2026-07-10 09:00:00', 'value' => 100,
        ]);
        ClientSession::factory()->for($ana)->completed()->create([
            'scheduled_at' => '2026-07-11 09:00:00', 'value' => 120,
        ]);

        $report = app(BillingService::class)->monthlyReport($user, '2026-07');

        $this->assertSame(['Ana', 'Zeca'], $report['rows']->pluck('client.name')->all());
        $this->assertEquals(220.0, $report['totals']['total']);
    }

    public function test_mes_sem_sessoes_mostra_estado_vazio(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/billing?month=2026-03');

        $response->assertOk()->assertSee('Nenhuma sessão neste mês.');
    }
}
