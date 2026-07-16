<?php

namespace App\Services;

use App\Enums\EmailTemplateType;
use App\Models\User;

class EmailTemplateService
{
    /**
     * Retorna assunto e corpo do template do profissional,
     * com fallback para o padrão do sistema.
     *
     * @return array{subject: string, body: string}
     */
    public function resolve(User $user, EmailTemplateType $type): array
    {
        $template = $user->emailTemplates()->where('type', $type->value)->first();

        return [
            'subject' => $template?->subject ?? $type->defaultSubject(),
            'body' => $template?->body ?? $type->defaultBody(),
        ];
    }

    /**
     * Substitui as variáveis {{...}} pelos valores informados.
     *
     * O texto nunca passa pelo compilador Blade: variáveis desconhecidas
     * ficam literais e o escaping de HTML acontece na view do e-mail.
     *
     * @param array<string, string> $vars
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
     * Resolve o template do profissional e renderiza com as variáveis.
     *
     * @param array<string, string> $vars
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
}
