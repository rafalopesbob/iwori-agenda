<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tela WhatsApp: cada profissional conecta o próprio número, conferido na
 * Meta antes de salvar; o token fica criptografado e nunca volta para a tela.
 */
class WhatsAppConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Respostas da Graph API: número conferido e lista de modelos.
     */
    protected function fakeMeta(string $templateStatus = 'APPROVED'): void
    {
        Http::fake([
            'graph.facebook.com/*/message_templates*' => Http::response(['data' => $templateStatus === 'missing' ? [] : [
                ['name' => 'cobranca_iwori', 'status' => $templateStatus, 'language' => 'pt_BR'],
            ]]),
            'graph.facebook.com/*' => Http::response([
                'display_phone_number' => '+55 11 98765-4321',
                'verified_name' => 'Consultório Kátia',
                'id' => '123456789012345',
            ]),
        ]);
    }

    protected function connectedUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'whatsapp_phone_number_id' => '123456789012345',
            'whatsapp_business_account_id' => '987654321098765',
            'whatsapp_token' => 'EAAG-token-antigo-1234567890',
            'whatsapp_display_phone' => '+55 11 98765-4321',
            'whatsapp_connected_at' => now(),
        ])->save();

        return $user;
    }

    public function test_tela_sem_conexao_mostra_passo_a_passo(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('whatsapp.edit'))
            ->assertOk()
            ->assertSee('Ainda não conectado')
            ->assertSee('Conectar e conferir')
            ->assertSee('Passo a passo na Meta')
            ->assertSee('deixa de funcionar no app do WhatsApp');

        Http::assertNothingSent();
    }

    public function test_conecta_o_numero_do_profissional_conferindo_na_meta(): void
    {
        $this->fakeMeta();
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('whatsapp.update'), [
            'whatsapp_phone_number_id' => '123456789012345',
            'whatsapp_business_account_id' => '987654321098765',
            'whatsapp_token' => 'EAAG-token-novo-1234567890',
        ])->assertRedirect(route('whatsapp.edit'))
            ->assertSessionHas('status', 'WhatsApp conectado: as cobranças automáticas vão sair do número +55 11 98765-4321.');

        $user->refresh();
        $this->assertTrue($user->hasWhatsapp());
        $this->assertSame('+55 11 98765-4321', $user->whatsapp_display_phone);
        $this->assertSame('Consultório Kátia', $user->whatsapp_verified_name);
        $this->assertSame('EAAG-token-novo-1234567890', $user->whatsapp_token);
        // No banco, só criptografado.
        $this->assertStringNotContainsString('EAAG-token-novo', DB::table('users')->where('id', $user->id)->value('whatsapp_token'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/123456789012345?')
            && $request->hasHeader('Authorization', 'Bearer EAAG-token-novo-1234567890'));
    }

    public function test_meta_recusa_e_nada_e_salvo(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401)]);
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('whatsapp.edit'))->put(route('whatsapp.update'), [
            'whatsapp_phone_number_id' => '123456789012345',
            'whatsapp_business_account_id' => '987654321098765',
            'whatsapp_token' => 'EAAG-token-errado-1234567890',
        ])->assertRedirect(route('whatsapp.edit'))
            ->assertSessionHasErrors(['whatsapp_token' => 'A Meta recusou: Invalid OAuth access token.']);

        $this->assertFalse($user->fresh()->hasWhatsapp());
    }

    public function test_token_em_branco_mantem_o_salvo_e_nunca_volta_para_a_tela(): void
    {
        $this->fakeMeta();
        $user = $this->connectedUser();

        $this->actingAs($user)->get(route('whatsapp.edit'))
            ->assertOk()
            ->assertSee('Conectado: +55 11 98765-4321')
            ->assertSee('Aprovado pela Meta')
            ->assertDontSee('EAAG-token-antigo');

        $this->actingAs($user)->put(route('whatsapp.update'), [
            'whatsapp_phone_number_id' => '123456789012345',
            'whatsapp_business_account_id' => '987654321098765',
            'whatsapp_token' => '',
            'whatsapp_template' => 'cobranca_consultorio',
        ])->assertRedirect(route('whatsapp.edit'));

        $user->refresh();
        $this->assertSame('EAAG-token-antigo-1234567890', $user->whatsapp_token);
        $this->assertSame('cobranca_consultorio', $user->whatsappTemplate());
    }

    public function test_validacao_dos_campos(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('whatsapp.update'), [
            'whatsapp_phone_number_id' => '+55 11 9876',
            'whatsapp_business_account_id' => '',
            'whatsapp_token' => '',
            'whatsapp_template' => 'Cobrança Nova',
        ])->assertSessionHasErrors(['whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_token', 'whatsapp_template']);

        Http::assertNothingSent();
    }

    public function test_desconectar_apaga_as_credenciais(): void
    {
        $user = $this->connectedUser();

        $this->actingAs($user)->delete(route('whatsapp.destroy'))->assertRedirect(route('whatsapp.edit'));

        $user->refresh();
        $this->assertFalse($user->hasWhatsapp());
        $this->assertNull($user->whatsapp_token);
        $this->assertNull($user->whatsapp_display_phone);
    }

    public function test_modelo_inexistente_pode_ser_criado_na_conta_do_profissional(): void
    {
        $this->fakeMeta('missing');
        $user = $this->connectedUser();

        $this->actingAs($user)->get(route('whatsapp.edit'))
            ->assertSee('Este modelo não existe na sua conta')
            ->assertSee('Criar o modelo de cobrança na Meta');

        $this->actingAs($user)->post(route('whatsapp.template'))
            ->assertRedirect(route('whatsapp.edit'))
            ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Modelo enviado para a Meta.'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/987654321098765/message_templates')
            && $request['name'] === WhatsAppService::DEFAULT_TEMPLATE
            && $request['category'] === 'UTILITY'
            && $request['language'] === 'pt_BR');
    }

    public function test_modelo_em_analise(): void
    {
        $this->fakeMeta('PENDING');

        $this->actingAs($this->connectedUser())->get(route('whatsapp.edit'))
            ->assertSee('Em análise na Meta');
    }

    public function test_desligado_na_demonstracao(): void
    {
        config(['demo.enabled' => true, 'services.whatsapp.enabled' => false]);
        Http::fake();
        $user = $this->connectedUser();

        $this->actingAs($user)->get(route('whatsapp.edit'))
            ->assertOk()
            ->assertSee('fica desligado no ambiente de demonstração');

        $this->actingAs($user)->from(route('whatsapp.edit'))->put(route('whatsapp.update'), [
            'whatsapp_phone_number_id' => '123456789012345',
            'whatsapp_business_account_id' => '987654321098765',
            'whatsapp_token' => 'EAAG-token-novo-1234567890',
        ]);

        $this->assertSame('EAAG-token-antigo-1234567890', $user->fresh()->whatsapp_token);
        Http::assertNothingSent();
    }

    public function test_menu_e_perfil_levam_para_a_tela(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertSee('Conectar o meu número')
            ->assertSee(route('whatsapp.edit'), false);
    }
}
