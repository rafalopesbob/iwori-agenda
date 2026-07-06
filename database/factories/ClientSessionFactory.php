<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Client;
use App\Models\ClientSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientSession>
 */
class ClientSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'duration_minutes' => 50,
            'status' => SessionStatus::Scheduled,
            'value' => fake()->randomFloat(2, 80, 400),
            'notes' => null,
        ];
    }

    /**
     * Sessão realizada (entra no faturamento).
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Completed,
            'scheduled_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Cliente faltou.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::NoShow,
            'scheduled_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Sessão cancelada.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Canceled,
        ]);
    }
}
