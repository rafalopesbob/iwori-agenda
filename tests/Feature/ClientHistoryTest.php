<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Enums\SessionStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Histórico na ficha do cliente: resumo, mês a mês, sessões e cobranças.
 */
class ClientHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
    }

    /**
     * Cliente com sessões de julho e agosto e três cobranças.
     *
     * @return array{0: User, 1: Client}
     */
    protected function clientWithHistory(): array
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Ana Beatriz']);

        $session = fn (string $at, SessionStatus $status, float $value = 100) => ClientSession::factory()->for($client)->create([
            'scheduled_at' => $at,
            'status' => $status,
            'value' => $value,
        ]);

        $session('2026-07-06 14:00', SessionStatus::Completed);
        $session('2026-07-13 14:00', SessionStatus::NoShowUnexcused);
        $session('2026-07-20 14:00', SessionStatus::NoShowExcused);
        $session('2026-08-03 14:00', SessionStatus::Completed, 120);
        $session('2026-08-05 14:00', SessionStatus::Canceled);
        $session('2026-08-17 14:00', SessionStatus::Scheduled);
        $session('2026-08-24 14:00', SessionStatus::Scheduled);

        Charge::factory()->for($user)->for($client)->paid()->create(['amount' => 200, 'sent_at' => '2026-07-31 09:00']);
        Charge::factory()->for($user)->for($client)->create(['amount' => 120, 'status' => ChargeStatus::Pending, 'sent_at' => '2026-08-08 09:00']);
        Charge::factory()->for($user)->for($client)->create(['amount' => 50, 'status' => ChargeStatus::Pending, 'sent_at' => '2026-07-20 09:00']);

        // Outro cliente do mesmo profissional não entra no histórico.
        $outro = Client::factory()->for($user)->create();
        ClientSession::factory()->for($outro)->completed()->create(['scheduled_at' => '2026-08-04 10:00', 'value' => 999]);
        Charge::factory()->for($user)->for($outro)->create(['amount' => 999]);

        return [$user, $client];
    }

    public function test_resumo_do_cliente(): void
    {
        [$user, $client] = $this->clientWithHistory();

        $response = $this->actingAs($user)->get(route('clients.show', $client))->assertOk();

        $response->assertViewHas('summary', function (array $summary) {
            return $summary['completed'] === 2
                && $summary['no_show'] === 1
                && $summary['no_show_excused'] === 1
                && $summary['canceled'] === 1
                && $summary['billed'] === 320.0 // 100 + 100 (falta sem aviso) + 120
                && $summary['received'] === 200.0
                && $summary['pending'] === 170.0
                && $summary['overdue'] === 1
                && $summary['next']->scheduled_at->format('Y-m-d H:i') === '2026-08-17 14:00'
                && $summary['first']->format('Y-m-d') === '2026-07-06';
        });

        $response->assertSee('Desde a primeira sessão, em 06/07/2026.')
            ->assertSee('R$ 320,00')
            ->assertSee('1 cobrança atrasada')
            ->assertDontSee('Em breve');
    }

    public function test_mes_a_mes(): void
    {
        [$user, $client] = $this->clientWithHistory();

        $months = $this->actingAs($user)->get(route('clients.show', $client))->viewData('months');

        $this->assertSame(['2026-08', '2026-07'], $months->map(fn (array $row) => $row['month']->format('Y-m'))->all());
        $this->assertSame(120.0, $months[0]['billed']);
        $this->assertSame(2, $months[0]['scheduled']);
        $this->assertSame(1, $months[0]['canceled']);
        $this->assertSame(200.0, $months[1]['billed']);
        $this->assertSame(1, $months[1]['no_show_excused']);
    }

    public function test_lista_de_sessoes_com_filtro_por_situacao(): void
    {
        [$user, $client] = $this->clientWithHistory();

        $dates = fn (array $query = []) => collect(
            $this->actingAs($user)->get(route('clients.show', ['client' => $client] + $query))->viewData('sessions')->items()
        )->map(fn (ClientSession $session) => $session->scheduled_at->format('d/m'))->all();

        // Mais recentes primeiro.
        $this->assertSame(['24/08', '17/08', '05/08', '03/08', '20/07', '13/07', '06/07'], $dates());
        $this->assertSame(['03/08', '06/07'], $dates(['situacao' => 'completed']));
        $this->assertSame(['13/07'], $dates(['situacao' => 'no_show']));
        $this->assertCount(7, $dates(['situacao' => 'bobagem']));
    }

    public function test_lista_de_cobrancas_so_do_cliente(): void
    {
        [$user, $client] = $this->clientWithHistory();

        $charges = $this->actingAs($user)->get(route('clients.show', $client))->viewData('charges');

        // Mais recentes primeiro: 08/08, 31/07 e 20/07.
        $this->assertSame([120.0, 200.0, 50.0], collect($charges->items())->map(fn (Charge $charge) => (float) $charge->amount)->all());
    }

    public function test_paginas_das_duas_listas_sao_independentes(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();
        ClientSession::factory()->for($client)->completed()->count(12)->create();
        Charge::factory()->for($user)->for($client)->count(12)->create();

        $response = $this->actingAs($user)->get(route('clients.show', ['client' => $client, 'sessoes' => 2]))->assertOk();

        $this->assertCount(2, $response->viewData('sessions')->items());
        $this->assertCount(10, $response->viewData('charges')->items());
        $response->assertSee('cobrancas=2', false)->assertSee('#cobrancas', false);
    }

    public function test_cliente_sem_historico(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Ainda não há sessões com este cliente.')
            ->assertSee('Nenhuma agendada')
            ->assertSee('Nenhuma sessão ainda.')
            ->assertSee('Nenhuma cobrança registrada para este cliente.')
            ->assertDontSee('Mês a mês');
    }
}
