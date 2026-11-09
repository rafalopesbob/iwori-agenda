<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleLoginService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Exibe o formulário de registro.
     */
    public function create(GoogleLoginService $google): View
    {
        return view('auth.register', ['googleLogin' => $google->isAvailable()]);
    }

    /**
     * Registra um novo profissional.
     */
    public function store(Request $request): RedirectResponse
    {
        // O teclado do celular costuma começar o e-mail com maiúscula: normaliza
        // antes de validar, em vez de recusar o cadastro.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($validated);

        // Dispara o e-mail de confirmação (listener padrão do Laravel para MustVerifyEmail).
        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
