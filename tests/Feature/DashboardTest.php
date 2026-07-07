<?php

namespace Tests\Feature;

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
}
