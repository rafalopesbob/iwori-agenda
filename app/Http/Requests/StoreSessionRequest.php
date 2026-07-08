<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A regra do client_id garante que só é possível agendar para
     * clientes do próprio profissional autenticado.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recurrence' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            // Limite de 52 evita gerar uma quantidade excessiva de sessões de uma vez.
            'recurrence_count' => ['nullable', 'required_with:recurrence', 'integer', 'min:2', 'max:52'],
            'recurrence_custom_days' => ['nullable', 'required_if:recurrence,custom', 'integer', 'min:1', 'max:365'],
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
            'recurrence' => 'frequência de repetição',
            'recurrence_count' => 'quantidade de repetições',
            'recurrence_custom_days' => 'intervalo em dias',
        ];
    }
}
