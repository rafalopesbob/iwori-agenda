<?php

namespace App\Http\Requests;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', EmailTemplate::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::enum(EmailTemplateType::class),
                Rule::unique('email_templates')->where('user_id', $this->user()->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:150', $this->validPlaceholders()],
            'body' => ['required', 'string', 'max:5000', $this->validPlaceholders()],
        ];
    }

    /**
     * Rejeita variáveis {{...}} que não existem para o tipo escolhido.
     */
    protected function validPlaceholders(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = EmailTemplateType::tryFrom((string) $this->input('type'));

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

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.unique' => 'Você já tem um template personalizado deste tipo.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'name' => 'nome',
            'subject' => 'assunto',
            'body' => 'corpo',
        ];
    }
}
