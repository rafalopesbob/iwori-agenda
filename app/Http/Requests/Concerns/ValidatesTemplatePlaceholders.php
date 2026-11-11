<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EmailTemplateType;
use Closure;

/**
 * Rejeita variáveis {{...}} que não existem para o tipo de mensagem.
 */
trait ValidatesTemplatePlaceholders
{
    /**
     * Tipo de mensagem cujas variáveis valem para o texto.
     */
    abstract protected function templateType(): ?EmailTemplateType;

    protected function validPlaceholders(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = $this->templateType();

            if ($type === null || ! is_string($value)) {
                return;
            }

            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $value, $matches);
            $unknown = array_diff(array_unique($matches[1]), array_keys($type->variables()));

            if ($unknown !== []) {
                $fail(sprintf(
                    'Variável desconhecida: %s. Variáveis disponíveis: %s.',
                    implode(', ', $unknown),
                    implode(', ', array_keys($type->variables())),
                ));
            }
        };
    }
}
