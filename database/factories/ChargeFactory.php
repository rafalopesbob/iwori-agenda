<?php

namespace Database\Factories;

use App\Enums\BillingChannel;
use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $client = Client::factory();

        return [
            'user_id' => User::factory(),
            'client_id' => $client,
            'client_session_id' => null,
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 800),
            'status' => ChargeStatus::Pending,
            'channel' => BillingChannel::Email,
            'sent_at' => now(),
            'paid_at' => null,
            'receipt_path' => null,
            'notes' => null,
        ];
    }

    /**
     * Cobrança e cliente vinculados ao mesmo profissional.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'client_id' => Client::factory()->for($user),
        ]);
    }

    /**
     * Cobrança pendente.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Pending,
            'paid_at' => null,
        ]);
    }

    /**
     * Cobrança paga.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Cobrança avulsa vinculada a uma sessão.
     */
    public function forSession(ClientSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $session->client->user_id,
            'client_id' => $session->client_id,
            'client_session_id' => $session->id,
            'period_start' => $session->scheduled_at->toDateString(),
            'period_end' => $session->scheduled_at->toDateString(),
            'amount' => $session->value,
        ]);
    }
}
