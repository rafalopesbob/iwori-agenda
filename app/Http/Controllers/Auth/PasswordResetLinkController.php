<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Tela "Esqueci minha senha".
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Envia o link de redefinição de senha.
     *
     * A resposta é sempre a mesma, exista ou não a conta: assim a tela não
     * serve para descobrir quais e-mails estão cadastrados.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        Password::sendResetLink(['email' => $request->string('email')->lower()->toString()]);

        return back()->with('status', 'Se houver uma conta com esse e-mail, enviamos um link para redefinir a senha. Confira também a caixa de spam.');
    }
}
