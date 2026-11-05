<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendClientCharge;
use App\Jobs\SendSessionCharge;
use App\Mail\SessionScheduledMail;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function verificationUrl(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($email ?? $user->email),
        ]);
    }

    // ── Cadastro e confirmação ───────────────────────────────────────

    public function test_cadastro_envia_link_de_confirmacao(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Nova Profissional',
            'email' => 'nova@teste.com',
            'password' => 'Senha-Forte-123!',
            'password_confirmation' => 'Senha-Forte-123!',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'nova@teste.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    public function test_link_de_confirmacao_vai_pela_fila(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new VerifyEmailQueued,
        );
    }

    public function test_link_assinado_confirma_o_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect('/dashboard');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_link_com_hash_de_outro_email_nao_confirma(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user, 'outro@teste.com'))
            ->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_link_sem_assinatura_valida_nao_confirma(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get("/email/verify/{$user->id}/".sha1($user->email))
            ->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_link_de_outro_usuario_nao_confirma_a_conta_logada(): void
    {
        $vitima = User::factory()->unverified()->create();
        $outro = User::factory()->unverified()->create();

        $this->actingAs($vitima)
            ->get($this->verificationUrl($outro))
            ->assertForbidden();

        $this->assertFalse($vitima->refresh()->hasVerifiedEmail());
        $this->assertFalse($outro->refresh()->hasVerifiedEmail());
    }

    public function test_tela_de_confirmacao_e_reenvio(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify')
            ->assertOk()
            ->assertSee('Confirme seu e-mail')
            ->assertSee($user->email);

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification')
            ->assertRedirect('/email/verify');

        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    public function test_conta_ja_confirmada_nao_ve_a_tela_nem_recebe_reenvio(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/email/verify')->assertRedirect('/dashboard');
        $this->actingAs($user)->post('/email/verification-notification')->assertRedirect('/dashboard');

        Notification::assertNothingSent();
    }

    public function test_aviso_aparece_no_painel_ate_confirmar(): void
    {
        $naoConfirmado = User::factory()->unverified()->create();
        $confirmado = User::factory()->create();

        $this->actingAs($naoConfirmado)->get('/dashboard')
            ->assertOk()
            ->assertSee('Confirme seu e-mail para liberar o envio');

        $this->actingAs($confirmado)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Confirme seu e-mail para liberar o envio');
    }

    public function test_trocar_email_pede_nova_confirmacao(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'novo-endereco@teste.com',
            'email_current_password' => 'password',
        ])->assertRedirect('/profile');

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    // ── Envios bloqueados até confirmar ──────────────────────────────

    public function test_conta_nao_confirmada_nao_cobra_cliente_nem_sessao(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();
        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->completed()->create();

        $this->actingAs($user)->post("/billing/{$client->id}/charge")->assertRedirect('/email/verify');
        $this->actingAs($user)->post("/sessions/{$session->id}/charge")->assertRedirect('/email/verify');

        Queue::assertNotPushed(SendClientCharge::class);
        Queue::assertNotPushed(SendSessionCharge::class);
    }

    public function test_conta_nao_confirmada_agenda_mas_nao_envia_confirmacao(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $client = Client::factory()->for($user)->create(['email' => 'cliente@teste.com']);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ])->assertSessionHas('status', fn (string $status) => str_contains($status, 'confirme seu e-mail'));

        $this->assertDatabaseCount('client_sessions', 1);
        Mail::assertNotQueued(SessionScheduledMail::class);
    }

    public function test_cobranca_diaria_ignora_profissional_nao_confirmado(): void
    {
        Queue::fake();

        $confirmado = User::factory()->create();
        $naoConfirmado = User::factory()->unverified()->create();
        Client::factory()->for($confirmado)->create(['payment_day' => 5]);
        Client::factory()->for($naoConfirmado)->create(['payment_day' => 5]);

        $this->artisan('charges:dispatch', ['--date' => '2026-08-05'])
            ->expectsOutputToContain('Cobranças despachadas para 05/08/2026: 1')
            ->assertSuccessful();

        Queue::assertPushed(SendClientCharge::class, 1);
        Queue::assertPushed(SendClientCharge::class, fn (SendClientCharge $job) => $job->client->user->is($confirmado));
    }
}
