<?php

namespace App\Services;

use App\Enums\EmailTemplateType;
use App\Models\Charge;
use App\Models\ClientSession;

/**
 * Mensagens de WhatsApp enviadas pelo próprio profissional: o link wa.me
 * abre o WhatsApp dele (celular ou computador) com o texto pronto, na
 * conversa com o cliente. Não usa credenciais da Meta e não custa nada;
 * quem envia de fato é o profissional, com um toque.
 */
class WhatsAppLinkService
{
    public function __construct(
        private EmailTemplateService $templates,
        private BillingService $billing,
    ) {}

    /**
     * Link que abre a conversa com o número (E.164, sem +) e o texto pronto.
     */
    public function url(string $number, string $text): string
    {
        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    /**
     * Texto da cobrança registrada, com o modelo de WhatsApp do profissional.
     */
    public function chargeMessage(Charge $charge): string
    {
        $client = $charge->client;

        $sessions = $charge->client_session_id
            ? collect([$charge->session])->filter()
            : $this->billing->periodCharge(
                $client,
                $charge->period_start->startOfDay(),
                $charge->period_end->endOfDay(),
            )['sessions'];

        return $this->templates->renderWhatsappFor(
            $client->user,
            EmailTemplateType::Charge,
            $this->templates->chargeVariables(
                $client,
                $sessions,
                (float) $charge->amount,
                $charge->period_start,
                $charge->period_end,
            ),
        );
    }

    /**
     * Texto de confirmação da sessão, com o modelo de WhatsApp do profissional.
     */
    public function sessionMessage(ClientSession $session): string
    {
        return $this->templates->renderWhatsappFor(
            $session->client->user,
            EmailTemplateType::SessionScheduled,
            $this->templates->sessionVariables($session),
        );
    }
}
