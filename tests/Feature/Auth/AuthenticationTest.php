<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_tela_de_login_renderiza(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_com_credenciais_validas(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_login_com_senha_errada_falha(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_exige_autenticacao(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_acessivel_autenticado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_login_sofre_rate_limit_apos_cinco_tentativas(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'senha-errada',
            ]);
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertStatus(429);
    }

    public function test_trocar_o_ip_no_x_forwarded_for_nao_contorna_o_rate_limit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->withHeader('X-Forwarded-For', "203.0.113.{$i}")
                ->post('/login', ['email' => $user->email, 'password' => 'senha-errada']);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.99')
            ->post('/login', ['email' => $user->email, 'password' => 'senha-errada'])
            ->assertStatus(429);
    }

    public function test_rate_limit_por_email_ignora_maiusculas_e_espacos(): void
    {
        $user = User::factory()->create(['email' => 'ana@teste.com']);

        foreach (range(1, 5) as $i) {
            $this->withHeader('X-Forwarded-For', "203.0.113.{$i}")
                ->post('/login', ['email' => " ANA@teste.com{$this->spaces($i)}", 'password' => 'senha-errada']);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.99')
            ->post('/login', ['email' => 'Ana@Teste.com', 'password' => 'senha-errada'])
            ->assertStatus(429);
    }

    private function spaces(int $count): string
    {
        return str_repeat(' ', $count);
    }
}
