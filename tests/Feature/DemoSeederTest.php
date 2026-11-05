<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_massa_de_demonstracao_e_coerente_e_pode_ser_recriada(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $users = User::where('email', DemoSeeder::EMAIL)->get();
        $this->assertCount(1, $users);

        $user = $users->first();
        $this->assertSame(17, $user->clients()->count());
        $this->assertSame(15, $user->clients()->active()->count());
        $this->assertTrue($user->clients()->whereHas('sessions', fn ($query) => $query->whereDate('scheduled_at', today()))->exists());
        $this->assertTrue($user->charges()->pending()->exists());
        $this->assertSame(0, $user->charges()->paid()->whereNull('paid_at')->count());
        $this->assertTrue($user->charges()->pending()->get()->contains->isOverdue());
    }

    public function test_conta_de_demonstracao_abre_o_painel_com_dados(): void
    {
        $this->withoutVite();
        $this->seed(DemoSeeder::class);

        $this->actingAs(User::where('email', DemoSeeder::EMAIL)->first())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Olá, Helena')
            ->assertSee('Ana Beatriz Souza')
            ->assertDontSee('Nenhuma sessão agendada para hoje.');
    }
}
