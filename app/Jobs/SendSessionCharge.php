<?php

namespace App\Jobs;

use App\Mail\ClientChargeMail;
use App\Models\ClientSession;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSessionCharge implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ClientSession $session,
    ) {}

    /**
     * Execute the job.
     *
     * Cobrança/lembrete avulso de uma única sessão, pelo canal do cliente.
     * Não altera last_charged_at — o ciclo segue normal.
     */
    public function handle(WhatsAppService $whatsapp): void
    {
        $session = $this->session;
        $client = $session->client;
        $channel = $client->billing_channel;

        if ($channel->sendsEmail() && $client->email) {
            Mail::to($client->email)->queue(new ClientChargeMail(
                $client,
                collect([$session]),
                (float) $session->value,
                $session->scheduled_at,
                $session->scheduled_at,
            ));
        }

        if ($channel->sendsWhatsapp() && $whatsapp->isConfigured() && ($to = $client->whatsappNumber())) {
            $whatsapp->sendTemplate($to, config('services.whatsapp.template_charge'), [
                $client->name,
                'R$ '.number_format((float) $session->value, 2, ',', '.'),
                $session->scheduled_at->format('d/m/Y \à\s H:i'),
            ]);
        }
    }
}
