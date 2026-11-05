<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia ações que não fazem sentido (ou seriam abuso) no ambiente de
 * demonstração, onde todos os visitantes compartilham a mesma conta:
 * criar contas, trocar senha/e-mail/foto, conectar o Google.
 */
class DisabledInDemo
{
    public const MESSAGE = 'Esta ação fica desativada no ambiente de demonstração.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, self::MESSAGE);
        }

        // GET (ex.: tela de cadastro) não tem para onde "voltar": manda para o login.
        $target = $request->isMethod('GET')
            ? redirect()->route($request->user() ? 'dashboard' : 'login')
            : back();

        return $target->with('status', self::MESSAGE);
    }
}
