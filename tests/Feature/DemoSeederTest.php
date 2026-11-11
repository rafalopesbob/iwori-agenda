<?php

namespace Tests\Feature;

use App\Models\ClientSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_roda_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->app->make(DemoSeeder::class)->run();
            $this->fail('O DemoSeeder deveria recusar o ambiente de produção.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('produção', $e->getMessage());
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertFalse(User::where('email', DemoSeeder::EMAIL)->exists());
    }

    /**
     * A agenda da demo só tem sessões de segunda a sexta, e a Ana Beatriz é
     * atendida às quintas. Fixar uma quinta deixa os testes iguais todo dia.
     */
    private function numaQuintaFeira(): void
    {
        $this->travelTo(now()->next('thursday')->setTime(14, 0));
    }

    public function test_massa_de_demonstracao_e_coerente_e_pode_ser_recriada(): void
    {
        $this->numaQuintaFeira();
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

    public function test_dia_mais_cheio_cai_sempre_no_dia_da_apresentacao(): void
    {
        // Segunda, quinta, sábado e domingo.
        foreach (['2026-11-09', '2026-11-12', '2026-11-14', '2026-11-15'] as $date) {
            $this->travelTo(CarbonImmutable::parse($date.' 07:00'));
            $this->seed(DemoSeeder::class);

            $user = User::where('email', DemoSeeder::EMAIL)->first();
            $todaySessions = ClientSession::query()
                ->whereHas('client', fn ($query) => $query->where('user_id', $user->id))
                ->whereDate('scheduled_at', today())
                ->count();

            $this->assertSame(5, $todaySessions, "Sessões de hoje em {$date}");
        }
    }

    public function test_conta_de_demonstracao_abre_o_painel_com_dados(): void
    {
        $this->withoutVite();
        $this->numaQuintaFeira();
        $this->seed(DemoSeeder::class);

        $this->actingAs(User::where('email', DemoSeeder::EMAIL)->first())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Olá, Kátia')
            ->assertSee('Ana Beatriz Souza')
            ->assertDontSee('Nenhuma sessão agendada para hoje.');
    }
}
