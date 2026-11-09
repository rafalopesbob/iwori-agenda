<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\GoogleLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function withGoogleConfigured(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
    }

    /**
     * ID token no formato do Google, com as claims padrão de uma conta válida.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function idToken(array $claims = []): string
    {
        $claims = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id',
            'sub' => 'google-sub-123',
            'email' => 'ana@gmail.com',
            'email_verified' => true,
            'name' => 'Ana Souza',
            'exp' => now()->addHour()->timestamp,
        ], $claims);

        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        return $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims).'.assinatura';
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function fakeGoogle(array $claims = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-de-acesso',
                'id_token' => $this->idToken($claims),
                'expires_in' => 3600,
            ]),
        ]);
    }

    protected function googleCallback(string $query = 'state=estado&code=codigo'): TestResponse
    {
        return $this->withSession([
            GoogleLoginService::FLOW_SESSION_KEY => ['state' => 'estado', 'verifier' => 'verificador'],
        ])->get('/login/google/callback?'.$query);
    }

    public function test_sem_credenciais_o_botao_some_e_a_rota_nao_existe(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Entrar com Google');
        $this->get('/register')->assertOk()->assertDontSee('Criar conta com Google');
        $this->get('/login/google')->assertNotFound();
    }

    public function test_botao_aparece_no_login_e_no_cadastro(): void
    {
        $this->withGoogleConfigured();

        $this->get('/login')->assertSee('Entrar com Google')->assertSee(route('login.google'));
        $this->get('/register')->assertSee('Criar conta com Google');
    }

    public function test_redireciona_para_o_google_com_state_e_pkce(): void
    {
        $this->withGoogleConfigured();

        $response = $this->get('/login/google');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $flow = session(GoogleLoginService::FLOW_SESSION_KEY);

        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame(route('login.google.callback'), $query['redirect_uri']);
        $this->assertSame($flow['state'], $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='),
            $query['code_challenge'],
        );
    }

    public function test_primeiro_acesso_cria_a_conta_sem_senha(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle();

        $response = $this->googleCallback();

        $response->assertRedirect(route('dashboard'));
        $user = User::where('email', 'ana@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertSame('Ana Souza', $user->name);
        $this->assertFalse($user->hasPassword());
        $this->assertNotNull($user->email_verified_at);

        Http::assertSent(fn (Request $request) => $request['code'] === 'codigo'
            && $request['code_verifier'] === 'verificador'
            && $request['redirect_uri'] === route('login.google.callback'));
    }

    public function test_conta_vinculada_entra_pelo_sub_mesmo_com_outro_email(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle(['email' => 'ana.nova@gmail.com']);
        $user = User::factory()->create(['email' => 'ana@gmail.com']);
        $user->forceFill(['google_id' => 'google-sub-123'])->save();

        $this->googleCallback()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_email_de_conta_existente_pede_a_senha_antes_de_vincular(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle();
        $user = User::factory()->unverified()->create(['email' => 'ana@gmail.com']);

        $response = $this->googleCallback();

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $response->assertSessionHasInput('email', 'ana@gmail.com');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);

        $this->post('/login', ['email' => 'ana@gmail.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-sub-123', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_senha_errada_nao_vincula(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle();
        $user = User::factory()->create(['email' => 'ana@gmail.com']);

        $this->googleCallback();
        $this->post('/login', ['email' => 'ana@gmail.com', 'password' => 'senha-errada']);

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_vinculo_pendente_nao_vale_para_outra_conta(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle();
        $ana = User::factory()->create(['email' => 'ana@gmail.com']);
        $outra = User::factory()->create(['email' => 'outra@example.com']);

        $this->googleCallback();
        $this->post('/login', ['email' => 'outra@example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($outra);
        $this->assertNull($outra->fresh()->google_id);
        $this->assertNull($ana->fresh()->google_id);
    }

    public function test_email_vinculado_a_outra_conta_google_e_recusado(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle();
        $user = User::factory()->create(['email' => 'ana@gmail.com']);
        $user->forceFill(['google_id' => 'outro-sub'])->save();

        $this->googleCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google')
            ->assertSessionMissing(GoogleLoginService::PENDING_LINK_SESSION_KEY);

        $this->assertGuest();
    }

    public function test_state_diferente_e_recusado_sem_chamar_o_google(): void
    {
        $this->withGoogleConfigured();
        Http::fake();

        $this->googleCallback('state=outro&code=codigo')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_callback_sem_login_em_andamento_e_recusado(): void
    {
        $this->withGoogleConfigured();
        Http::fake();

        $this->get('/login/google/callback?state=&code=codigo')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        Http::assertNothingSent();
    }

    public function test_cancelar_no_google_volta_ao_login(): void
    {
        $this->withGoogleConfigured();
        Http::fake();

        $this->googleCallback('state=estado&error=access_denied')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Entrada com o Google cancelada.');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_email_nao_confirmado_pelo_google_e_recusado(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle(['email_verified' => false]);

        $this->googleCallback()->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_token_emitido_para_outro_app_e_recusado(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle(['aud' => 'outro-app']);

        $this->googleCallback()->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_token_expirado_e_recusado(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle(['exp' => now()->subMinute()->timestamp]);

        $this->googleCallback()->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_falha_ao_trocar_o_codigo_volta_ao_login(): void
    {
        $this->withGoogleConfigured();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->googleCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_conta_sem_senha_nao_entra_pelo_formulario(): void
    {
        User::factory()->create(['email' => 'ana@gmail.com', 'password' => null]);

        $this->post('/login', ['email' => 'ana@gmail.com', 'password' => 'qualquer-coisa'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_conta_sem_senha_define_senha_sem_informar_a_atual(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->get('/profile')
            ->assertSee('Definir senha')
            ->assertDontSee('Senha atual');

        $this->actingAs($user)->put('/profile/password', [
            'password' => 'Senha@Forte123',
            'password_confirmation' => 'Senha@Forte123',
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Senha@Forte123', $user->fresh()->password));
    }

    public function test_primeiro_acesso_nao_manda_link_de_confirmacao(): void
    {
        Notification::fake();
        $this->withGoogleConfigured();
        $this->fakeGoogle();

        $this->googleCallback()->assertRedirect(route('dashboard'));

        $user = User::where('email', 'ana@gmail.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_vincula_conta_antiga_com_email_em_maiusculas(): void
    {
        $this->withGoogleConfigured();
        $this->fakeGoogle();
        $user = User::factory()->create(['email' => 'Ana@Gmail.com']);

        $this->googleCallback()->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'Ana@Gmail.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-sub-123', $user->fresh()->google_id);
    }

    public function test_na_demo_o_botao_some_e_as_rotas_ficam_bloqueadas(): void
    {
        $this->withGoogleConfigured();
        config(['demo.enabled' => true]);
        Http::fake();

        $this->get('/login')->assertOk()->assertDontSee('Entrar com Google');
        $this->get('/login/google')->assertRedirect(route('login'));
        $this->googleCallback()->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
        Http::assertNothingSent();
    }

    public function test_conta_sem_senha_so_troca_o_email_depois_de_definir_uma(): void
    {
        $user = User::factory()->create(['email' => 'ana@gmail.com', 'password' => null]);

        $this->actingAs($user)->get('/profile')
            ->assertSee('defina uma senha antes')
            ->assertDontSee('email_current_password');

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => 'ana.nova@gmail.com'])
            ->assertSessionHasErrors('email');
        $this->assertSame('ana@gmail.com', $user->fresh()->email);

        // Mudar só o nome continua livre.
        $this->actingAs($user)->patch('/profile', ['name' => 'Ana Souza', 'email' => 'ana@gmail.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Ana Souza', $user->fresh()->name);
    }

    public function test_conta_com_senha_continua_exigindo_a_senha_atual(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'password' => 'Senha@Forte123',
            'password_confirmation' => 'Senha@Forte123',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
