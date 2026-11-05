<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Tela que pede a confirmação do e-mail (destino do middleware 'verified').
     */
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.verify-email', ['user' => $request->user()]);
    }

    /**
     * Confirma o e-mail a partir do link assinado enviado por e-mail.
     *
     * O EmailVerificationRequest confere se o link é do usuário logado (id + hash do e-mail).
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->intended(route('dashboard'))
            ->with('status', 'E-mail confirmado. Os envios para os seus clientes estão liberados.');
    }

    /**
     * Reenvia o link de confirmação.
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Enviamos um novo link de confirmação para '.$request->user()->email.'.');
    }
}
