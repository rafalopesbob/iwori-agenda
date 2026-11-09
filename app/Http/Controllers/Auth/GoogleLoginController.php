<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleLoginService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleLoginController extends Controller
{
    /**
     * Redireciona para a escolha de conta do Google.
     */
    public function redirect(Request $request, GoogleLoginService $google): RedirectResponse
    {
        abort_unless($google->isConfigured(), 404);

        $state = Str::random(40);
        $codeVerifier = Str::random(64);

        $request->session()->put(GoogleLoginService::FLOW_SESSION_KEY, [
            'state' => $state,
            'verifier' => $codeVerifier,
        ]);

        return redirect()->away($google->authUrl($state, $codeVerifier));
    }

    /**
     * Recebe a volta do Google e entra com a conta correspondente.
     *
     * - Conta Google já vinculada: entra.
     * - E-mail sem conta no Iwori: cria a conta, sem senha.
     * - E-mail de uma conta que já existe: pede a senha uma vez antes de
     *   vincular. A conta funciona antes de o e-mail ser confirmado, então
     *   outra pessoa poderia ter criado essa conta com o e-mail de quem está
     *   entrando agora.
     */
    public function callback(Request $request, GoogleLoginService $google): RedirectResponse
    {
        abort_unless($google->isConfigured(), 404);

        $flow = $request->session()->pull(GoogleLoginService::FLOW_SESSION_KEY);

        if (! is_array($flow) || ! hash_equals($flow['state'], (string) $request->query('state'))) {
            return $this->fail('A entrada com o Google expirou. Tente de novo.');
        }

        $code = $request->query('code');

        if ($request->query('error') || ! is_string($code) || $code === '') {
            return redirect()->route('login')->with('status', 'Entrada com o Google cancelada.');
        }

        try {
            $identity = $google->identity($code, $flow['verifier']);
        } catch (RequestException|ConnectionException) {
            $identity = null;
        }

        if ($identity === null) {
            return $this->fail('Não foi possível confirmar sua conta Google. Tente de novo.');
        }

        $user = User::where('google_id', $identity['sub'])->first();

        if ($user === null) {
            $existing = User::where('email', $identity['email'])->first();

            if ($existing !== null) {
                return $this->askPasswordToLink($request, $existing, $identity);
            }

            $user = $this->register($identity);
        }

        Auth::login($user);

        // Regenera o ID da sessão para impedir session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Guarda a conta Google na sessão e manda para o login com senha.
     * O vínculo é feito em AuthenticatedSessionController quando a senha confere.
     *
     * @param  array{sub: string, email: string, name: string}  $identity
     */
    protected function askPasswordToLink(Request $request, User $existing, array $identity): RedirectResponse
    {
        if ($existing->google_id !== null) {
            return $this->fail('A conta com este e-mail já está vinculada a outra conta Google. Entre com e-mail e senha.');
        }

        $request->session()->put(GoogleLoginService::PENDING_LINK_SESSION_KEY, [
            'sub' => $identity['sub'],
            'email' => $identity['email'],
        ]);

        return redirect()->route('login')
            ->withInput(['email' => $existing->email])
            ->with('status', 'Já existe uma conta com este e-mail. Entre com sua senha uma vez para vincular o Google a ela. Depois, é só usar o botão do Google. Se não lembrar a senha, use "Esqueci minha senha".');
    }

    /**
     * Cria a conta de quem entra pelo Google pela primeira vez.
     *
     * @param  array{sub: string, email: string, name: string}  $identity
     */
    protected function register(array $identity): User
    {
        $user = new User([
            'name' => $identity['name'],
            'email' => $identity['email'],
        ]);

        // O Google já confirmou o e-mail: nenhum link de confirmação é enviado.
        $user->forceFill([
            'google_id' => $identity['sub'],
            'email_verified_at' => now(),
        ])->save();

        event(new Registered($user));

        return $user;
    }

    /**
     * Volta ao login com a mensagem de erro do Google.
     */
    protected function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
