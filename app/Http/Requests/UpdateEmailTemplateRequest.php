<?php

namespace App\Http\Requests;

use App\Enums\EmailTemplateType;
use App\Enums\MessageChannel;
use App\Http\Requests\Concerns\ValidatesTemplatePlaceholders;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    use ValidatesTemplatePlaceholders;

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
     * Tipo e canal não podem ser trocados na edição — ficam de fora das
     * regras e do formulário.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $channel = $this->route('email_template')->channel;

        return [
            'name' => ['required', 'string', 'max:100'],
            'subject' => $channel === MessageChannel::Whatsapp
                ? ['prohibited']
                : ['required', 'string', 'max:150', $this->validPlaceholders()],
            'body' => ['required', 'string', 'max:'.$channel->maxBodyLength(), $this->validPlaceholders()],
        ];
    }

    /**
     * Tipo da mensagem que está sendo editada.
     */
    protected function templateType(): ?EmailTemplateType
    {
        $type = $this->route('email_template')->type;

        return $type instanceof EmailTemplateType ? $type : null;
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
            'body' => 'texto',
        ];
    }
}
