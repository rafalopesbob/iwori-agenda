<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppConnectionException;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tela WhatsApp: cada profissional conecta o próprio número (WhatsApp
 * Cloud API da Meta), e o envio automático das cobranças sai dele.
 */
class WhatsAppConnectionController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsapp,
    ) {}

    /**
     * Situação da conexão, formulário e passo a passo.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('whatsapp.edit', [
            'user' => $user,
            'enabled' => $this->whatsapp->isEnabled(),
            'connected' => $this->whatsapp->isConfiguredFor($user),
            // Consulta a Meta só quando há conexão; falha vira "não deu para consultar".
            'templateStatus' => $this->whatsapp->templateStatus($user),
            'defaultTemplate' => WhatsAppService::DEFAULT_TEMPLATE,
            'defaultTemplateBody' => WhatsAppService::DEFAULT_TEMPLATE_BODY,
        ]);
    }

    /**
     * Conecta (ou atualiza) o número do profissional, conferindo na Meta
     * antes de salvar. Token em branco mantém o token já salvo.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'whatsapp_phone_number_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'whatsapp_business_account_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'whatsapp_token' => [$user->whatsapp_token ? 'nullable' : 'required', 'string', 'min:20', 'max:1000'],
            'whatsapp_template' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        ], [
            'whatsapp_phone_number_id.regex' => 'O ID do número tem só dígitos (está em "Configuração da API" no painel da Meta).',
            'whatsapp_business_account_id.regex' => 'O ID da conta tem só dígitos (está em "Configuração da API" no painel da Meta).',
            'whatsapp_template.regex' => 'O nome do modelo usa só letras minúsculas, números e _ (como na Meta).',
        ], [
            'whatsapp_phone_number_id' => 'ID do número',
            'whatsapp_business_account_id' => 'ID da conta do WhatsApp Business',
            'whatsapp_token' => 'token de acesso',
            'whatsapp_template' => 'modelo de cobrança',
        ]);

        $token = $validated['whatsapp_token'] ?? $user->whatsapp_token;

        try {
            $number = $this->whatsapp->verify($validated['whatsapp_phone_number_id'], $token);
        } catch (WhatsAppConnectionException $e) {
            return back()->withInput($request->except('whatsapp_token'))
                ->withErrors(['whatsapp_token' => $e->getMessage()]);
        }

        $user->forceFill([
            'whatsapp_phone_number_id' => $validated['whatsapp_phone_number_id'],
            'whatsapp_business_account_id' => $validated['whatsapp_business_account_id'],
            'whatsapp_token' => $token,
            'whatsapp_display_phone' => $number['display_phone'],
            'whatsapp_verified_name' => $number['verified_name'],
            'whatsapp_template' => ($validated['whatsapp_template'] ?? null) ?: null,
            'whatsapp_connected_at' => now(),
        ])->save();

        return redirect()->route('whatsapp.edit')
            ->with('status', 'WhatsApp conectado: as cobranças automáticas vão sair do número '.$number['display_phone'].'.');
    }

    /**
     * Desconecta: o envio automático para, e os botões "WhatsApp" continuam.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'whatsapp_phone_number_id' => null,
            'whatsapp_business_account_id' => null,
            'whatsapp_token' => null,
            'whatsapp_display_phone' => null,
            'whatsapp_verified_name' => null,
            'whatsapp_template' => null,
            'whatsapp_connected_at' => null,
        ])->save();

        return redirect()->route('whatsapp.edit')
            ->with('status', 'WhatsApp desconectado. As cobranças automáticas voltam a ir por e-mail; os botões “WhatsApp” continuam funcionando.');
    }

    /**
     * Envia o modelo de cobrança padrão para aprovação na conta do profissional.
     */
    public function template(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->whatsapp->isConfiguredFor($user)) {
            return redirect()->route('whatsapp.edit')->with('status', 'Conecte o seu número antes de criar o modelo.');
        }

        try {
            $this->whatsapp->createChargeTemplate($user);
        } catch (WhatsAppConnectionException $e) {
            return redirect()->route('whatsapp.edit')->with('status', $e->getMessage());
        }

        // O modelo criado é sempre o padrão: passa a ser o usado na cobrança.
        $user->forceFill(['whatsapp_template' => null])->save();

        return redirect()->route('whatsapp.edit')
            ->with('status', 'Modelo enviado para a Meta. A aprovação costuma levar de minutos a algumas horas; a situação aparece aqui.');
    }
}
