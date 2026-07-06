<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_pertence_ao_profissional(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->assertTrue($client->user->is($user));
        $this->assertTrue($user->clients->first()->is($client));
    }

    public function test_scope_active_filtra_clientes_desativados(): void
    {
        $user = User::factory()->create();
        Client::factory()->for($user)->count(2)->create();
        Client::factory()->for($user)->inactive()->create();

        $this->assertSame(2, $user->clients()->active()->count());
        $this->assertSame(3, $user->clients()->count());
    }

    public function test_anotacoes_ficam_criptografadas_no_banco(): void
    {
        $client = Client::factory()->create(['notes' => 'anotação clínica sigilosa']);

        $raw = DB::table('clients')->where('id', $client->id)->value('notes');

        $this->assertNotSame('anotação clínica sigilosa', $raw);
        $this->assertStringNotContainsString('sigilosa', $raw);
        $this->assertSame('anotação clínica sigilosa', $client->fresh()->notes);
    }

    public function test_soft_delete_preserva_o_registro(): void
    {
        $client = Client::factory()->create();

        $client->delete();

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertNull(Client::find($client->id));
        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function test_user_id_nao_e_atribuivel_em_massa(): void
    {
        $client = new Client(['name' => 'Fulano', 'user_id' => 999]);

        $this->assertNull($client->user_id);
        $this->assertSame('Fulano', $client->name);
    }
}
