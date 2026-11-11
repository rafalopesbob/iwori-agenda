@extends('layouts.app')

@section('title', 'WhatsApp — Agenda Iwori')

@php
    $input = 'w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25';
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-semibold tracking-tight text-ink">WhatsApp</h1>
        <p class="text-sm text-muted mt-1">
            Conecte o <strong class="font-semibold text-ink">seu</strong> número de WhatsApp: as cobranças automáticas
            saem dele para os clientes que autorizaram. Cada profissional conecta o próprio número.
        </p>
    </div>

    @if (session('status'))
        <div role="status" class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink">
            {{ session('status') }}
        </div>
    @endif

    @unless ($enabled)
        <div class="rounded-xl bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            O envio automático por WhatsApp fica desligado no ambiente de demonstração.
        </div>
    @endunless

    {{-- Situação --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        <div class="flex items-start gap-3">
            <span class="mt-1.5 size-2.5 rounded-full shrink-0 {{ $connected ? 'bg-accent' : 'bg-line-strong' }}"></span>
            <div class="min-w-0 flex-1">
                @if ($connected)
                    <h2 class="text-lg font-semibold tracking-tight text-ink">Conectado: {{ $user->whatsapp_display_phone }}</h2>
                    <p class="text-sm text-muted mt-1">
                        @if ($user->whatsapp_verified_name)
                            Nome na Meta: {{ $user->whatsapp_verified_name }} ·
                        @endif
                        conferido em {{ $user->whatsapp_connected_at?->format('d/m/Y H:i') }}.
                    </p>

                    <div class="mt-4 rounded-xl bg-canvas px-4 py-3 text-sm">
                        <p class="font-medium text-ink">Modelo de cobrança: <span class="font-mono text-[13px]">{{ $user->whatsappTemplate() }}</span></p>
                        @switch($templateStatus)
                            @case('APPROVED')
                                <p class="mt-1 text-accent-ink">Aprovado pela Meta. As cobranças automáticas já podem sair.</p>
                                @break
                            @case('PENDING')
                            @case('IN_APPEAL')
                                <p class="mt-1 text-warn-ink">Em análise na Meta. Até a aprovação, as cobranças automáticas não saem pelo WhatsApp.</p>
                                @break
                            @case('REJECTED')
                            @case('DISABLED')
                            @case('PAUSED')
                                <p class="mt-1 text-danger-ink">A Meta recusou ou pausou este modelo. Veja o motivo no WhatsApp Manager e ajuste o texto por lá.</p>
                                @break
                            @case('missing')
                                <p class="mt-1 text-danger-ink">Este modelo não existe na sua conta do WhatsApp Business.</p>
                                @if ($user->whatsappTemplate() === $defaultTemplate)
                                    <form method="POST" action="{{ route('whatsapp.template') }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center justify-center min-h-11 px-4 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                                            Criar o modelo de cobrança na Meta
                                        </button>
                                    </form>
                                @endif
                                @break
                            @default
                                <p class="mt-1 text-muted">Não deu para consultar a situação do modelo agora. Tente recarregar a página.</p>
                        @endswitch
                    </div>
                @else
                    <h2 class="text-lg font-semibold tracking-tight text-ink">Ainda não conectado</h2>
                    <p class="text-sm text-muted mt-1">
                        Enquanto isso, as cobranças automáticas vão por e-mail, e os botões “WhatsApp” das cobranças e
                        das sessões abrem o WhatsApp do seu celular com a mensagem pronta.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Conectar --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink">{{ $connected ? 'Dados da conexão' : 'Conectar o seu número' }}</h2>
        <p class="text-sm text-muted mt-1 mb-5">
            Os dados vêm do painel da Meta (veja o passo a passo abaixo). Ao salvar, o Agenda Iwori confere com a Meta
            qual é o número antes de conectar.
        </p>

        <form method="POST" action="{{ route('whatsapp.update') }}" class="space-y-5" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="whatsapp_phone_number_id" class="block text-sm font-medium text-ink mb-1.5">ID do número *</label>
                    <input id="whatsapp_phone_number_id" name="whatsapp_phone_number_id" inputmode="numeric" required
                           value="{{ old('whatsapp_phone_number_id', $user->whatsapp_phone_number_id) }}" placeholder="Ex.: 123456789012345" class="{{ $input }}">
                    <p class="text-[13px] text-muted mt-1.5">“Identificação do número de telefone” (Phone number ID).</p>
                    @error('whatsapp_phone_number_id') <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="whatsapp_business_account_id" class="block text-sm font-medium text-ink mb-1.5">ID da conta do WhatsApp Business *</label>
                    <input id="whatsapp_business_account_id" name="whatsapp_business_account_id" inputmode="numeric" required
                           value="{{ old('whatsapp_business_account_id', $user->whatsapp_business_account_id) }}" placeholder="Ex.: 987654321098765" class="{{ $input }}">
                    <p class="text-[13px] text-muted mt-1.5">“Identificação da conta do WhatsApp Business” (WABA ID).</p>
                    @error('whatsapp_business_account_id') <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="whatsapp_token" class="block text-sm font-medium text-ink mb-1.5">Token de acesso {{ $user->whatsapp_token ? '' : '*' }}</label>
                {{-- O token salvo nunca volta para a tela. --}}
                <input id="whatsapp_token" name="whatsapp_token" type="password" autocomplete="new-password" @unless ($user->whatsapp_token) required @endunless
                       placeholder="{{ $user->whatsapp_token ? 'Token salvo. Deixe em branco para manter.' : 'Token permanente do usuário do sistema' }}" class="{{ $input }}">
                <p class="text-[13px] text-muted mt-1.5">Fica guardado criptografado e não aparece de novo nesta tela.</p>
                @error('whatsapp_token') <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="whatsapp_template" class="block text-sm font-medium text-ink mb-1.5">Modelo de cobrança (opcional)</label>
                <input id="whatsapp_template" name="whatsapp_template" value="{{ old('whatsapp_template', $user->whatsapp_template) }}"
                       placeholder="{{ $defaultTemplate }}" class="{{ $input }} font-mono">
                <p class="text-[13px] text-muted mt-1.5">
                    Em branco, usamos o <span class="font-mono">{{ $defaultTemplate }}</span>, que dá para criar por aqui depois de conectar.
                    Se usar outro modelo seu, ele precisa estar em português e ter 3 variáveis, nesta ordem: cliente, valor e período.
                </p>
                @error('whatsapp_template') <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <button type="submit" @disabled(! $enabled)
                        class="inline-flex items-center justify-center min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors disabled:opacity-50">
                    {{ $connected ? 'Salvar e conferir' : 'Conectar e conferir' }}
                </button>
            </div>
        </form>

        @if ($connected)
            <form method="POST" action="{{ route('whatsapp.destroy') }}" class="mt-4 border-t border-line pt-4"
                  onsubmit="return confirm('Desconectar o WhatsApp? As cobranças automáticas voltam a ir por e-mail.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center min-h-11 text-sm font-medium text-danger-ink hover:underline">Desconectar este número</button>
            </form>
        @endif
    </div>

    {{-- Passo a passo --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink">Passo a passo na Meta</h2>

        <div class="mt-4 rounded-xl bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <strong class="font-semibold">Antes de começar:</strong> o número conectado por aqui passa a ser usado pela Meta
            e <strong class="font-semibold">deixa de funcionar no app do WhatsApp</strong> do celular. Use um número só para as
            cobranças (um chip do consultório, por exemplo). Para usar o mesmo número no app e no envio automático, vai
            existir o botão oficial “Conectar com a Meta”, que ainda não está disponível.
        </div>

        <ol class="mt-5 space-y-4 text-sm text-ink list-decimal pl-5 marker:font-semibold marker:text-muted">
            <li>
                Crie (ou use) uma conta empresarial da Meta em
                <a href="https://business.facebook.com" target="_blank" rel="noopener" class="font-medium text-brand hover:underline">business.facebook.com</a>.
            </li>
            <li>
                Em <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener" class="font-medium text-brand hover:underline">developers.facebook.com/apps</a>,
                crie um app do tipo <em>Empresa</em> ligado a essa conta e adicione o produto <strong class="font-semibold">WhatsApp</strong>.
            </li>
            <li>
                No app, abra <strong class="font-semibold">WhatsApp → Configuração da API</strong>, adicione o seu número e confirme
                com o código que chega por SMS ou ligação.
            </li>
            <li>
                Na mesma tela, copie a <strong class="font-semibold">Identificação do número de telefone</strong> e a
                <strong class="font-semibold">Identificação da conta do WhatsApp Business</strong>.
            </li>
            <li>
                Gere um token que não expira: em <strong class="font-semibold">Configurações do negócio → Usuários do sistema</strong>,
                crie um usuário do sistema, dê a ele acesso ao app e à conta do WhatsApp e gere um token com as permissões
                <span class="font-mono text-[13px]">whatsapp_business_messaging</span> e
                <span class="font-mono text-[13px]">whatsapp_business_management</span>, com validade “Nunca”.
            </li>
            <li>
                Cadastre uma forma de pagamento na conta do WhatsApp Business. A Meta cobra as mensagens de cobrança direto
                de você, por mensagem enviada.
            </li>
            <li>Cole os dois IDs e o token no formulário acima e clique em <strong class="font-semibold">Conectar e conferir</strong>.</li>
            <li>
                Clique em <strong class="font-semibold">Criar o modelo de cobrança na Meta</strong> e aguarde a aprovação
                (costuma levar de minutos a algumas horas). O texto enviado para aprovação é:
                <span class="block mt-2 rounded-xl bg-canvas px-4 py-3 text-muted">{{ $defaultTemplateBody }}</span>
                <span class="block mt-1 text-[13px] text-muted">({{ '{' }}{1}} é o nome do cliente, {{ '{' }}{2}} o valor e {{ '{' }}{3}} o período.)</span>
            </li>
            <li>
                Nos clientes que vão receber pelo WhatsApp: canal de cobrança “WhatsApp” ou “E-mail + WhatsApp”, celular
                preenchido e a autorização do cliente marcada.
            </li>
        </ol>
    </div>
</div>
@endsection
