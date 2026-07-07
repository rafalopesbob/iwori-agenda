<?php

namespace Tests\Feature;

use App\Mail\SessionScheduledMail;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SessionScheduledMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_agendamento_envia_confirmacao_para_a_fila(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['email' => 'cliente@teste.com']);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        Mail::assertQueued(SessionScheduledMail::class, function (SessionScheduledMail $mail) use ($client) {
            return $mail->hasTo('cliente@teste.com')
                && $mail->session->client->is($client);
        });
    }

    public function test_cliente_sem_email_nao_gera_envio(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['email' => null]);

        $this->actingAs($user)->post('/sessions', [
            'client_id' => $client->id,
            'scheduled_at' => '2026-07-20T14:00',
            'duration_minutes' => 50,
        ]);

        $this->assertDatabaseCount('client_sessions', 1);
        Mail::assertNotQueued(SessionScheduledMail::class);
    }

    public function test_conteudo_do_email_traz_os_dados_da_sessao(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Iwori']);
        $client = Client::factory()->for($user)->create(['name' => 'Maria Silva', 'email' => 'maria@teste.com']);
        $session = ClientSession::factory()->for($client)->create([
            'scheduled_at' => '2026-07-20 14:00:00',
            'duration_minutes' => 50,
        ]);

        $mail = new SessionScheduledMail($session);

        $mail->assertHasSubject('Sessão confirmada para 20/07/2026 às 14:00');
        $mail->assertSeeInHtml('Maria Silva');
        $mail->assertSeeInHtml('Dra. Iwori');
        $mail->assertSeeInHtml('14:00');
        $mail->assertSeeInHtml('50 minutos');
    }
}
