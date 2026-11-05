<?php

namespace Tests\Feature;

use App\Models\Client;
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
            'phone' => '(11) 98765-4321',
            'whatsapp_opt_in' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'name' => 'Cliente Pagante',
            'payment_cycle' => 'monthly',
            'payment_day' => 5,
            'billing_channel' => 'both',
        ]);
        $this->assertNotNull(Client::firstWhere('name', 'Cliente Pagante')->whatsapp_opt_in_at);
    }

    public function test_canal_whatsapp_exige_celular_valido(): void
    {
        $user = User::factory()->create();
        $payload = array_merge($this->basePayload(), [
            'payment_cycle' => 'weekly',
            'billing_channel' => 'whatsapp',
            'whatsapp_opt_in' => '1',
        ]);

        $this->actingAs($user)->post('/clients', $payload)->assertSessionHasErrors('phone');
        $this->actingAs($user)->post('/clients', array_merge($payload, ['phone' => '(11) 3333-4444']))
            ->assertSessionHasErrors('phone'); // fixo
        $this->actingAs($user)->post('/clients', array_merge($payload, ['phone' => '(01) 98765-4321']))
            ->assertSessionHasErrors('phone'); // DDD inexistente
        $this->actingAs($user)->post('/clients', array_merge($payload, ['phone' => '+55 (11) 98765-4321']))
            ->assertSessionHasNoErrors();
    }

    public function test_canal_whatsapp_exige_autorizacao_do_cliente(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'weekly',
            'billing_channel' => 'whatsapp',
            'phone' => '(11) 98765-4321',
        ]))->assertSessionHasErrors('whatsapp_opt_in');
    }

    public function test_canal_email_aceita_telefone_fixo_sem_autorizacao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/clients', array_merge($this->basePayload(), [
            'payment_cycle' => 'weekly',
            'phone' => '(11) 3333-4444',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Client::firstWhere('name', 'Cliente Pagante')->whatsapp_opt_in_at);
    }

    public function test_autorizacao_preserva_a_data_e_e_removida_ao_desmarcar(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->channelWhatsapp()->create([
            'phone' => '(11) 98765-4321',
            'whatsapp_opt_in_at' => '2026-10-01 10:00:00',
        ]);
        $payload = [
            'name' => $client->name,
            'session_value' => '150.00',
            'active' => '1',
            'payment_cycle' => 'monthly',
            'payment_day' => '5',
            'phone' => '(11) 98765-4321',
        ];

        $this->actingAs($user)->put("/clients/{$client->id}", array_merge($payload, [
            'billing_channel' => 'whatsapp',
            'whatsapp_opt_in' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-01', $client->fresh()->whatsapp_opt_in_at->format('Y-m-d'));

        $this->actingAs($user)->put("/clients/{$client->id}", array_merge($payload, [
            'billing_channel' => 'email',
            'whatsapp_opt_in' => '0',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($client->fresh()->whatsapp_opt_in_at);
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
