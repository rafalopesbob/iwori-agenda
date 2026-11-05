<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/clients')->assertRedirect(route('login'));
    }

    public function test_listagem_mostra_apenas_os_proprios_clientes(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $clientA = Client::factory()->for($userA)->create(['name' => 'Cliente do Profissional A']);
        Client::factory()->for($userB)->create(['name' => 'Cliente do Profissional B']);

        $response = $this->actingAs($userA)->get('/clients');

        $response->assertOk()
            ->assertSee('Cliente do Profissional A')
            ->assertDontSee('Cliente do Profissional B');
    }

    public function test_nome_do_cliente_nao_executa_script_no_confirm_de_remocao(): void
    {
        $user = User::factory()->create();
        Client::factory()->for($user)->create(['name' => "');alert(document.domain);('"]);

        $html = $this->actingAs($user)->get('/clients')->assertOk()->getContent();

        // O nome entra no confirm() como string JS escapada, sem fechar a aspa.
        $this->assertStringNotContainsString("confirm('Remover o cliente &#039;", $html);
        $apos = chr(92).'u0027'; // aspa simples escapada pelo @js
        $this->assertStringContainsString('Remover o cliente '.$apos.');alert(document.domain);('.$apos.'?', $html);
    }

    public function test_valor_antigo_do_formulario_nao_executa_script_no_alpine(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/clients/create')
            ->post('/clients', ['payment_cycle' => "x'+alert(1)+'"])
            ->assertSessionHasErrors('payment_cycle');

        $html = $this->actingAs($user)->get('/clients/create')->assertOk()->getContent();

        $this->assertStringNotContainsString("cycle: 'x&#039;+alert(1)+&#039;'", $html);
        $apos = chr(92).'u0027'; // aspa simples escapada pelo @js
        $this->assertStringContainsString("cycle: 'x".$apos.'+alert(1)+'.$apos."'", $html);
    }

    public function test_profissional_cadastra_cliente(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/clients', [
            'name' => 'Novo Cliente',
            'email' => 'cliente@teste.com',
            'phone' => '(11) 99999-9999',
            'session_value' => '180.50',
            'notes' => 'primeira anotação',
            'active' => '1',
            'payment_cycle' => 'monthly',
            'payment_day' => '5',
            'billing_channel' => 'email',
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'name' => 'Novo Cliente',
            'user_id' => $user->id,
        ]);
    }

    public function test_validacao_exige_nome_e_valor_da_sessao(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/clients', [
            'name' => '',
            'session_value' => 'abc',
        ]);

        $response->assertSessionHasErrors(['name', 'session_value']);
    }

    public function test_profissional_atualiza_o_proprio_cliente(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/clients/{$client->id}", [
            'name' => 'Nome Atualizado',
            'session_value' => '200.00',
            'active' => '1',
            'payment_cycle' => 'monthly',
            'payment_day' => '5',
            'billing_channel' => 'email',
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Nome Atualizado']);
    }

    public function test_remocao_e_soft_delete(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->delete("/clients/{$client->id}")
            ->assertRedirect(route('clients.index'));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_nao_e_possivel_ver_cliente_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($intruso)->get("/clients/{$client->id}")->assertForbidden();
    }

    public function test_nao_e_possivel_editar_cliente_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $client = Client::factory()->create(['name' => 'Nome Original']);

        $this->actingAs($intruso)->get("/clients/{$client->id}/edit")->assertForbidden();

        $this->actingAs($intruso)->put("/clients/{$client->id}", [
            'name' => 'Invadido',
            'session_value' => '1.00',
            'active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Nome Original']);
    }

    public function test_nao_e_possivel_remover_cliente_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($intruso)->delete("/clients/{$client->id}")->assertForbidden();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }
}
