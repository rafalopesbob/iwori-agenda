<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Textos do sistema em português (validação, paginação, e-mails) e o botão de
 * mostrar a senha nos formulários.
 */
class PortugueseUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function cadastro(array $dados): \Illuminate\Testing\TestResponse
    {
        return $this->from('/register')->post('/register', $dados + [
            'name' => 'Ana Teste',
            'email' => 'ana@teste.com',
            'password' => 'Senha-Forte-123!',
            'password_confirmation' => 'Senha-Forte-123!',
        ]);
    }

    public function test_idioma_padrao_e_portugues(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());
        // No Render não há APP_LOCALE: o padrão do config precisa ser pt_BR.
        $this->assertStringContainsString("env('APP_LOCALE', 'pt_BR')", file_get_contents(config_path('app.php')));
    }

    public function test_senha_curta_mostra_mensagem_em_portugues(): void
    {
        $this->cadastro(['password' => 'Ab1!', 'password_confirmation' => 'Ab1!'])
            ->assertSessionHasErrors(['password' => 'O campo senha precisa ter pelo menos 12 caracteres.']);
    }

    public function test_regras_da_senha_forte_em_portugues(): void
    {
        $this->cadastro(['password' => 'senhasemmaiuscula', 'password_confirmation' => 'senhasemmaiuscula'])
            ->assertSessionHasErrors(['password' => 'O campo senha precisa ter pelo menos uma letra maiúscula e uma minúscula.'])
            ->assertSessionHasErrors(['password' => 'O campo senha precisa ter pelo menos um número.'])
            ->assertSessionHasErrors(['password' => 'O campo senha precisa ter pelo menos um símbolo.']);
    }

    public function test_confirmacao_e_obrigatorio_e_unico_em_portugues(): void
    {
        $this->cadastro(['password_confirmation' => 'Outra-Senha-123!'])
            ->assertSessionHasErrors(['password' => 'A confirmação do campo senha não confere.']);

        $this->cadastro(['name' => ''])
            ->assertSessionHasErrors(['name' => 'O campo nome é obrigatório.']);

        User::factory()->create(['email' => 'ja@existe.com']);
        $this->cadastro(['email' => 'ja@existe.com'])
            ->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);
    }

    public function test_email_com_maiusculas_no_cadastro_e_aceito_em_minusculas(): void
    {
        $this->cadastro(['email' => '  Ana.Silva@Teste.COM '])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'ana.silva@teste.com']);
        $this->assertAuthenticated();
    }

    public function test_paginacao_em_portugues(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(16)->for($user)->create();

        $this->actingAs($user)->get('/clients')
            ->assertOk()
            ->assertSee('Mostrando')
            ->assertSee('resultados')
            ->assertSee('Próxima', false)
            ->assertDontSee('Showing')
            ->assertDontSee('Next &raquo;', false);
    }

    public function test_rodape_dos_emails_em_portugues(): void
    {
        $user = User::factory()->unverified()->create();

        foreach ([new VerifyEmailQueued, new ResetPasswordQueued('token')] as $notificacao) {
            $html = (string) $notificacao->toMail($user)->render();

            $this->assertStringContainsString('Se o botão', $html);
            $this->assertStringContainsString('Todos os direitos reservados.', $html);
            $this->assertStringNotContainsString("having trouble", $html);
            $this->assertStringNotContainsString('All rights reserved', $html);
        }
    }

    public function test_campos_de_senha_tem_botao_de_mostrar(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('aria-label="Mostrar senha"', false)
            ->assertSee('aria-controls="password"', false);

        $register = $this->get('/register')->assertOk();
        $this->assertSame(2, substr_count($register->getContent(), 'aria-label="Mostrar senha"'));
        $register->assertSee('aria-controls="password_confirmation"', false);

        $profile = $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
        // Senha atual (troca de e-mail), senha atual, nova senha e confirmação.
        $this->assertSame(4, substr_count($profile->getContent(), 'aria-label="Mostrar senha"'));
        $profile->assertSee('x-bind:required="email !== original"', false);
    }

    public function test_campo_de_senha_continua_password_sem_javascript(): void
    {
        $html = $this->get('/login')->getContent();

        // O type inicial é password; o Alpine só troca para text quando clicam no olho.
        $this->assertMatchesRegularExpression('/<input id="password" name="password" type="password"/', $html);
        $this->assertStringContainsString('x-bind:type="visivel ? \'text\' : \'password\'"', $html);
    }
}
