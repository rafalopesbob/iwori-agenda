@extends('layouts.app')

@section('title', 'Google Agenda — Agenda Iwori')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-semibold tracking-tight text-ink">Conectar o Google Agenda</h1>
        <p class="mt-2 text-[15px] text-muted">
            Suas sessões do Iwori aparecem sozinhas no Google Agenda, no celular e no computador, com os lembretes do Google.
            Você continua agendando aqui; o Google só mostra.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-danger/20 bg-danger-tint px-4 py-3 text-sm font-medium text-danger-ink" role="alert">
            {{ session('status') }}
        </div>
    @endif

    {{-- Situação da conexão do profissional logado --}}
    <div class="rounded-2xl border border-line bg-white p-5 sm:p-6 flex items-start gap-3">
        <span class="mt-1.5 size-2 rounded-full shrink-0 {{ $connected ? 'bg-accent' : 'bg-line-strong' }}"></span>
        <div class="min-w-0 flex-1">
            @if (! $configured)
                <p class="text-[15px] font-semibold">A integração ainda não foi ativada</p>
                <p class="mt-0.5 text-sm text-muted">O administrador do sistema precisa ligar o Google Agenda no servidor. Enquanto isso, o botão de conectar não aparece.</p>
            @elseif ($connected)
                <p class="text-[15px] font-semibold">Sua conta está conectada</p>
                <p class="mt-0.5 text-sm text-muted">As sessões agendadas já estão indo para o seu Google Agenda.</p>
            @else
                <p class="text-[15px] font-semibold">Sua conta ainda não está conectada</p>
                <p class="mt-0.5 text-sm text-muted">Leva menos de um minuto. Siga o passo a passo abaixo.</p>
                <a href="{{ route('google.connect') }}"
                   class="mt-3 inline-flex items-center justify-center min-h-11 px-4 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                    Conectar o Google Agenda
                </a>
            @endif
        </div>
    </div>

    <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-4">O que você ganha</h2>
        <ul class="space-y-3 text-[15px]">
            <li><span class="font-semibold">Lembrete no celular antes de cada sessão.</span> <span class="text-muted">O app do Google Agenda avisa no horário que você já usa para os seus compromissos.</span></li>
            <li><span class="font-semibold">Tudo numa agenda só.</span> <span class="text-muted">As sessões ficam ao lado dos compromissos pessoais, e fica mais difícil marcar dois horários juntos.</span></li>
            <li><span class="font-semibold">Sempre atualizada.</span> <span class="text-muted">Reagendou, arrastou ou cancelou no Iwori? O Google acompanha sozinho.</span></li>
            <li><span class="font-semibold">Atalho de volta.</span> <span class="text-muted">Cada evento traz o link para abrir a sessão no Iwori.</span></li>
        </ul>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-1">Passo a passo</h2>
        <p class="text-sm text-muted mb-5">Você precisa de uma conta Google (Gmail ou conta do trabalho). As sessões vão para a agenda principal dessa conta.</p>

        <ol class="space-y-5">
            @foreach ($steps as $number => $step)
                <li class="flex gap-4">
                    <span class="size-8 shrink-0 rounded-full bg-brand-tint text-brand text-sm font-semibold flex items-center justify-center">{{ $number }}</span>
                    <div class="min-w-0 pt-1">
                        <p class="text-[15px] font-semibold">{{ $step['title'] }}</p>
                        <p class="mt-0.5 text-sm text-muted">{{ $step['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-4">Ver no celular</h2>
        <ol class="list-decimal pl-5 space-y-2 text-[15px] text-muted">
            <li>Instale o app <span class="font-semibold text-ink">Google Agenda</span> (Android ou iPhone).</li>
            <li>Entre com a mesma conta Google que você conectou ao Iwori.</li>
            <li>Para escolher quanto tempo antes o celular avisa, abra o app, toque no menu e vá em <span class="font-semibold text-ink">Configurações → sua conta → Notificações padrão</span>.</li>
        </ol>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-4">O que vai para o Google</h2>
        <dl class="space-y-4 text-[15px]">
            <div>
                <dt class="font-semibold">Vai</dt>
                <dd class="mt-1 text-muted">
                    As sessões agendadas de hoje em diante, assim que você conecta. Depois, toda sessão nova, reagendada ou arrastada.
                    O evento se chama "Sessão — nome do cliente" e tem o horário e a duração da sessão.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">Sai</dt>
                <dd class="mt-1 text-muted">Sessões canceladas somem do Google. Se você desfizer o cancelamento, ela volta.</dd>
            </div>
            <div>
                <dt class="font-semibold">Não vai</dt>
                <dd class="mt-1 text-muted">
                    Anotações, valores e dados de contato do cliente. Sessões que já passaram também não são enviadas.
                    O cliente não recebe convite: o evento fica só na sua agenda.
                </dd>
            </div>
            <div>
                <dt class="font-semibold">Só num sentido</dt>
                <dd class="mt-1 text-muted">
                    O Iwori manda para o Google, mas não lê de lá. Se você mover ou apagar o evento direto no Google, o Iwori não fica sabendo,
                    e a próxima alteração da sessão no Iwori refaz o evento. Faça as mudanças sempre no Iwori.
                </dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-4">Desconectar</h2>
        <p class="text-[15px] text-muted">
            Clique em <span class="font-semibold text-ink">Desconectar</span> no quadro Google Agenda. O Iwori perde o acesso na hora.
            Os eventos já criados continuam na sua agenda do Google; apague-os por lá se quiser.
            Você também pode tirar o acesso pelo Google, em <span class="font-semibold text-ink">myaccount.google.com → Segurança → Apps de terceiros</span>.
        </p>
    </section>

    <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-4">Se algo não funcionar</h2>
        <dl class="space-y-4 text-[15px]">
            @foreach ($troubleshooting as $problem => $answer)
                <div>
                    <dt class="font-semibold">{{ $problem }}</dt>
                    <dd class="mt-1 text-muted">{{ $answer }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
@endsection
