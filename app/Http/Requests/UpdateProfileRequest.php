<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id),
                // Conta criada pelo Google ainda sem senha: não há senha para confirmar a
                // troca, então ela só é liberada depois que a pessoa define uma.
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value !== $this->user()->email && ! $this->user()->hasPassword()) {
                        $fail('Para trocar o e-mail da conta, defina uma senha antes.');
                    }
                },
            ],
            // Trocar o e-mail da conta exige a senha atual: quem estiver com uma sessão
            // aberta (ex.: computador compartilhado) não consegue tomar a conta.
            'email_current_password' => [
                Rule::requiredIf(fn () => $this->input('email') !== $this->user()->email && $this->user()->hasPassword()),
                'nullable',
                'string',
                'current_password',
            ],
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
            'name' => 'nome',
            'email' => 'e-mail',
            'email_current_password' => 'senha atual',
        ];
    }
}
