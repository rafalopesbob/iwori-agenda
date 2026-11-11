<?php

namespace Tests\Feature;

use App\Enums\BillingChannel;
use App\Enums\EmailTemplateType;
use App\Enums\MessageChannel;
use App\Enums\SessionStatus;
use App\Mail\ClientChargeMail;
use App\Mail\SessionScheduledMail;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 das mensagens: textos de WhatsApp editáveis, botões que abrem o
 * WhatsApp do profissional (wa.me), e-mail com o nome dele e "Responder
 * para", e a seção Conexões do perfil.
 */
class MessagesPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
    }

    /**
     * Texto que o link wa.me leva, já decodificado.
     */
    protected function whatsappText(string $url): string
    {
        $this->assertStringStartsWith('https://wa.me/', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['text'];
    }

    // ── Tela Mensagens ───────────────────────────────────────────────

    public function test_tela_mensagens_mostra_e_mail_e_whatsapp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('email-templates.index'))
            ->assertOk()
            ->assertSee('Mensagens')
            ->assertSee('E-mail')
            ->assertSee('WhatsApp')
            ->assertSee(route('email-templates.create', ['type' => 'charge', 'channel' => 'whatsapp']));
    }

    public function test_telas_de_criar_e_editar_abrem(): void
    {
        // A inserção de variáveis monta {{...}} em JavaScript; o Blade não pode
        // confundir essas chaves com as dele.
        $user = User::factory()->create();
        $template = EmailTemplate::factory()->charge()->for($user)->create();

        $this->actingAs($user)->get(route('email-templates.create', ['type' => 'charge', 'channel' => 'whatsapp']))
            ->assertOk()
            ->assertSee('Personalizar WhatsApp')
            ->assertSee("const token = '{' + '{' + name + '}' + '}';", false);
        $this->actingAs($user)->get(route('email-templates.create', ['type' => 'session_scheduled']))
            ->assertOk()
            ->assertSee('Personalizar e-mail')
            ->assertSee('name="subject"', false);
        $this->actingAs($user)->get(route('email-templates.edit', $template))
            ->assertOk()
            ->assertSee('Editar e-mail')
            ->assertSee('&#123;&#123;cliente_nome&#125;&#125;', false);
    }

    public function test_cria_texto_de_whatsapp_sem_assunto_e_sem_mexer_no_e_mail(): void
    {
        $user = User::factory()->create();
        EmailTemplate::factory()->charge()->for($user)->create(['subject' => 'Assunto do e-mail']);

        $this->actingAs($user)->post(route('email-templates.store'), [
            'channel' => 'whatsapp',
            'type' => 'charge',
            'name' => 'Cobrança no WhatsApp',
            'body' => 'Oi {{cliente_nome}}, total {{valor}}.',
        ])->assertRedirect(route('email-templates.index'));

        $whatsapp = EmailTemplate::where('channel', 'whatsapp')->sole();
        $this->assertNull($whatsapp->subject);
        $this->assertSame(EmailTemplateType::Charge, $whatsapp->type);

        // O e-mail do mesmo tipo continua sendo o usado nos e-mails.
        $this->assertSame('Assunto do e-mail', app(EmailTemplateService::class)->resolve($user, EmailTemplateType::Charge)['subject']);
        $this->assertSame('Oi {{cliente_nome}}, total {{valor}}.', app(EmailTemplateService::class)->resolveWhatsapp($user, EmailTemplateType::Charge));
    }

    public function test_texto_de_whatsapp_rejeita_variavel_desconhecida_assunto_e_texto_longo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('email-templates.store'), [
            'channel' => 'whatsapp',
            'type' => 'session_scheduled',
            'name' => 'Confirmação',
            'subject' => 'Não existe no WhatsApp',
            'body' => 'Sessão em {{valor}}',
        ])->assertSessionHasErrors(['subject', 'body']);

        $this->actingAs($user)->post(route('email-templates.store'), [
            'channel' => 'whatsapp',
            'type' => 'session_scheduled',
            'name' => 'Confirmação',
            'body' => str_repeat('a', 1501),
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_um_texto_de_whatsapp_por_tipo(): void
    {
        $user = User::factory()->create();
        EmailTemplate::factory()->charge()->for($user)->create(['channel' => MessageChannel::Whatsapp, 'subject' => null]);

        $this->actingAs($user)->post(route('email-templates.store'), [
            'channel' => 'whatsapp',
            'type' => 'charge',
            'name' => 'Outro',
            'body' => 'Oi',
        ])->assertSessionHasErrors('type');
    }

    public function test_edicao_de_texto_de_whatsapp(): void
    {
        $user = User::factory()->create();
        $template = EmailTemplate::factory()->charge()->for($user)->create(['channel' => MessageChannel::Whatsapp, 'subject' => null]);

        $this->actingAs($user)->get(route('email-templates.edit', $template))
            ->assertOk()
            ->assertSee('Editar WhatsApp')
            ->assertDontSee('name="subject"', false);

        $this->actingAs($user)->put(route('email-templates.update', $template), [
            'name' => 'Novo nome',
            'body' => 'Olá {{cliente_nome}}',
        ])->assertRedirect(route('email-templates.index'));

        $this->assertSame('Olá {{cliente_nome}}', $template->fresh()->body);
    }

    public function test_preview_do_whatsapp_mostra_o_balao_com_negrito(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('email-templates.preview', ['channel' => 'whatsapp']), [
            'type' => 'charge',
            'body' => "Oi {{cliente_nome}}\n*Total: {{valor}}*",
        ])
            ->assertOk()
            ->assertSee('<strong>Total: R$ 480,00</strong>', false)
            ->assertSee('Oi Ana Beatriz');
    }

    // ── Botões WhatsApp (wa.me) ──────────────────────────────────────

    public function test_cobrar_no_whatsapp_registra_a_cobranca_e_abre_o_whatsapp_com_o_texto(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Kátia']);
        EmailTemplate::factory()->charge()->for($user)->create([
            'channel' => MessageChannel::Whatsapp,
            'subject' => null,
            'body' => "Oi {{cliente_nome}}! Total {{valor}}\n{{lista_sessoes}}",
        ]);
        $client = Client::factory()->for($user)->create(['name' => 'Ana', 'phone' => '(11) 98765-4321']);
        ClientSession::factory()->for($client)->completed()->create([
            'scheduled_at' => '2026-08-03 14:00:00',
            'value' => 150,
        ]);

        $response = $this->actingAs($user)->post(route('billing.whatsapp', $client));

        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/5511987654321?text=', $url);
        $this->assertSame(
            "Oi Ana! Total R$ 150,00\n03/08/2026 14:00 — ".SessionStatus::Completed->label().' — R$ 150,00',
            $this->whatsappText($url),
        );

        $charge = Charge::sole();
        $this->assertSame(BillingChannel::Whatsapp, $charge->channel);
        $this->assertSame('150.00', $charge->amount);
        $this->assertNotNull($client->fresh()->last_charged_at);
    }

    public function test_cobrar_no_whatsapp_sem_valor_nao_registra_nada(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create();

        $this->actingAs($user)->from(route('billing.index'))->post(route('billing.whatsapp', $client))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('status', 'Nada a cobrar de '.$client->name.' neste período.');

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_cliente_sem_celular_nao_abre_o_whatsapp(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['phone' => null]);
        ClientSession::factory()->for($client)->completed()->create(['scheduled_at' => '2026-08-03 14:00:00']);

        $this->actingAs($user)->from(route('billing.index'))->post(route('billing.whatsapp', $client))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('status', 'Cadastre o celular de '.$client->name.' para enviar pelo WhatsApp.');

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_nao_e_possivel_usar_os_botoes_com_dados_de_outro_profissional(): void
    {
        $intruso = User::factory()->create();
        $session = ClientSession::factory()->completed()->create();
        $charge = Charge::factory()->forSession($session)->create();

        $this->actingAs($intruso)->post(route('billing.whatsapp', $session->client))->assertForbidden();
        $this->actingAs($intruso)->post(route('sessions.whatsapp.charge', $session))->assertForbidden();
        $this->actingAs($intruso)->get(route('sessions.whatsapp', $session))->assertForbidden();
        $this->actingAs($intruso)->get(route('charges.whatsapp', $charge))->assertForbidden();

        $this->assertDatabaseCount('charges', 1);
    }

    public function test_cobrar_sessao_no_whatsapp_reaproveita_a_cobranca_pendente(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['phone' => '11987654321']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 200]);

        $first = $this->actingAs($user)->post(route('sessions.whatsapp.charge', $session));
        $this->actingAs($user)->post(route('sessions.whatsapp.charge', $session));

        $this->assertStringContainsString('R$ 200,00', $this->whatsappText($first->headers->get('Location')));
        $charge = Charge::sole();
        $this->assertSame($session->id, $charge->client_session_id);
        $this->assertSame(BillingChannel::Whatsapp, $charge->channel);
        // Cobrança avulsa não fecha o ciclo.
        $this->assertNull($client->fresh()->last_charged_at);
    }

    public function test_reenvio_de_cobranca_registrada_nao_altera_nada(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['phone' => '11987654321']);
        $session = ClientSession::factory()->for($client)->completed()->create(['value' => 90]);
        $charge = Charge::factory()->forSession($session)->pending()->create(['amount' => 90]);
        $before = $charge->fresh()->toArray();

        $response = $this->actingAs($user)->get(route('charges.whatsapp', $charge));

        $this->assertStringContainsString('R$ 90,00', $this->whatsappText($response->headers->get('Location')));
        $this->assertSame($before, $charge->fresh()->toArray());
    }

    public function test_confirmacao_da_sessao_usa_o_texto_de_whatsapp_padrao(): void
    {
        $user = User::factory()->create(['name' => 'Dra. Kátia']);
        $client = Client::factory()->for($user)->create(['name' => 'Ana', 'phone' => '11987654321']);
        $session = ClientSession::factory()->for($client)->create([
            'scheduled_at' => '2026-08-12 09:30:00',
            'duration_minutes' => 50,
        ]);

        $url = $this->actingAs($user)->get(route('sessions.whatsapp', $session))->headers->get('Location');

        $this->assertSame(
            "Olá, Ana! Aqui é Dra. Kátia.\n\nSua sessão está confirmada para *12/08/2026 às 09:30* (50 min).",
            $this->whatsappText($url),
        );
    }

    public function test_botoes_aparecem_no_faturamento_e_nas_cobrancas(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user)->create(['phone' => '11987654321']);
        $semCelular = Client::factory()->for($user)->create(['phone' => null]);
        $session = ClientSession::factory()->for($client)->completed()->create(['scheduled_at' => '2026-08-03 14:00:00']);
        ClientSession::factory()->for($semCelular)->completed()->create(['scheduled_at' => '2026-08-04 14:00:00']);
        $charge = Charge::factory()->forSession($session)->pending()->create();

        $this->actingAs($user)->get(route('billing.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee(route('billing.whatsapp', $client), false)
            ->assertDontSee(route('billing.whatsapp', $semCelular), false);

        $this->actingAs($user)->get(route('charges.index'))
            ->assertOk()
            ->assertSee(route('charges.whatsapp', $charge), false);
    }

    // ── E-mail com o nome do profissional ────────────────────────────

    public function test_e_mails_saem_com_o_nome_do_profissional_e_respostas_para_ele(): void
    {
        config(['mail.from.address' => 'naoresponda@iwori.test']);
        $user = User::factory()->create(['name' => 'Dra. Kátia', 'email' => 'katia@consultorio.test']);
        $client = Client::factory()->for($user)->create();
        $session = ClientSession::factory()->for($client)->completed()->create();

        $charge = new ClientChargeMail($client, collect([$session]), 100.0, $session->scheduled_at, $session->scheduled_at);
        $charge->assertFrom('naoresponda@iwori.test', 'Dra. Kátia');
        $charge->assertHasReplyTo('katia@consultorio.test', 'Dra. Kátia');

        $user->forceFill(['reply_to_email' => 'recepcao@consultorio.test'])->save();

        $scheduled = new SessionScheduledMail($session->fresh());
        $scheduled->assertFrom('naoresponda@iwori.test', 'Dra. Kátia');
        $scheduled->assertHasReplyTo('recepcao@consultorio.test', 'Dra. Kátia');
    }

    // ── Perfil → Conexões ────────────────────────────────────────────

    public function test_perfil_mostra_as_conexoes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Conexões')
            ->assertSee('E-mail para respostas')
            ->assertSee('Editar os textos do WhatsApp')
            ->assertSee('Google Agenda');
    }

    public function test_define_e_limpa_o_e_mail_para_respostas(): void
    {
        $user = User::factory()->create(['email' => 'conta@teste.test']);

        $this->actingAs($user)->patch(route('profile.reply-to'), ['reply_to_email' => 'respostas@teste.test'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Pronto: as respostas dos seus clientes vão para respostas@teste.test.');
        $this->assertSame('respostas@teste.test', $user->fresh()->replyToAddress());

        $this->actingAs($user)->patch(route('profile.reply-to'), ['reply_to_email' => ''])
            ->assertRedirect(route('profile.edit'));
        $this->assertNull($user->fresh()->reply_to_email);
        $this->assertSame('conta@teste.test', $user->fresh()->replyToAddress());

        $this->actingAs($user)->patch(route('profile.reply-to'), ['reply_to_email' => 'não é e-mail'])
            ->assertSessionHasErrors('reply_to_email');
    }
}
