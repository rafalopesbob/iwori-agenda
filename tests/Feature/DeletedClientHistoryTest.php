<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cliente removido (soft delete) que ainda tem sessões e cobranças:
 * o histórico continua mostrando o nome, sem erro 500, e o cliente
 * não volta para a agenda nem para o faturamento.
 */
class DeletedClientHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ClientSession $session;

    protected Charge $charge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->user = User::factory()->create();
        $client = Client::factory()->for($this->user)->create(['name' => 'Cliente Removido']);

        $this->session = ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->startOfMonth()->setTime(10, 0),
            'value' => 250,
        ]);
        $this->charge = Charge::factory()->forSession($this->session)->pending()->create();

        $client->delete();
    }

    public function test_painel_mostra_cobranca_pendente_de_cliente_removido(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Cliente Removido');
    }

    public function test_lista_de_cobrancas_mostra_cliente_removido(): void
    {
        $this->actingAs($this->user)->get('/charges')
            ->assertOk()
            ->assertSee('Cliente Removido');
    }

    public function test_agenda_e_faturamento_nao_mostram_sessoes_de_cliente_removido(): void
    {
        $this->actingAs($this->user)->get('/sessions?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertDontSee('Cliente Removido');

        $this->actingAs($this->user)->get('/billing')
            ->assertOk()
            ->assertDontSee('Cliente Removido');
    }

    public function test_dono_abre_sessao_de_cliente_removido(): void
    {
        $this->actingAs($this->user)->get("/sessions/{$this->session->id}/edit")
            ->assertOk()
            ->assertSee('Cliente Removido');
    }

    public function test_outro_profissional_continua_bloqueado_na_sessao_de_cliente_removido(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->get("/sessions/{$this->session->id}/edit")
            ->assertForbidden();

        $this->actingAs($other)->patch("/sessions/{$this->session->id}/status", [
            'status' => SessionStatus::Canceled->value,
        ])->assertForbidden();

        $this->actingAs($other)->post("/sessions/{$this->session->id}/charge")
            ->assertForbidden();

        $this->assertSame(SessionStatus::Completed, $this->session->fresh()->status);
    }

    public function test_outro_profissional_nao_ve_cobranca_de_cliente_removido(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)->get('/charges')
            ->assertOk()
            ->assertDontSee('Cliente Removido');

        $this->actingAs($other)->patch("/charges/{$this->charge->id}/reopen")
            ->assertForbidden();
    }
}
