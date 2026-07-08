<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPaymentFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, string>
     */
    protected function basePayload(): array
    {
        return [
            'name' => 'Cliente Pagante',
            'session_value' => '150.00',
            'active' => '1',
            'billing_channel' => 'email',
        ];
    }

    public function test_ciclo_mensal_exige_dia_de_pagamento(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'monthly',
        ]))->assertSessionHasErrors('payment_day');
    }

    public function test_ciclo_por_intervalo_exige_o_intervalo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'interval',
        ]))->assertSessionHasErrors('payment_interval_days');
    }

    public function test_ciclo_semanal_nao_exige_campos_extras(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'weekly',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'name' => 'Cliente Pagante',
            'payment_cycle' => 'weekly',
        ]);
    }

    public function test_cadastro_persiste_ciclo_dia_e_canal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'monthly',
            'payment_day' => '5',
            'billing_channel' => 'both',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'name' => 'Cliente Pagante',
            'payment_cycle' => 'monthly',
            'payment_day' => 5,
            'billing_channel' => 'both',
        ]);
    }

    public function test_canal_invalido_e_rejeitado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'weekly',
            'billing_channel' => 'pombo-correio',
        ]))->assertSessionHasErrors('billing_channel');
    }

    public function test_intervalo_persiste_a_cada_15_dias(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'interval',
            'payment_interval_days' => '15',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'name' => 'Cliente Pagante',
            'payment_cycle' => 'interval',
            'payment_interval_days' => 15,
        ]);
    }
}
