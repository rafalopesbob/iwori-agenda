<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateType;
use App\Mail\ClientChargeMail;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // ── CRUD ─────────────────────────────────────────────────────────

    public function test_index_mostra_tipos_sem_personalizacao_como_padrao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/email-templates')
            ->assertOk()
            ->assertSee('Cobrança de período')
            ->assertSee('Confirmação de agendamento')
            ->assertSee('Padrão do sistema');
    }

    public function test_profissional_cria_template_de_cobranca(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/email-templates', [
                'type' => 'charge',
                'name' => 'Cobrança carinhosa',
                'subject' => 'Fatura do período {{periodo}}',
                'body' => "Oi, {{cliente_nome}}!\n\nSegue o valor: {{valor}}.",
            ])
            ->assertRedirect('/email-templates');

        $this->assertDatabaseHas('email_templates', [
            'user_id' => $user->id,
            'type' => 'charge',
            'name' => 'Cobrança carinhosa',
        ]);
    }

    public function test_nao_permite_dois_templates_do_mesmo_tipo(): void
    {
        $user = User::factory()->create();
        EmailTemplate::factory()->charge()->for($user)->create();

        $this->actingAs($user)
            ->from('/email-templates/create?type=charge')
            ->post('/email-templates', [
                'type' => 'charge',
                'name' => 'Outro',
                'subject' => 'Assunto',
                'body' => 'Corpo',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('email_templates', 1);
    }

    public function test_placeholder_invalido_e_rejeitado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/email-templates/create?type=charge')
            ->post('/email-templates', [
                'type' => 'charge',
                'name' => 'Com erro',
                'subject' => 'Assunto',
                'body' => 'Olá {{nome_inexistente}}',
            ])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_profissional_nao_edita_template_de_outro(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $template = EmailTemplate::factory()->charge()->for($owner)->create();

        $this->actingAs($intruder)
            ->put("/email-templates/{$template->id}", [
                'name' => 'Invadido',
                'subject' => 'x',
                'body' => 'x',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get("/email-templates/{$template->id}/edit")
            ->assertForbidden();
    }

    public function test_atualiza_template_sem_trocar_o_tipo(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::factory()->charge()->for($user)->create();

        $this->actingAs($user)
            ->put("/email-templates/{$template->id}", [
                'type' => 'session_scheduled', // deve ser ignorado
                'name' => 'Novo nome',
                'subject' => 'Novo assunto {{valor}}',
                'body' => 'Novo corpo {{cliente_nome}}',
            ])
            ->assertRedirect('/email-templates');

        $template->refresh();
        $this->assertSame('Novo nome', $template->name);
        $this->assertSame(EmailTemplateType::Charge, $template->type);
    }

    public function test_excluir_template_volta_ao_padrao_do_sistema(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::factory()->charge()->for($user)->create();

        $this->actingAs($user)
            ->delete("/email-templates/{$template->id}")
            ->assertRedirect('/email-templates');

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_preview_renderiza_variaveis_com_dados_de_exemplo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/email-templates/preview', [
                'type' => 'charge',
                'subject' => 'Fatura de {{cliente_nome}}',
                'body' => 'Total: {{valor}}',
            ])
            ->assertOk()
            ->assertSee('Fatura de Ana Beatriz')
            ->assertSee('Total: R$ 480,00');
    }

    // ── Renderização nos e-mails ─────────────────────────────────────

    public function test_cobranca_usa_template_personalizado_do_profissional(): void
    {
        $user = User::factory()->create();
        EmailTemplate::factory()->charge()->for($user)->create([
            'subject' => 'Fatura personalizada — {{valor}}',
            'body' => 'Oi {{cliente_nome}}, seu total é {{valor}}.',
        ]);

        $client = Client::factory()->for($user)->create(['name' => 'Maria Silva']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 150]);

        $mail = new ClientChargeMail(
            $client,
            collect([$session]),
            150.0,
            $session->scheduled_at,
            $session->scheduled_at,
        );

        $mail->assertHasSubject('Fatura personalizada — R$ 150,00');
        $mail->assertSeeInHtml('Oi Maria Silva, seu total é R$ 150,00.');
    }

    public function test_cobranca_usa_template_padrao_quando_nao_ha_personalizado(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Iwori']);
        $client = Client::factory()->for($user)->create(['name' => 'Maria Silva']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 150]);

        $mail = new ClientChargeMail(
            $client,
            collect([$session]),
            150.0,
            $session->scheduled_at,
            $session->scheduled_at,
        );

        $mail->assertHasSubject(sprintf(
            'Valor a pagar: R$ 150,00 — período %s a %s',
            $session->scheduled_at->format('d/m/Y'),
            $session->scheduled_at->format('d/m/Y'),
        ));
        $mail->assertSeeInHtml('Maria Silva');
        $mail->assertSeeInHtml('Dra. Iwori');
    }

    public function test_html_no_template_e_escapado(): void
    {
        $user = User::factory()->create();
        EmailTemplate::factory()->charge()->for($user)->create([
            'subject' => 'Assunto simples',
            'body' => 'Olá {{cliente_nome}} <script>alert("xss")</script>',
        ]);

        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 100]);

        $mail = new ClientChargeMail(
            $client,
            collect([$session]),
            100.0,
            $session->scheduled_at,
            $session->scheduled_at,
        );

        $mail->assertDontSeeInHtml('<script>alert', false);
    }

    public function test_placeholder_desconhecido_fica_literal(): void
    {
        $service = app(\App\Services\EmailTemplateService::class);

        $this->assertSame(
            'Olá Maria, {{desconhecida}}!',
            $service->render('Olá {{cliente_nome}}, {{desconhecida}}!', ['cliente_nome' => 'Maria']),
        );
    }
}
