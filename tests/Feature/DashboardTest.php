<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_painel_sem_dados_renderiza(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Nenhuma sessão agendada para hoje.');
    }

    public function test_painel_mostra_sessoes_de_hoje_e_resumo_do_mes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Cliente de Hoje']);

        ClientSession::factory()->for($client)->create([
            'scheduled_at' => now()->setTime(14, 30),
        ]);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->startOfMonth()->setTime(10, 0),
            'value' => 320,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Cliente de Hoje')
            ->assertSee('14:30')
            ->assertSee('320,00');
    }

    public function test_painel_nao_mostra_sessoes_de_outro_profissional(): void
    {
        $user = User::factory()->create();
        $clienteAlheio = Client::factory()->create(['name' => 'Cliente Alheio']);
        ClientSession::factory()->for($clienteAlheio)->create([
            'scheduled_at' => now()->setTime(9, 0),
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Cliente Alheio');
    }

    public function test_painel_mostra_cobrancas_pendentes_e_total_a_receber(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Cliente Devedor']);
        Charge::factory()->pending()->create(['user_id' => $user->id, 'client_id' => $client->id, 'amount' => 450]);
        Charge::factory()->paid()->create(['user_id' => $user->id, 'client_id' => $client->id, 'amount' => 999]);
        Charge::factory()->pending()->create(['amount' => 777]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Cliente Devedor')
            ->assertSee('R$ 450,00')
            ->assertSee('1 cobrança pendente')
            ->assertDontSee('999,00')
            ->assertDontSee('777,00');
    }

    public function test_painel_resume_os_proximos_dias_com_sessao(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();
        ClientSession::factory()->for($client)->count(2)->create(['scheduled_at' => now()->addDays(2)->setTime(10, 0)]);
        ClientSession::factory()->for($client)->canceled()->create(['scheduled_at' => now()->addDays(3)->setTime(10, 0)]);

        $label = fn ($date) => ucfirst($date->locale('pt_BR')->translatedFormat('l, j'));

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee($label(now()->addDays(2)))
            ->assertDontSee($label(now()->addDays(3)))
            ->assertDontSee('Nenhuma sessão nas próximas duas semanas.');
    }
}
