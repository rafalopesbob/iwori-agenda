<?php

namespace App\Http\Controllers;

use App\Enums\BillingChannel;
use App\Jobs\SendClientCharge;
use App\Jobs\SendSessionCharge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Services\BillingService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;

class ChargeController extends Controller
{
    /**
     * Cobrança manual do período corrente do cliente (botão Cobrar do
     * Faturamento, depois da confirmação).
     *
     * O registro acontece na hora, para a mensagem dizer exatamente o que
     * foi cobrado; o e-mail em si segue pela fila, e a tela de Cobranças
     * mostra quando ele sai.
     */
    public function client(Client $client, BillingService $billing, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorize('update', $client);

        $preview = $billing->chargePreview($client, $whatsapp->isConfiguredFor($client->user));

        if ($preview['total'] <= 0) {
            return back()->with('status', sprintf(
                'Nada a cobrar de %s: não há sessões realizadas ou faltas sem aviso de %s a %s.',
                $client->name,
                $preview['start']->format('d/m/Y'),
                $preview['end']->format('d/m/Y'),
            ));
        }

        SendClientCharge::dispatchSync($client, $preview['start'], $preview['end']);

        return back()->with('status', sprintf(
            'Cobrança de %s registrada: R$ %s, de %s a %s. %s',
            $client->name,
            number_format($preview['total'], 2, ',', '.'),
            $preview['start']->format('d/m/Y'),
            $preview['end']->format('d/m/Y'),
            $this->deliveryText($client, $preview['delivery']),
        ));
    }

    /**
     * Cobrança/lembrete avulso de uma sessão.
     */
    public function session(ClientSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        SendSessionCharge::dispatch($session);

        return back()->with('status', 'Cobrança da sessão de '.$session->client->name.' enviada para processamento.');
    }

    /**
     * Por onde a cobrança saiu, em palavras.
     */
    protected function deliveryText(Client $client, ?BillingChannel $delivery): string
    {
        return match ($delivery) {
            BillingChannel::Email => 'O e-mail para '.$client->email.' entrou na fila de envio; em Cobranças você vê quando ele sair.',
            BillingChannel::Both => 'O e-mail para '.$client->email.' entrou na fila de envio e o WhatsApp automático foi disparado; em Cobranças você vê quando o e-mail sair.',
            BillingChannel::Whatsapp => 'A mensagem foi para o WhatsApp automático.',
            null => 'Nada foi enviado: o cliente não tem e-mail cadastrado. Use o botão WhatsApp ou cadastre um e-mail.',
        };
    }
}
