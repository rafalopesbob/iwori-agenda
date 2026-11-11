<?php

namespace Tests\Feature;

use App\Enums\BillingChannel;
use App\Enums\ChargeStatus;
use App\Mail\ClientChargeMail;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Confirmação do Cobrar, acompanhamento do e-mail de cobrança (enviado,
 * na fila, falhou), contador de e-mails e filtros de Clientes, Faturamento
 * e Cobranças.
 */
class BillingFiltersAndEmailTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
    }

    /**
     * Cliente com uma sessão realizada a cobrar em agosto.
     */
    protected function clientToCharge(User $user, array $attributes = []): Client
    {
        $client = Client::factory()->for($user)->create(array_merge(['email' => 'ana@cliente.test'], $attributes));
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-08-03 14:00:00',
            'value' => 150,
        ]);

        return $client;
    }

    // ── Confirmação do Cobrar ────────────────────────────────────────

    public function test_faturamento_mostra_a_confirmacao_com_periodo_valor_e_destino(): void
    {
        $user = User::factory()->create();
        $client = $this->clientToCharge($user, ['name' => 'Ana Beatriz']);
        $semNada = Client::factory()->for($user)->create(['name' => 'Bruno', 'last_charged_at' => '2026-08-09 09:00:00']);
        ClientSession::factory()->for($semNada)->completed()->create(['scheduled_at' => '2026-08-02 10:00:00']);

        $this->actingAs($user)->get(route('billing.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('Cobrar Ana Beatriz?')
            ->assertSee('E-mail para ana@cliente.test')
            ->assertSee('R$ 150,00')
            ->assertSee('Enviar cobrança')
            ->assertSee('Cobrar Bruno?')
            ->assertSee('Nada a cobrar agora');
    }

    public function test_cobrar_registra_na_hora_e_diz_para_onde_foi_o_e_mail(): void
    {
        $user = User::factory()->create();
        $client = $this->clientToCharge($user, ['name' => 'Ana']);

        $this->actingAs($user)->from(route('billing.index'))->post(route('billing.charge', $client))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Cobrança de Ana registrada: R$ 150,00')
                && str_contains($status, 'O e-mail para ana@cliente.test entrou na fila de envio'));

        // Fila síncrona e mailer "array" nos testes: o e-mail já saiu.
        $charge = Charge::sole();
        $this->assertSame('sent', $charge->emailStatus());
        $this->assertSame(1, $charge->emails_sent);
        $this->assertNotNull($charge->email_sent_at);
    }

    public function test_cobrar_sem_valor_avisa_e_nao_fecha_o_ciclo(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['name' => 'Bruno']);

        $this->actingAs($user)->from(route('billing.index'))->post(route('billing.charge', $client))
            ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Nada a cobrar de Bruno'));

        $this->assertDatabaseCount('charges', 0);
        $this->assertNull($client->fresh()->last_charged_at);
    }

    public function test_cobrar_cliente_sem_e_mail_avisa_que_nada_foi_enviado(): void
    {
        $user = User::factory()->create();
        $client = $this->clientToCharge($user, ['email' => null]);

        $this->actingAs($user)->post(route('billing.charge', $client))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Nada foi enviado: o cliente não tem e-mail cadastrado'));

        $this->assertNull(Charge::sole()->emailStatus());
    }

    // ── Situação do e-mail e contador ────────────────────────────────

    public function test_reenvio_soma_no_contador_e_falha_fica_marcada(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->completed()->create(['scheduled_at' => '2026-08-03 14:00:00', 'value' => 90]);

        $this->actingAs($user)->post(route('sessions.charge', $session));
        $this->actingAs($user)->post(route('sessions.charge', $session));

        $charge = Charge::sole();
        $this->assertSame(2, $charge->emails_sent);
        $this->assertSame('sent', $charge->emailStatus());

        // Todas as tentativas falharam: a fila chama failed() no e-mail.
        $charge->forceFill(['email_queued_at' => now()->addMinute()])->save();
        $this->assertSame('queued', $charge->fresh()->emailStatus());

        (new ClientChargeMail($client, collect([$session]), 90.0, $session->scheduled_at, $session->scheduled_at, $charge->id))
            ->failed(new RuntimeException('SMTP fora do ar'));
        $this->assertSame('failed', $charge->fresh()->emailStatus());
    }

    public function test_contador_de_e_mails_aparece_no_faturamento_e_em_cobrancas(): void
    {
        $user = User::factory()->create();
        Charge::factory()->forUser($user)->create(['emails_sent' => 2, 'email_queued_at' => '2026-08-05 09:00', 'email_sent_at' => '2026-08-05 09:01']);
        Charge::factory()->forUser($user)->create(['emails_sent' => 1, 'email_queued_at' => '2026-08-06 09:00', 'email_sent_at' => '2026-08-06 09:01']);
        // Julho e de outro profissional não entram.
        Charge::factory()->forUser($user)->create(['emails_sent' => 5, 'email_queued_at' => '2026-07-06 09:00', 'email_sent_at' => '2026-07-06 09:01']);
        Charge::factory()->create(['emails_sent' => 7, 'email_queued_at' => '2026-08-06 09:00', 'email_sent_at' => '2026-08-06 09:01']);

        $this->actingAs($user)->get(route('billing.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSeeInOrder(['E-mails de cobrança enviados', '3']);

        $this->actingAs($user)->get(route('charges.index'))
            ->assertOk()
            ->assertSeeInOrder(['E-mails de cobrança enviados', '3', 'agosto de 2026']);

        $this->actingAs($user)->get(route('charges.index', ['month' => '2026-07']))
            ->assertSeeInOrder(['E-mails de cobrança enviados', '5', 'julho de 2026']);
    }

    // ── Filtros ──────────────────────────────────────────────────────

    public function test_filtros_de_clientes(): void
    {
        $user = User::factory()->create();
        Client::factory()->for($user)->create(['name' => 'Ana Beatriz', 'email' => 'ana@x.test', 'phone' => '(11) 98765-4321']);
        Client::factory()->for($user)->inactive()->create(['name' => 'Bruno Lima', 'email' => 'bruno@x.test', 'phone' => '(21) 91234-5678']);
        Client::factory()->for($user)->channelWhatsapp()->create(['name' => 'Carla Dias', 'email' => 'carla@x.test', 'phone' => null]);
        Client::factory()->create(['name' => 'Ana de outro profissional']);

        $names = fn (array $query) => collect($this->actingAs($user)->get(route('clients.index', $query))->viewData('clients')->items())
            ->pluck('name')->all();

        $this->assertSame(['Ana Beatriz'], $names(['q' => 'ana']));
        $this->assertSame(['Bruno Lima'], $names(['q' => 'bruno@']));
        $this->assertSame(['Ana Beatriz'], $names(['q' => '98765-4321']));
        $this->assertSame(['Ana Beatriz'], $names(['q' => '11987654321']));
        $this->assertSame(['Bruno Lima'], $names(['situation' => 'inactive']));
        $this->assertSame(['Carla Dias'], $names(['channel' => 'whatsapp']));
        $this->assertSame(['Ana Beatriz', 'Carla Dias'], $names(['situation' => 'active']));
        $this->assertSame([], $names(['q' => 'zzz']));

        $this->actingAs($user)->get(route('clients.index', ['q' => 'zzz']))
            ->assertSee('Nenhum cliente encontrado com esses filtros.');
    }

    public function test_filtros_do_faturamento_recalculam_os_totais(): void
    {
        $user = User::factory()->create();
        $this->clientToCharge($user, ['name' => 'Ana']);
        $bruno = Client::factory()->for($user)->create(['name' => 'Bruno', 'last_charged_at' => '2026-08-09 09:00:00']);
        ClientSession::factory()->for($bruno)->noShow()->create(['scheduled_at' => '2026-08-04 10:00:00', 'value' => 200]);

        $rows = fn (array $query) => $this->actingAs($user)->get(route('billing.index', ['month' => '2026-08'] + $query))->viewData('rows')
            ->map(fn (array $row) => $row['client']->name)->all();

        $this->assertSame(['Ana', 'Bruno'], $rows([]));
        $this->assertSame(['Bruno'], $rows(['q' => 'BRU']));
        $this->assertSame(['Ana'], $rows(['show' => 'to_charge']));
        $this->assertSame(['Bruno'], $rows(['show' => 'no_show']));

        $this->actingAs($user)->get(route('billing.index', ['month' => '2026-08', 'q' => 'ana']))
            ->assertViewHas('totals', fn (array $totals) => $totals['total'] === 150.0)
            ->assertSee('Faturamento do mês (filtro)');
    }

    public function test_filtros_de_cobrancas(): void
    {
        $user = User::factory()->create();
        $recente = Charge::factory()->forUser($user)->create(['sent_at' => '2026-08-08 10:00', 'status' => ChargeStatus::Pending]);
        $atrasada = Charge::factory()->forUser($user)->create(['sent_at' => '2026-07-20 10:00', 'status' => ChargeStatus::Pending]);
        $paga = Charge::factory()->forUser($user)->paid()->create(['sent_at' => '2026-07-10 10:00']);
        $falhou = Charge::factory()->forUser($user)->create([
            'sent_at' => '2026-08-09 10:00', 'email_queued_at' => '2026-08-09 10:00', 'email_failed_at' => '2026-08-09 10:05',
        ]);
        $naFila = Charge::factory()->forUser($user)->create(['sent_at' => '2026-08-09 11:00', 'email_queued_at' => '2026-08-09 11:00']);
        $enviada = Charge::factory()->forUser($user)->create([
            'sent_at' => '2026-08-09 12:00', 'email_queued_at' => '2026-08-09 12:00', 'email_sent_at' => '2026-08-09 12:01', 'emails_sent' => 1,
            'channel' => BillingChannel::Email,
        ]);

        $ids = fn (array $query) => collect($this->actingAs($user)->get(route('charges.index', $query))->viewData('charges')->items())
            ->pluck('id')->sort()->values()->all();

        $this->assertSame([$atrasada->id], $ids(['status' => 'overdue']));
        $this->assertSame([$paga->id], $ids(['status' => 'paid']));
        $this->assertSame(collect([$atrasada->id, $paga->id])->sort()->values()->all(), $ids(['month' => '2026-07']));
        $this->assertSame([$falhou->id], $ids(['email' => 'failed']));
        $this->assertSame([$naFila->id], $ids(['email' => 'queued']));
        $this->assertSame([$enviada->id], $ids(['email' => 'sent']));
        $this->assertCount(6, $ids(['month' => 'bobagem']));

        $this->actingAs($user)->get(route('charges.index'))
            ->assertSee('Falhou')
            ->assertSee('Na fila')
            ->assertSee('Enviado');
        $this->assertNotNull($recente);
    }
}
