<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\DisabledInDemo;
use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NOVA_SENHA = 'Nova-Senha-Forte-456!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_tem_link_para_esqueci_minha_senha(): void
    {
        $this->get('/login')->assertOk()->assertSee('Esqueci minha senha')->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('Enviar link');
    }

    public function test_pedido_envia_link_pela_fila_para_conta_existente(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@teste.com']);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'ANA@teste.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordQueued::class);
        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordQueued('token'));
    }

    public function test_email_inexistente_recebe_a_mesma_resposta_e_nada_e_enviado(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ana@teste.com']);

        $existente = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ana@teste.com']);
        $inexistente = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ninguem@teste.com']);

        // Mesma mensagem nos dois casos: a tela não revela quem tem conta.
        $this->assertSame(
            $existente->getSession()->get('status'),
            $inexistente->getSession()->get('status'),
        );
        Notification::assertSentTimes(ResetPasswordQueued::class, 1);
    }

    public function test_link_do_email_abre_a_tela_de_nova_senha(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Criar nova senha')
            ->assertSee($user->email);
    }

    public function test_redefine_a_senha_com_token_valido(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'Senha-Antiga-123!']);
        $rememberAntigo = $user->remember_token;
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NOVA_SENHA,
            'password_confirmation' => self::NOVA_SENHA,
        ])->assertRedirect('/login')->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check(self::NOVA_SENHA, $user->password));
        $this->assertNotSame($rememberAntigo, $user->remember_token);
        // Receber o link no e-mail comprova o endereço.
        $this->assertTrue($user->hasVerifiedEmail());

        // O token é de uso único.
        $this->from('/reset-password')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Outra-Senha-Forte-789!',
            'password_confirmation' => 'Outra-Senha-Forte-789!',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::NOVA_SENHA, $user->refresh()->password));
    }

    public function test_token_invalido_nao_troca_a_senha(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Antiga-123!']);

        $this->from('/reset-password')->post('/reset-password', [
            'token' => 'token-inventado',
            'email' => $user->email,
            'password' => self::NOVA_SENHA,
            'password_confirmation' => self::NOVA_SENHA,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Senha-Antiga-123!', $user->refresh()->password));
    }

    public function test_token_de_um_usuario_nao_serve_para_outro(): void
    {
        $atacante = User::factory()->create();
        $vitima = User::factory()->create(['password' => 'Senha-Da-Vitima-123!']);
        $token = Password::createToken($atacante);

        $this->from('/reset-password')->post('/reset-password', [
            'token' => $token,
            'email' => $vitima->email,
            'password' => self::NOVA_SENHA,
            'password_confirmation' => self::NOVA_SENHA,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Senha-Da-Vitima-123!', $vitima->refresh()->password));
    }

    public function test_nova_senha_respeita_a_politica_forte(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->from('/reset-password')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertSessionHasErrors('password');
    }

    public function test_link_expirado_nao_troca_a_senha(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Antiga-123!']);
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->from('/reset-password')->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NOVA_SENHA,
            'password_confirmation' => self::NOVA_SENHA,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Senha-Antiga-123!', $user->refresh()->password));
    }

    public function test_pedidos_em_excesso_sao_limitados(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->post('/forgot-password', ['email' => 'alvo@teste.com']);
        }

        $this->post('/forgot-password', ['email' => 'alvo@teste.com'])->assertStatus(429);
    }

    public function test_em_producao_o_link_usa_o_app_url_e_nao_o_host_da_requisicao(): void
    {
        config(['app.url' => 'https://agenda.iwori.test']);
        $this->app->detectEnvironment(fn () => 'production');

        try {
            (new AppServiceProvider($this->app))->boot();
            $this->app['request']->headers->set('Host', 'evil.example');

            $mail = (new ResetPasswordQueued('abc'))->toMail(User::factory()->create(['email' => 'ana@teste.com']));

            $this->assertStringStartsWith('https://agenda.iwori.test/reset-password/abc', $mail->actionUrl);
            $this->assertStringNotContainsString('evil.example', $mail->actionUrl);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_fica_desligado_no_ambiente_de_demonstracao(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/login')->assertDontSee('Esqueci minha senha');
        $this->get('/forgot-password')->assertRedirect('/login')->assertSessionHas('status', DisabledInDemo::MESSAGE);
    }
}
