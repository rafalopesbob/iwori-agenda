<?php

namespace Tests\Feature;

use App\Jobs\SendClientCharge;
use App\Jobs\SendSessionCharge;
use App\Mail\ClientChargeMail;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\BillingService;
use App\Services\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function withWhatsappConfigured(): void
    {
        config([
            'services.whatsapp.token' => 'token-teste',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.template_charge' => 'cobranca_iwori',
        ]);
    }

    // ── Vencimento por ciclo ─────────────────────────────────────────

    public function test_mensal_vence_no_dia_configurado(): void
    {
        $client = Client::factory()->create(['payment_day' => 5]);

        $this->assertTrue($client->isChargeDueOn(CarbonImmutable::parse('2026-08-05')));
        $this->assertFalse($client->isChargeDueOn(CarbonImmutable::parse('2026-08-04')));
        $this->assertFalse($client->isChargeDueOn(CarbonImmutable::parse('2026-08-06')));
    }

    public function test_mensal_dia_31_cobra_no_ultimo_dia_de_fevereiro(): void
    {
        $client = Client::factory()->create(['payment_day' => 31]);

        $this->assertTrue($client->isChargeDueOn(CarbonImmutable::parse('2026-02-28')));
        $this->assertFalse($client->isChargeDueOn(CarbonImmutable::parse('2026-02-27')));
    }

    public function test_nao_cobra_duas_vezes_no_mesmo_dia(): void
    {
        $client = Client::factory()->create(['payment_day' => 5]);
        $client->forceFill(['last_charged_at' => '2026-08-05 08:00:00'])->save();

        $this->assertFalse($client->fresh()->isChargeDueOn(CarbonImmutable::parse('2026-08-05')));
    }

    public function test_semanal_vence_apos_sete_dias_da_ultima_cobranca(): void
    {
        $client = Client::factory()->weekly()->create();
        $client->forceFill(['last_charged_at' => '2026-07-01 08:00:00'])->save();
        $client = $client->fresh();

        $this->assertFalse($client->isChargeDueOn(CarbonImmutable::parse('2026-07-07')));
        $this->assertTrue($client->isChargeDueOn(CarbonImmutable::parse('2026-07-08')));
    }

    public function test_intervalo_vence_apos_x_dias(): void
    {
        $client = Client::factory()->interval(15)->create();
        $client->forceFill(['last_charged_at' => '2026-07-01 08:00:00'])->save();
        $client = $client->fresh();

        $this->assertFalse($client->isChargeDueOn(CarbonImmutable::parse('2026-07-15')));
        $this->assertTrue($client->isChargeDueOn(CarbonImmutable::parse('2026-07-16')));
    }

    public function test_ciclo_nunca_cobrado_vence_quando_ha_sessoes_faturaveis(): void
    {
        $semSessoes = Client::factory()->weekly()->create();
        $comSessoes = Client::factory()->weekly()->create();
        ClientSession::factory()->for($comSessoes)->completed()->create([
            'scheduled_at' => '2026-07-01 10:00:00',
        ]);

        $date = CarbonImmutable::parse('2026-07-10');

        $this->assertFalse($semSessoes->isChargeDueOn($date));
        $this->assertTrue($comSessoes->isChargeDueOn($date));
    }

    public function test_cliente_inativo_nunca_vence(): void
    {
        $client = Client::factory()->inactive()->create(['payment_day' => 5]);

        $this->assertFalse($client->isChargeDueOn(CarbonImmutable::parse('2026-08-05')));
    }

    // ── Período e valor da cobrança ──────────────────────────────────

    public function test_periodo_corrente_parte_da_ultima_cobranca(): void
    {
        $client = Client::factory()->create(['payment_day' => 5]);
        $client->forceFill(['last_charged_at' => '2026-07-05 08:00:00'])->save();

        $period = $client->fresh()->currentChargePeriod(CarbonImmutable::parse('2026-08-05'));

        $this->assertSame('2026-07-06 00:00:00', $period['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-05', $period['end']->format('Y-m-d'));
    }

    public function test_periodo_sem_cobranca_anterior_volta_um_ciclo(): void
    {
        $client = Client::factory()->create(['payment_day' => 5]);

        $period = $client->currentChargePeriod(CarbonImmutable::parse('2026-08-05'));

        $this->assertSame('2026-07-06', $period['start']->format('Y-m-d'));
    }

    public function test_period_charge_soma_apenas_faturaveis_do_periodo(): void
    {
        $client = Client::factory()->create();

        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-07-10 10:00:00', 'value' => 150,
        ]);
        ClientSession::factory()->for($client)->noShow()->create([
            'scheduled_at' => '2026-07-15 10:00:00', 'value' => 100,
        ]);
        ClientSession::factory()->for($client)->noShowExcused()->create([
            'scheduled_at' => '2026-07-16 10:00:00', 'value' => 100, // abonada
        ]);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-06-01 10:00:00', 'value' => 300, // fora do período
        ]);

        $charge = app(BillingService::class)->periodCharge(
            $client,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-31 23:59:59'),
        );

        $this->assertEquals(250.0, $charge['total']);
        $this->assertCount(2, $charge['sessions']);
    }

    // ── Job de cobrança e canais ─────────────────────────────────────

    public function test_cobranca_por_email_enfileira_o_mail_e_fecha_o_periodo(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 200,
        ]);

        (new SendClientCharge($client))->handle(app(BillingService::class), app(WhatsAppService::class));

        Mail::assertQueued(ClientChargeMail::class, function (ClientChargeMail $mail) {
            return $mail->hasTo('cliente@teste.com') && $mail->total === 200.0;
        });
        $this->assertNotNull($client->fresh()->last_charged_at);
    }

    public function test_cobranca_por_whatsapp_envia_template_para_o_numero(): void
    {
        $this->withWhatsappConfigured();
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        $client = Client::factory()->channelWhatsapp()->create(['phone' => '(11) 98765-4321']);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 180,
        ]);

        (new SendClientCharge($client))->handle(app(BillingService::class), app(WhatsAppService::class));

        Mail::assertNotQueued(ClientChargeMail::class);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com')
                && $request['to'] === '5511987654321'
                && $request['template']['name'] === 'cobranca_iwori';
        });
    }

    public function test_canal_ambos_envia_email_e_whatsapp(): void
    {
        $this->withWhatsappConfigured();
        Mail::fake();
        Http::fake(['graph.facebook.com/*' => Http::response([])]);

        $client = Client::factory()->channelBoth()->create([
            'email' => 'cliente@teste.com',
            'phone' => '(11) 98765-4321',
        ]);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 180,
        ]);

        (new SendClientCharge($client))->handle(app(BillingService::class), app(WhatsAppService::class));

        Mail::assertQueued(ClientChargeMail::class);
        Http::assertSentCount(1);
    }

    public function test_whatsapp_sem_credenciais_fica_dormente(): void
    {
        Mail::fake();
        Http::fake();

        $client = Client::factory()->channelWhatsapp()->create(['phone' => '(11) 98765-4321']);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 180,
        ]);

        (new SendClientCharge($client))->handle(app(BillingService::class), app(WhatsAppService::class));

        Http::assertNothingSent();
        $this->assertNotNull($client->fresh()->last_charged_at);
    }

    public function test_periodo_sem_valor_nao_envia_nada(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        ClientSession::factory()->for($client)->noShowExcused()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 200, // abonada: nada a cobrar
        ]);

        (new SendClientCharge($client))->handle(app(BillingService::class), app(WhatsAppService::class));

        Mail::assertNotQueued(ClientChargeMail::class);
    }

    public function test_cobranca_avulsa_de_sessao_usa_o_valor_da_sessao(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 175]);

        (new SendSessionCharge($session))->handle(app(WhatsAppService::class));

        Mail::assertQueued(ClientChargeMail::class, fn (ClientChargeMail $mail) => $mail->total === 175.0);
        // Cobrança avulsa não fecha o ciclo.
        $this->assertNull($client->fresh()->last_charged_at);
    }

    // ── Comando agendado ─────────────────────────────────────────────

    public function test_comando_despacha_apenas_clientes_vencendo_na_data(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Client::factory()->for($user)->create(['payment_day' => 5]); // vence dia 5
        Client::factory()->for($user)->create(['payment_day' => 10]); // não vence
        Client::factory()->for($user)->inactive()->create(['payment_day' => 5]); // inativo

        $comSessao = Client::factory()->for($user)->weekly()->create(); // nunca cobrado, com sessão
        ClientSession::factory()->for($comSessao)->completed()->create([
            'scheduled_at' => '2026-08-01 10:00:00',
        ]);

        $this->artisan('charges:dispatch', ['--date' => '2026-08-05'])
            ->expectsOutputToContain('Cobranças despachadas para 05/08/2026: 2')
            ->assertSuccessful();

        Queue::assertPushed(SendClientCharge::class, 2);
    }

    // ── Botões manuais ───────────────────────────────────────────────

    public function test_botao_cobrar_do_faturamento_despacha_o_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->post("/billing/{$client->id}/charge")->assertRedirect();

        Queue::assertPushed(SendClientCharge::class);
    }

    public function test_nao_e_possivel_cobrar_cliente_de_outro_profissional(): void
    {
        Queue::fake();

        $intruso = User::factory()->create();
        $client = Client::factory()->create();

        $this->actingAs($intruso)->post("/billing/{$client->id}/charge")->assertForbidden();

        Queue::assertNotPushed(SendClientCharge::class);
    }

    public function test_botao_de_cobranca_da_sessao_despacha_o_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $session = ClientSession::factory()->for(Client::factory()->for($user))->completed()->create();

        $this->actingAs($user)->post("/sessions/{$session->id}/charge")->assertRedirect();

        Queue::assertPushed(SendSessionCharge::class);
    }

    public function test_nao_e_possivel_cobrar_sessao_de_outro_profissional(): void
    {
        Queue::fake();

        $intruso = User::factory()->create();
        $session = ClientSession::factory()->completed()->create();

        $this->actingAs($intruso)->post("/sessions/{$session->id}/charge")->assertForbidden();

        Queue::assertNotPushed(SendSessionCharge::class);
    }
}
