<?php

namespace App\Services;

use App\Models\Charge;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;

/**
 * Acompanha o e-mail de cobrança: fila, envio e falha. É o que permite
 * mostrar ao profissional se o e-mail saiu de fato e contar os enviados.
 *
 * Registrado em AppServiceProvider (fora de app/Listeners de propósito,
 * para a descoberta automática de eventos não registrar duas vezes).
 */
class ChargeEmailTracker
{
    /**
     * O e-mail da cobrança entrou na fila.
     */
    public function queued(Charge $charge): void
    {
        $charge->forceFill([
            'email_queued_at' => now(),
            'email_failed_at' => null,
        ])->save();
    }

    /**
     * Evento MessageSent: o e-mail foi entregue ao servidor de e-mail.
     * Só reage a e-mails que carregam o id de uma cobrança.
     */
    public function sent(MessageSent $event): void
    {
        $chargeId = $event->data['chargeId'] ?? null;

        if (! $chargeId) {
            return;
        }

        Charge::query()->whereKey($chargeId)->update([
            'emails_sent' => DB::raw('emails_sent + 1'),
            'email_sent_at' => now(),
            'email_failed_at' => null,
        ]);
    }

    /**
     * Todas as tentativas de envio falharam.
     */
    public function failed(int $chargeId): void
    {
        Charge::query()->whereKey($chargeId)->update(['email_failed_at' => now()]);
    }
}
