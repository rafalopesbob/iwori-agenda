<?php

namespace App\Http\Requests;

use App\Enums\EmailTemplateType;
use App\Enums\MessageChannel;
use App\Http\Requests\Concerns\ValidatesTemplatePlaceholders;
use App\Models\EmailTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
{
    use ValidatesTemplatePlaceholders;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', EmailTemplate::class);
    }

    /**
     * Sem canal informado, o texto é de e-mail (formulários antigos).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('channel')) {
            $this->merge(['channel' => MessageChannel::Email->value]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $channel = MessageChannel::tryFrom((string) $this->input('channel'));

        return [
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'type' => [
                'required',
                Rule::enum(EmailTemplateType::class),
                Rule::unique('email_templates')
                    ->where('user_id', $this->user()->id)
                    ->where('channel', $this->input('channel')),
            ],
            'name' => ['required', 'string', 'max:100'],
            'subject' => $channel === MessageChannel::Whatsapp
                ? ['prohibited']
                : ['required', 'string', 'max:150', $this->validPlaceholders()],
            'body' => ['required', 'string', 'max:'.($channel ?? MessageChannel::Email)->maxBodyLength(), $this->validPlaceholders()],
        ];
    }

    /**
     * Tipo de mensagem escolhido no formulário.
     */
    protected function templateType(): ?EmailTemplateType
    {
        return EmailTemplateType::tryFrom((string) $this->input('type'));
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.unique' => 'Você já tem um texto personalizado deste tipo.',
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
            'channel' => 'canal',
            'type' => 'tipo',
            'name' => 'nome',
            'subject' => 'assunto',
            'body' => 'texto',
        ];
    }
}
