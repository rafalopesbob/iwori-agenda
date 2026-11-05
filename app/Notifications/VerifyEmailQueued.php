<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Link de confirmação de e-mail enviado pela fila, como os demais e-mails do app:
 * uma falha no SMTP não derruba o cadastro nem a troca de e-mail.
 * O texto em português é definido em AppServiceProvider (VerifyEmail::toMailUsing).
 */
class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
