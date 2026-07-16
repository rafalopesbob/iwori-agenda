<?php

namespace Tests\Feature;

use App\Enums\ChargeStatus;
use App\Jobs\SendClientCharge;
use App\Jobs\SendSessionCharge;
use App\Mail\ClientChargeMail;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\BillingService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChargeRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function runClientCharge(Client $client): void
    {
        (new SendClientCharge($client))->handle(app(BillingService::class), app(WhatsAppService::class));
    }

    // ── Registro criado pelos jobs ───────────────────────────────────

    public function test_job_de_cobranca_cria_registro_pendente_com_periodo_e_valor(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 200,
        ]);

        $this->runClientCharge($client);

        $charge = Charge::sole();
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertSame('200.00', $charge->amount);
        $this->assertSame($client->id, $charge->client_id);
        $this->assertSame($client->user_id, $charge->user_id);
        $this->assertNull($charge->client_session_id);
        $this->assertNotNull($charge->sent_at);
    }

    public function test_job_nao_cria_registro_quando_total_zero(): void
    {
        Mail::fake();

        $client = Client::factory()->create();

        $this->runClientCharge($client);

        $this->assertDatabaseCount('charges', 0);
        $this->assertNotNull($client->fresh()->last_charged_at);
    }

    public function test_job_nao_duplica_registro_para_o_mesmo_periodo(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => now()->subDays(3), 'value' => 200,
        ]);

        $period = $client->currentChargePeriod();

        (new SendClientCharge($client, $period['start'], $period['end']))
            ->handle(app(BillingService::class), app(WhatsAppService::class));
        (new SendClientCharge($client, $period['start'], $period['end']))
            ->handle(app(BillingService::class), app(WhatsAppService::class));

        $this->assertDatabaseCount('charges', 1);
        // Re-disparo reenvia a mensagem sem duplicar o registro.
        Mail::assertQueuedCount(2);
    }

    public function test_cobranca_avulsa_cria_registro_vinculado_a_sessao(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 175]);

        (new SendSessionCharge($session))->handle(app(WhatsAppService::class));

        $charge = Charge::sole();
        $this->assertSame($session->id, $charge->client_session_id);
        $this->assertSame('175.00', $charge->amount);
        $this->assertTrue($charge->period_start->isSameDay($session->scheduled_at));
    }

    public function test_cobranca_avulsa_nao_duplica_registro_pendente_da_mesma_sessao(): void
    {
        Mail::fake();

        $client = Client::factory()->create(['email' => 'cliente@teste.com']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 175]);

        (new SendSessionCharge($session))->handle(app(WhatsAppService::class));
        (new SendSessionCharge($session))->handle(app(WhatsAppService::class));

        $this->assertDatabaseCount('charges', 1);
        Mail::assertQueuedCount(2);
    }

    // ── Listagem ─────────────────────────────────────────────────────

    public function test_listagem_mostra_apenas_cobrancas_do_profissional(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Charge::factory()->forUser($user)->pending()->create();
        $theirs = Charge::factory()->forUser($other)->pending()->create();

        $this->actingAs($user)
            ->get('/charges')
            ->assertOk()
            ->assertSee($mine->client->name)
            ->assertDontSee($theirs->client->name);
    }

    public function test_filtro_por_status(): void
    {
        $user = User::factory()->create();
        // O nome do cliente aparece no <select> de filtro, então as
        // asserções usam o valor da cobrança.
        Charge::factory()->forUser($user)->pending()->create(['amount' => 111.11]);
        // paid_at fora do mês corrente para o valor não aparecer no card de totais.
        Charge::factory()->forUser($user)->paid()->create([
            'amount' => 222.22,
            'paid_at' => now()->subMonths(2),
        ]);

        $this->actingAs($user)
            ->get('/charges?status=pending')
            ->assertOk()
            ->assertSee('R$ 111,11')
            ->assertDontSee('R$ 222,22');
    }

    public function test_filtro_por_cliente(): void
    {
        $user = User::factory()->create();
        $chargeA = Charge::factory()->forUser($user)->pending()->create(['amount' => 111.11]);
        Charge::factory()->forUser($user)->pending()->create(['amount' => 222.22]);

        $this->actingAs($user)
            ->get('/charges?client='.$chargeA->client_id)
            ->assertOk()
            ->assertSee('R$ 111,11')
            ->assertDontSee('R$ 222,22');
    }

    // ── Confirmação de pagamento ─────────────────────────────────────

    public function test_confirmar_pagamento_sem_comprovante(): void
    {
        $user = User::factory()->create();
        $charge = Charge::factory()->forUser($user)->pending()->create();

        $this->actingAs($user)
            ->patch("/charges/{$charge->id}/pay", [
                'paid_at' => now()->subDay()->format('Y-m-d'),
                'notes' => 'Pix recebido',
            ])
            ->assertRedirect('/charges');

        $charge->refresh();
        $this->assertSame(ChargeStatus::Paid, $charge->status);
        $this->assertNotNull($charge->paid_at);
        $this->assertSame('Pix recebido', $charge->notes);
    }

    public function test_confirmar_pagamento_com_comprovante_salva_em_disco_privado(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $charge = Charge::factory()->forUser($user)->pending()->create();

        $this->actingAs($user)
            ->patch("/charges/{$charge->id}/pay", [
                'receipt' => UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect('/charges');

        $charge->refresh();
        $this->assertNotNull($charge->receipt_path);
        $this->assertStringStartsWith("receipts/{$user->id}/", $charge->receipt_path);
        Storage::disk('local')->assertExists($charge->receipt_path);
    }

    public function test_comprovante_rejeita_extensao_invalida(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $charge = Charge::factory()->forUser($user)->pending()->create();

        $this->actingAs($user)
            ->from('/charges')
            ->patch("/charges/{$charge->id}/pay", [
                'receipt' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(ChargeStatus::Pending, $charge->refresh()->status);
    }

    public function test_profissional_nao_confirma_pagamento_de_outro(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $charge = Charge::factory()->forUser($owner)->pending()->create();

        $this->actingAs($intruder)
            ->patch("/charges/{$charge->id}/pay", [])
            ->assertForbidden();

        $this->assertSame(ChargeStatus::Pending, $charge->refresh()->status);
    }

    public function test_download_do_comprovante_exige_dono(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $charge = Charge::factory()->forUser($owner)->pending()->create();

        $this->actingAs($owner)->patch("/charges/{$charge->id}/pay", [
            'receipt' => UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($owner)
            ->get("/charges/{$charge->id}/receipt")
            ->assertOk();

        $this->actingAs($intruder)
            ->get("/charges/{$charge->id}/receipt")
            ->assertForbidden();
    }

    public function test_reabrir_cobranca_volta_para_pendente(): void
    {
        $user = User::factory()->create();
        $charge = Charge::factory()->forUser($user)->paid()->create();

        $this->actingAs($user)
            ->patch("/charges/{$charge->id}/reopen")
            ->assertRedirect('/charges');

        $charge->refresh();
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertNull($charge->paid_at);
    }

    public function test_cobranca_pendente_antiga_conta_como_atrasada(): void
    {
        $charge = Charge::factory()->pending()->create(['sent_at' => now()->subDays(10)]);
        $recent = Charge::factory()->pending()->create(['sent_at' => now()->subDay()]);
        $paid = Charge::factory()->paid()->create(['sent_at' => now()->subDays(30)]);

        $this->assertTrue($charge->isOverdue());
        $this->assertFalse($recent->isOverdue());
        $this->assertFalse($paid->isOverdue());
    }
}
