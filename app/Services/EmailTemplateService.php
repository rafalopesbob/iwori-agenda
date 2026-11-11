<?php

namespace App\Services;

use App\Enums\EmailTemplateType;
use App\Enums\MessageChannel;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\EmailTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Textos das mensagens aos clientes (e-mail e WhatsApp), com fallback
 * para o padrão do sistema quando o profissional não personalizou.
 */
class EmailTemplateService
{
    /**
     * Retorna assunto e corpo do e-mail do profissional,
     * com fallback para o padrão do sistema.
     *
     * @return array{subject: string, body: string}
     */
    public function resolve(User $user, EmailTemplateType $type): array
    {
        $template = $this->find($user, $type, MessageChannel::Email);

        return [
            'subject' => $template?->subject ?? $type->defaultSubject(),
            'body' => $template?->body ?? $type->defaultBody(),
        ];
    }

    /**
     * Texto do WhatsApp do profissional, com fallback para o padrão do sistema.
     */
    public function resolveWhatsapp(User $user, EmailTemplateType $type): string
    {
        return $this->find($user, $type, MessageChannel::Whatsapp)?->body
            ?? $type->defaultWhatsappBody();
    }

    /**
     * Substitui as variáveis {{...}} pelos valores informados.
     *
     * O texto nunca passa pelo compilador Blade: variáveis desconhecidas
     * ficam literais e o escaping de HTML acontece na view do e-mail.
     *
     * @param  array<string, string>  $vars
     */
    public function render(string $text, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $matches) => $vars[$matches[1]] ?? $matches[0],
            $text,
        );
    }

    /**
     * Resolve o e-mail do profissional e renderiza com as variáveis.
     *
     * @param  array<string, string>  $vars
     * @return array{subject: string, body: string}
     */
    public function renderFor(User $user, EmailTemplateType $type, array $vars): array
    {
        $template = $this->resolve($user, $type);

        return [
            'subject' => $this->render($template['subject'], $vars),
            'body' => $this->render($template['body'], $vars),
        ];
    }

    /**
     * Resolve o texto de WhatsApp do profissional e renderiza com as variáveis.
     *
     * @param  array<string, string>  $vars
     */
    public function renderWhatsappFor(User $user, EmailTemplateType $type, array $vars): string
    {
        return $this->render($this->resolveWhatsapp($user, $type), $vars);
    }

    /**
     * Variáveis de uma cobrança (período ou sessão avulsa).
     *
     * @param  Collection<int, ClientSession>  $sessions
     * @return array<string, string>
     */
    public function chargeVariables(Client $client, Collection $sessions, float $total, CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'cliente_nome' => $client->name,
            'profissional_nome' => $client->user->name,
            'valor' => 'R$ '.number_format($total, 2, ',', '.'),
            'periodo_inicio' => $start->format('d/m/Y'),
            'periodo_fim' => $end->format('d/m/Y'),
            'periodo' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
            'lista_sessoes' => $sessions
                ->map(fn (ClientSession $session) => sprintf(
                    '%s — %s — R$ %s',
                    $session->scheduled_at->format('d/m/Y H:i'),
                    $session->status->label(),
                    number_format((float) $session->value, 2, ',', '.'),
                ))
                ->implode("\n"),
        ];
    }

    /**
     * Variáveis da confirmação de uma sessão agendada.
     *
     * @return array<string, string>
     */
    public function sessionVariables(ClientSession $session): array
    {
        return [
            'cliente_nome' => $session->client->name,
            'profissional_nome' => $session->client->user->name,
            'data' => $session->scheduled_at->format('d/m/Y'),
            'hora' => $session->scheduled_at->format('H:i'),
            'duracao' => (string) $session->duration_minutes,
        ];
    }

    /**
     * Texto personalizado do profissional para o tipo e o canal, se houver.
     */
    protected function find(User $user, EmailTemplateType $type, MessageChannel $channel): ?EmailTemplate
    {
        return $user->emailTemplates()
            ->where('type', $type->value)
            ->where('channel', $channel->value)
            ->first();
    }
}
