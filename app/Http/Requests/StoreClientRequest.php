<?php

namespace App\Http\Requests;

use App\Enums\BillingChannel;
use App\Enums\PaymentCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Client::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'session_value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['boolean'],
            'payment_cycle' => ['required', Rule::enum(PaymentCycle::class)],
            'payment_day' => ['nullable', 'required_if:payment_cycle,monthly', 'integer', 'min:1', 'max:31'],
            'payment_interval_days' => ['nullable', 'required_if:payment_cycle,interval', 'integer', 'min:1', 'max:365'],
            'billing_channel' => ['required', Rule::enum(BillingChannel::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active', true),
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_cycle' => 'ciclo de pagamento',
            'payment_day' => 'dia de pagamento',
            'payment_interval_days' => 'intervalo em dias',
            'billing_channel' => 'canal de cobrança',
        ];
    }
}
