<?php

namespace App\Http\Controllers;

use App\Enums\BillingChannel;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Services\BillingService;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\RedirectResponse;

/**
 * Botões "WhatsApp": abrem o WhatsApp do profissional com a mensagem
 * pronta (link wa.me). O sistema não envia nada; o profissional confere
 * o texto e toca em enviar.
 */
class WhatsAppMessageController extends Controller
{
    public function __construct(
        private WhatsAppLinkService $links,
    ) {}

    /**
     * Cobra o período corrente do cliente pelo WhatsApp: registra a
     * cobrança (como o botão Cobrar) e abre a conversa com o texto.
     */
    public function chargeClient(Client $client, BillingService $billing): RedirectResponse
    {
        $this->authorize('update', $client);

        if (! $number = $client->whatsappNumber()) {
            return $this->missingNumber($client);
        }

        ['start' => $start, 'end' => $end] = $client->currentChargePeriod();
        $period = $billing->periodCharge($client, $start, $end);

        if ($period['total'] <= 0) {
            return back()->with('status', 'Nada a cobrar de '.$client->name.' neste período.');
        }

        $charge = $billing->recordPeriodCharge($client, $start, $end, $period['total'], BillingChannel::Whatsapp);

        return redirect()->away($this->links->url($number, $this->links->chargeMessage($charge)));
    }

    /**
     * Cobra uma sessão avulsa pelo WhatsApp.
     */
    public function chargeSession(ClientSession $session, BillingService $billing): RedirectResponse
    {
        $this->authorize('update', $session);

        $client = $session->client;

        if (! $number = $client->whatsappNumber()) {
            return $this->missingNumber($client);
        }

        $charge = $billing->recordSessionCharge($session, BillingChannel::Whatsapp);

        return redirect()->away($this->links->url($number, $this->links->chargeMessage($charge)));
    }

    /**
     * Reenvia (ou lembra) uma cobrança já registrada. Não altera nada.
     */
    public function charge(Charge $charge): RedirectResponse
    {
        $this->authorize('view', $charge);

        if (! $number = $charge->client?->whatsappNumber()) {
            return $this->missingNumber($charge->client);
        }

        return redirect()->away($this->links->url($number, $this->links->chargeMessage($charge)));
    }

    /**
     * Confirmação de uma sessão agendada. Não altera nada.
     */
    public function session(ClientSession $session): RedirectResponse
    {
        $this->authorize('view', $session);

        if (! $number = $session->client->whatsappNumber()) {
            return $this->missingNumber($session->client);
        }

        return redirect()->away($this->links->url($number, $this->links->sessionMessage($session)));
    }

    /**
     * Cliente sem celular válido: avisa em vez de abrir uma conversa vazia.
     */
    protected function missingNumber(?Client $client): RedirectResponse
    {
        return back()->with('status', 'Cadastre o celular de '.($client?->name ?? 'este cliente').' para enviar pelo WhatsApp.');
    }
}
