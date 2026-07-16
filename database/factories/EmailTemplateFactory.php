<?php

namespace Database\Factories;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => EmailTemplateType::Charge,
            'name' => 'Minha cobrança',
            'subject' => EmailTemplateType::Charge->defaultSubject(),
            'body' => EmailTemplateType::Charge->defaultBody(),
        ];
    }

    /**
     * Template de cobrança de período.
     */
    public function charge(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EmailTemplateType::Charge,
        ]);
    }

    /**
     * Template de confirmação de agendamento.
     */
    public function sessionScheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EmailTemplateType::SessionScheduled,
            'name' => 'Minha confirmação',
            'subject' => EmailTemplateType::SessionScheduled->defaultSubject(),
            'body' => EmailTemplateType::SessionScheduled->defaultBody(),
        ]);
    }
}
