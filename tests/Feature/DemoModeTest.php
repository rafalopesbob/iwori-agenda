<?php

namespace Tests\Feature;

use App\Http\Middleware\DisabledInDemo;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\WhatsAppService;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Liga o modo demo e reaplica o boot do provider (que força os envios para o log),
     * simulando um contêiner que subiu com DEMO_MODE=true.
     */
    private function enableDemo(): void
    {
        config([
            'demo.enabled' => true,
            'mail.default' => 'smtp',
            'services.whatsapp.token' => 'token-configurado-por-engano',
            'services.whatsapp.phone_number_id' => '123',
            'services.google.client_id' => 'client-configurado-por-engano',
        ]);

        (new AppServiceProvider($this->app))->boot();
    }

    private function inProduction(callable $callback): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $callback();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    // ── Fora do modo demo nada muda ──────────────────────────────────

    public function test_sem_demo_a_entrada_rapida_nao_existe(): void
    {
        $this->post('/demo/entrar')->assertNotFound();
        $this->assertGuest();

        $this->get('/login')->assertOk()
            ->assertDontSee('Entrar na demonstração')
            ->assertDontSee('Ambiente de demonstração')
            ->assertSee('Criar conta');
    }

    public function test_sem_demo_o_cadastro_continua_aberto(): void
    {
        $this->get('/register')->assertOk();
    }

    // ── Entrada e aparência ──────────────────────────────────────────

    public function test_login_mostra_botao_e_faixa_da_demonstracao(): void
    {
        $this->enableDemo();

        $this->get('/login')->assertOk()
            ->assertSee('Entrar na demonstração')
            ->assertSee('Ambiente de demonstração')
            ->assertDontSee('Ainda não tem conta?');
    }

    public function test_entrada_rapida_cria_a_conta_demo_se_faltar_e_entra(): void
    {
        $this->enableDemo();

        $this->post('/demo/entrar')->assertRedirect('/dashboard');

        $demo = User::where('email', DemoSeeder::EMAIL)->firstOrFail();
        $this->assertAuthenticatedAs($demo);
        $this->assertTrue($demo->clients()->exists());

        $this->get('/dashboard')->assertOk()->assertSee('Olá, Helena')->assertSee('Ambiente de demonstração');
    }

    public function test_entrada_rapida_usa_a_conta_existente_sem_recriar(): void
    {
        $this->enableDemo();
        $this->seed(DemoSeeder::class);
        $idOriginal = User::where('email', DemoSeeder::EMAIL)->value('id');

        $this->post('/demo/entrar')->assertRedirect('/dashboard');

        $this->assertSame($idOriginal, User::where('email', DemoSeeder::EMAIL)->value('id'));
    }

    // ── Bloqueios ────────────────────────────────────────────────────

    public function test_cadastro_fica_bloqueado(): void
    {
        $this->enableDemo();

        $this->get('/register')->assertRedirect('/login')->assertSessionHas('status', DisabledInDemo::MESSAGE);

        $this->post('/register', [
            'name' => 'Visitante',
            'email' => 'visitante@teste.com',
            'password' => 'Senha-Forte-123!',
            'password_confirmation' => 'Senha-Forte-123!',
        ])->assertSessionHas('status', DisabledInDemo::MESSAGE);

        $this->assertDatabaseMissing('users', ['email' => 'visitante@teste.com']);
    }

    public function test_conta_demo_nao_troca_email_senha_nem_foto(): void
    {
        $this->enableDemo();
        $this->seed(DemoSeeder::class);
        $demo = User::where('email', DemoSeeder::EMAIL)->firstOrFail();
        $hashOriginal = $demo->password;

        $this->actingAs($demo)->from('/profile')
            ->patch('/profile', ['name' => 'Invasor', 'email' => 'invasor@teste.com', 'email_current_password' => 'password'])
            ->assertRedirect('/profile')
            ->assertSessionHas('status', DisabledInDemo::MESSAGE);

        $this->actingAs($demo)->from('/profile')
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'Nova-Senha-Forte-456!',
                'password_confirmation' => 'Nova-Senha-Forte-456!',
            ])
            ->assertSessionHas('status', DisabledInDemo::MESSAGE);

        $this->actingAs($demo)->from('/profile')->delete('/profile/photo')
            ->assertSessionHas('status', DisabledInDemo::MESSAGE);

        $demo->refresh();
        $this->assertSame(DemoSeeder::EMAIL, $demo->email);
        $this->assertNotSame('Invasor', $demo->name);
        $this->assertSame($hashOriginal, $demo->password);
        $this->assertTrue(Hash::check('password', $demo->password));
    }

    public function test_nada_sai_do_sistema_mesmo_com_envios_configurados(): void
    {
        $this->enableDemo();

        $this->assertSame('log', config('mail.default'));
        $this->assertFalse(app(WhatsAppService::class)->isConfigured());
        $this->assertNull(config('services.google.client_id'));
    }

    // ── Reset ────────────────────────────────────────────────────────

    public function test_reset_recria_a_conta_demo(): void
    {
        $this->seed(DemoSeeder::class);
        $idAntigo = User::where('email', DemoSeeder::EMAIL)->value('id');

        $this->artisan('demo:reset')
            ->expectsOutputToContain('Conta de demonstração recriada')
            ->assertSuccessful();

        $this->assertSame(1, User::where('email', DemoSeeder::EMAIL)->count());
        $this->assertNotSame($idAntigo, User::where('email', DemoSeeder::EMAIL)->value('id'));
    }

    public function test_reset_se_recusa_em_producao_sem_demo_mode(): void
    {
        $this->inProduction(function () {
            $this->artisan('demo:reset')->assertFailed();
        });

        $this->assertFalse(User::where('email', DemoSeeder::EMAIL)->exists());
    }

    public function test_reset_roda_em_producao_no_ambiente_de_demonstracao(): void
    {
        config(['demo.enabled' => true]);

        $this->inProduction(function () {
            $this->artisan('demo:reset')->assertSuccessful();
        });

        $this->assertTrue(User::where('email', DemoSeeder::EMAIL)->exists());
    }

    public function test_reset_diario_so_e_agendado_no_ambiente_de_demonstracao(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $hasReset = collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, 'demo:reset'));

        $this->assertFalse($hasReset);
    }
}
