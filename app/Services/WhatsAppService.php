<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Envio de mensagens via WhatsApp Cloud API (Meta).
 *
 * Fica dormente até WHATSAPP_TOKEN e WHATSAPP_PHONE_ID serem
 * configurados — mesmo padrão do GoogleCalendarService.
 */
class WhatsAppService
{
    protected const GRAPH_URL = 'https://graph.facebook.com/v21.0';

    /**
     * Credenciais da Cloud API configuradas no ambiente?
     */
    public function isConfigured(): bool
    {
        return (bool) config('services.whatsapp.token')
            && (bool) config('services.whatsapp.phone_number_id');
    }

    /**
     * Envia um template aprovado pela Meta para o número (E.164, sem +).
     *
     * Mensagens fora da janela de 24h exigem template aprovado, por isso
     * a cobrança usa sempre template.
     *
     * @param array<int, string|int|float> $params Parâmetros do corpo, na ordem do template.
     */
    public function sendTemplate(string $to, string $template, array $params = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        Http::withToken(config('services.whatsapp.token'))
            ->post(self::GRAPH_URL.'/'.config('services.whatsapp.phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'pt_BR'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => array_map(
                                fn ($param) => ['type' => 'text', 'text' => (string) $param],
                                array_values($params),
                            ),
                        ],
                    ],
                ],
            ])
            ->throw();
    }
}
