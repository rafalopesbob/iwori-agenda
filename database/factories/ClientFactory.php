<?php

namespace Database\Factories;

use App\Enums\BillingChannel;
use App\Enums\PaymentCycle;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(##) 9####-####'),
            'session_value' => fake()->randomFloat(2, 80, 400),
            'notes' => fake()->optional()->sentence(),
            'active' => true,
            'payment_cycle' => PaymentCycle::Monthly,
            'payment_day' => 5,
            'payment_interval_days' => null,
            'billing_channel' => BillingChannel::Email,
        ];
    }

    /**
     * Cliente desativado.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Cobrança semanal (por sessão).
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_cycle' => PaymentCycle::Weekly,
            'payment_day' => null,
            'payment_interval_days' => null,
        ]);
    }

    /**
     * Cobrança a cada X dias.
     */
    public function interval(int $days = 15): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_cycle' => PaymentCycle::Interval,
            'payment_day' => null,
            'payment_interval_days' => $days,
        ]);
    }

    /**
     * Cobrança via WhatsApp.
     */
    public function channelWhatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_channel' => BillingChannel::Whatsapp,
        ]);
    }

    /**
     * Cobrança por e-mail e WhatsApp.
     */
    public function channelBoth(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_channel' => BillingChannel::Both,
        ]);
    }
}
