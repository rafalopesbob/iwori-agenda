<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\GoogleLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Exibe o formulário de login.
     */
    public function create(GoogleLoginService $google): View
    {
        return view('auth.login', ['googleLogin' => $google->isAvailable()]);
    }

    /**
     * Autentica o profissional.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Regenera o ID da sessão para impedir session fixation.
        $request->session()->regenerate();

        $response = redirect()->intended(route('dashboard'));

        if ($this->linkPendingGoogleAccount($request)) {
            $response->with('status', 'Conta Google vinculada. Nas próximas vezes, é só usar o botão "Entrar com Google".');
        }

        return $response;
    }

    /**
     * Vincula a conta Google que esperava a senha (ver GoogleLoginController).
     *
     * Só vincula se a conta que acabou de entrar é a do e-mail que veio do Google.
     * Com a senha certa e o e-mail confirmado pelo Google, o e-mail também
     * passa a contar como verificado.
     */
    protected function linkPendingGoogleAccount(Request $request): bool
    {
        $pending = $request->session()->pull(GoogleLoginService::PENDING_LINK_SESSION_KEY);
        $user = $request->user();

        // Contas antigas podem ter o e-mail com maiúsculas; o do Google vem minúsculo.
        if (! is_array($pending)
            || $pending['email'] !== Str::lower($user->email)
            || $user->google_id !== null
            || User::where('google_id', $pending['sub'])->exists()) {
            return false;
        }

        $user->forceFill([
            'google_id' => $pending['sub'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return true;
    }

    /**
     * Encerra a sessão autenticada.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
