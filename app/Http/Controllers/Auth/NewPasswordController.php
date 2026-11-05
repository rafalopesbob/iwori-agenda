<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Tela para escolher a nova senha (destino do link enviado por e-mail).
     */
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Grava a nova senha se o link (token + e-mail) for válido e não tiver expirado.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $credentials = [
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'token' => $request->input('token'),
        ];

        $status = Password::reset($credentials, function (User $user, string $password) {
            // Novo remember_token derruba os "manter conectado" antigos; a troca do
            // hash da senha derruba as demais sessões (middleware auth.session).
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ]);

            // Quem recebeu o link no e-mail provou que é dono do endereço.
            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }

            $user->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Senha redefinida. Entre com a nova senha.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Este link de redefinição é inválido ou expirou. Peça um novo.']);
    }
}
