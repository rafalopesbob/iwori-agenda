<?php

namespace App\Http\Requests;

use App\Enums\EmailTemplateType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('email_template'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * O tipo não pode ser trocado na edição — fica de fora das regras
     * e do formulário.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:150', $this->validPlaceholders()],
            'body' => ['required', 'string', 'max:5000', $this->validPlaceholders()],
        ];
    }

    /**
     * Rejeita variáveis {{...}} que não existem para o tipo do template.
     */
    protected function validPlaceholders(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = $this->route('email_template')->type;

            if (! $type instanceof EmailTemplateType || ! is_string($value)) {
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

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'subject' => 'assunto',
            'body' => 'corpo',
        ];
    }
}
