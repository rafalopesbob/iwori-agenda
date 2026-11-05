<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Link de redefinição de senha enviado pela fila.
 *
 * Gerado no worker, o link usa sempre o APP_URL (e não o Host da requisição),
 * o que impede envenenar o link com um header Host/X-Forwarded-Host forjado.
 * O texto em português é definido em AppServiceProvider (ResetPassword::toMailUsing).
 */
class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
