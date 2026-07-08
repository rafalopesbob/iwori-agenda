<?php

namespace App\Jobs;

use App\Mail\ClientChargeMail;
use App\Models\Client;
use App\Services\BillingService;
use App\Services\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendClientCharge implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * Sem datas explícitas, usa o período corrente do ciclo do cliente.
     */
    public function __construct(
        public Client $client,
        public ?CarbonImmutable $start = null,
        public ?CarbonImmutable $end = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BillingService $billing, WhatsAppService $whatsapp): void
    {
        $client = $this->client;

        ['start' => $start, 'end' => $end] = ($this->start && $this->end)
            ? ['start' => $this->start, 'end' => $this->end]
            : $client->currentChargePeriod();

        $charge = $billing->periodCharge($client, $start, $end);

        if ($charge['total'] > 0) {
            $channel = $client->billing_channel;

            if ($channel->sendsEmail() && $client->email) {
                Mail::to($client->email)->queue(
                    new ClientChargeMail($client, $charge['sessions'], $charge['total'], $start, $end),
                );
            }

            if ($channel->sendsWhatsapp() && $whatsapp->isConfigured() && ($to = $client->whatsappNumber())) {
                $whatsapp->sendTemplate($to, config('services.whatsapp.template_charge'), [
                    $client->name,
                    'R$ '.number_format($charge['total'], 2, ',', '.'),
                    $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
                ]);
            }
        }

        // Fecha o período mesmo sem valor, para o ciclo seguinte partir daqui.
        $client->forceFill(['last_charged_at' => now()])->save();
    }
}
