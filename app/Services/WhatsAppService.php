<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Envio de mensagens pela WhatsApp Cloud API (Meta), sempre a partir do
 * número do próprio profissional. Não existe número do sistema: cada um
 * conecta o seu WhatsApp na tela WhatsApp, e as credenciais ficam no
 * cadastro dele (token criptografado).
 */
class WhatsAppService
{
    protected const GRAPH_URL = 'https://graph.facebook.com';

    /**
     * Modelo de cobrança que o sistema cria e usa quando o profissional
     * não escolhe outro. Corpo com 3 variáveis: cliente, valor e período.
     */
    public const DEFAULT_TEMPLATE = 'cobranca_iwori';

    /**
     * Texto do modelo de cobrança enviado para aprovação da Meta.
     */
    public const DEFAULT_TEMPLATE_BODY = 'Olá, {{1}}! O valor das suas sessões é {{2}}, referente a {{3}}. '
        .'Se tiver alguma dúvida, é só responder esta mensagem.';

    /**
     * Envio automático liberado neste ambiente (desligado na demonstração).
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.whatsapp.enabled', true);
    }

    /**
     * O profissional conectou o WhatsApp dele e o envio está liberado?
     */
    public function isConfiguredFor(?User $user): bool
    {
        return $this->isEnabled() && $user !== null && $user->hasWhatsapp();
    }

    /**
     * Envia um modelo aprovado pela Meta do número do profissional para o
     * número do cliente (E.164, sem +).
     *
     * Mensagens fora da janela de 24h exigem modelo aprovado, por isso a
     * cobrança usa sempre modelo.
     *
     * @param  array<int, string|int|float>  $params  Parâmetros do corpo, na ordem do modelo.
     */
    public function sendTemplate(User $user, string $to, string $template, array $params = []): void
    {
        if (! $this->isConfiguredFor($user)) {
            return;
        }

        $this->client($user->whatsapp_token)
            ->post($this->url($user->whatsapp_phone_number_id.'/messages'), [
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

    /**
     * Confere na Meta o número e o token informados pelo profissional.
     *
     * @return array{display_phone: string, verified_name: ?string}
     *
     * @throws WhatsAppConnectionException quando a Meta recusa ou não responde.
     */
    public function verify(string $phoneNumberId, string $token): array
    {
        try {
            $data = $this->client($token)
                ->get($this->url($phoneNumberId), ['fields' => 'display_phone_number,verified_name'])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new WhatsAppConnectionException($this->metaError($e));
        } catch (ConnectionException) {
            throw new WhatsAppConnectionException('A Meta não respondeu. Tente de novo em alguns minutos.');
        }

        if (empty($data['display_phone_number'])) {
            throw new WhatsAppConnectionException('A Meta não reconheceu este ID de número.');
        }

        return [
            'display_phone' => (string) $data['display_phone_number'],
            'verified_name' => $data['verified_name'] ?? null,
        ];
    }

    /**
     * Situação do modelo de cobrança na conta do profissional: 'APPROVED',
     * 'PENDING', 'REJECTED' etc., 'missing' quando não existe, ou null
     * quando não deu para consultar.
     */
    public function templateStatus(User $user): ?string
    {
        if (! $this->isConfiguredFor($user) || blank($user->whatsapp_business_account_id)) {
            return null;
        }

        try {
            $templates = $this->client($user->whatsapp_token)
                ->get($this->url($user->whatsapp_business_account_id.'/message_templates'), [
                    'name' => $user->whatsappTemplate(),
                    'fields' => 'name,status,language',
                ])
                ->throw()
                ->json('data', []);
        } catch (RequestException|ConnectionException) {
            return null;
        }

        $template = collect($templates)
            ->first(fn (array $template) => ($template['name'] ?? null) === $user->whatsappTemplate()
                && ($template['language'] ?? 'pt_BR') === 'pt_BR');

        return $template['status'] ?? 'missing';
    }

    /**
     * Envia o modelo de cobrança padrão para aprovação na conta do profissional.
     *
     * @throws WhatsAppConnectionException quando a Meta recusa ou não responde.
     */
    public function createChargeTemplate(User $user): void
    {
        try {
            $this->client($user->whatsapp_token)
                ->post($this->url($user->whatsapp_business_account_id.'/message_templates'), [
                    'name' => self::DEFAULT_TEMPLATE,
                    'language' => 'pt_BR',
                    'category' => 'UTILITY',
                    'components' => [[
                        'type' => 'BODY',
                        'text' => self::DEFAULT_TEMPLATE_BODY,
                        'example' => ['body_text' => [['Ana', 'R$ 480,00', '01/07/2026 a 31/07/2026']]],
                    ]],
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new WhatsAppConnectionException($this->metaError($e));
        } catch (ConnectionException) {
            throw new WhatsAppConnectionException('A Meta não respondeu. Tente de novo em alguns minutos.');
        }
    }

    protected function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(15);
    }

    protected function url(string $path): string
    {
        return self::GRAPH_URL.'/'.config('services.whatsapp.api_version').'/'.$path;
    }

    /**
     * Mensagem de erro da Meta, para mostrar ao profissional.
     */
    protected function metaError(RequestException $e): string
    {
        $message = $e->response?->json('error.message');

        return $message
            ? 'A Meta recusou: '.$message
            : 'A Meta recusou a solicitação (erro '.$e->response?->status().').';
    }
}
