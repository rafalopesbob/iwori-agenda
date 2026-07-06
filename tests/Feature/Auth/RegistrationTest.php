<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_tela_de_registro_renderiza(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_novo_profissional_pode_se_registrar(): void
    {
        $response = $this->post('/register', [
            'name' => 'Profissional Teste',
            'email' => 'pro@iwori.com',
            'password' => 'Senha@Forte123',
            'password_confirmation' => 'Senha@Forte123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'pro@iwori.com']);
    }

    public function test_senha_fraca_e_rejeitada(): void
    {
        $response = $this->post('/register', [
            'name' => 'Profissional Teste',
            'email' => 'pro@iwori.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'pro@iwori.com']);
    }

    public function test_email_duplicado_e_rejeitado(): void
    {
        User::factory()->create(['email' => 'pro@iwori.com']);

        $response = $this->post('/register', [
            'name' => 'Outro Profissional',
            'email' => 'pro@iwori.com',
            'password' => 'Senha@Forte123',
            'password_confirmation' => 'Senha@Forte123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
