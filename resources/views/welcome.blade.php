@extends('layouts.app')

@section('title', 'Agenda Iwori — Gestão de sessões, presenças e faturamento')
@section('bleed', true)

@section('content')
@php
    // Prévia ilustrativa do painel no hero (dados de exemplo, não do usuário).
    $preview = [
        ['08:00', 'Ana Beatriz Souza', 'Realizada', 'bg-accent-tint text-accent-ink'],
        ['10:00', 'Carlos Mendes', 'Agendada', 'bg-brand-tint text-brand'],
        ['14:00', 'Juliana Prado', 'Falta c/ aviso', 'bg-warn-tint text-warn-ink'],
        ['16:30', 'Pedro Henrique Alves', 'Agendada', 'bg-brand-tint text-brand'],
    ];
    $audiences = [
        'psi' => ['Psicologia e terapias', 'Sessões semanais, faltas avisadas ou não e o fechamento do mês sem planilha.', ['Falta com aviso é abonada; sem aviso, entra na cobrança', 'Anotações de cada sessão ficam criptografadas', 'Lembrete por e-mail a cada agendamento']],
        'arte' => ['Aulas e instrução artística', 'Alunos com valor por aula, presença marcada em um clique e cobrança por ciclo.', ['Aulas recorrentes criadas de uma vez', 'Ciclo semanal, mensal ou por intervalo de dias', 'Cobrança por e-mail ou WhatsApp']],
        'grupo' => ['Grupos de estudo', 'Encontros recorrentes com a presença de cada participante sempre à mão.', ['Agenda de dia, semana ou mês', 'Reagende arrastando para outro dia', 'Sessões espelhadas no Google Agenda']],
        'coach' => ['Mentoria e acompanhamento', 'Acompanhamentos por ciclo, com o histórico de cada pessoa no mesmo lugar.', ['Valor de contrato por cliente', 'Cobranças pendentes e pagas separadas', 'Comprovante anexado a cada pagamento']],
    ];
@endphp

<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-10 pb-20 lg:pt-14 lg:pb-24 grid lg:grid-cols-[1.05fr_0.95fr] gap-12 lg:gap-14 items-center">
    <div>
        <p class="inline-flex rounded-full bg-brand-tint px-3.5 py-1.5 text-[13px] font-semibold tracking-wide text-brand">
            Agenda para o desenvolvimento humano
        </p>
        <h1 class="mt-6 text-[44px] sm:text-[56px] lg:text-[60px] font-semibold leading-[1.04] tracking-[-0.035em]">
            O seu tempo de cuidar, <span class="text-accent">em ordem.</span>
        </h1>
        <p class="mt-6 max-w-xl text-lg sm:text-[19px] leading-relaxed text-muted">
            Sessões, presenças e faturamento num só lugar. Você acompanha cada pessoa
            ao longo das semanas — o Iwori cuida das planilhas, das cobranças e dos lembretes.
        </p>
        <div class="mt-9 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('sessions.index') }}"
                   class="inline-flex items-center gap-2.5 min-h-[52px] px-[26px] rounded-full bg-brand hover:bg-brand-dark text-white text-base font-semibold transition-colors">
                    Ir para a agenda
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center min-h-[52px] px-[26px] rounded-full border border-line-strong bg-white hover:bg-canvas text-base font-semibold text-ink transition-colors">Meu painel</a>
            @else
                <a href="{{ route('register') }}"
                   class="inline-flex items-center gap-2.5 min-h-[52px] px-[26px] rounded-full bg-brand hover:bg-brand-dark text-white text-base font-semibold transition-colors">
                    Começar agora
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="#como-funciona"
                   class="inline-flex items-center min-h-[52px] px-[26px] rounded-full border border-line-strong bg-white hover:bg-canvas text-base font-semibold text-ink transition-colors">Ver como funciona</a>
            @endauth
        </div>
        <p class="mt-6 flex items-center gap-2 text-sm text-muted">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 text-accent" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Anotações criptografadas e acesso só seu.
        </p>
    </div>

    {{-- Prévia do painel --}}
    <div aria-hidden="true" class="rounded-3xl border border-line bg-white p-7 shadow-[0_32px_64px_-32px_rgba(28,27,51,0.22)]">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <p class="text-[13px] font-medium text-muted">Quarta, 4 de novembro</p>
                <p class="mt-1 text-[22px] font-semibold tracking-tight">Hoje</p>
            </div>
            <p class="inline-flex items-center gap-1.5 rounded-full bg-accent-tint px-2.5 py-1.5 text-xs font-semibold text-accent-ink">
                <span class="size-1.5 rounded-full bg-accent"></span>Google Agenda sincronizada
            </p>
        </div>
        <div class="mt-5">
            @foreach ($preview as [$time, $name, $label, $classes])
                <div class="flex items-center gap-4 border-t border-line/70 py-3.5">
                    <span class="w-12 text-sm tabular-nums text-muted">{{ $time }}</span>
                    <span class="flex-1 text-[15px] font-medium">{{ $name }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $classes }}">{{ $label }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex items-end justify-between gap-4 border-t border-line/70 pt-5">
            <div>
                <p class="text-[13px] text-muted">Faturado em novembro</p>
                <p class="mt-1 text-[28px] font-semibold tracking-tight tabular-nums">R$ 4.180,00</p>
            </div>
            <div class="flex h-12 items-end gap-1.5">
                @foreach ([22, 34, 28, 40] as $height)
                    <span class="w-2.5 rounded bg-line" style="height: {{ $height }}px"></span>
                @endforeach
                <span class="h-12 w-2.5 rounded bg-brand"></span>
            </div>
        </div>
    </div>
</section>

<section id="recursos" class="scroll-mt-6 border-y border-line bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-24">
        <h2 class="max-w-2xl text-[32px] sm:text-[38px] font-semibold leading-tight tracking-tight">Tudo o que um acompanhamento contínuo pede</h2>
        <p class="mt-4 max-w-xl text-lg leading-relaxed text-muted">Do primeiro agendamento ao fechamento do mês, sem planilhas paralelas.</p>

        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Calendário dinâmico', 'Veja o dia, a semana ou o mês e marque presença com um clique — arraste para reagendar.', 'bg-brand-tint text-brand', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                ['Gestão de clientes', 'Perfis de pacientes e alunos com valor de contrato, ciclo de pagamento e histórico.', 'bg-accent-tint text-accent-ink', 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
                ['Faturamento inteligente', 'Fechamento mensal automático, cobrando só o que houve — com aviso por e-mail ou WhatsApp.', 'bg-danger-tint text-danger-ink', 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ['Dados protegidos', 'Anotações criptografadas e acesso isolado — cada profissional enxerga apenas os seus.', 'bg-neutral-tint text-ink', 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z'],
            ] as [$title, $text, $tone, $icon])
                <div class="rounded-[20px] border border-line p-7">
                    <div class="flex size-11 items-center justify-center rounded-xl {{ $tone }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-[22px]" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold tracking-tight">{{ $title }}</h3>
                    <p class="mt-2 text-[15px] leading-relaxed text-muted">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section id="como-funciona" class="scroll-mt-6 max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-24">
    <h2 class="text-[32px] sm:text-[38px] font-semibold leading-tight tracking-tight">Como funciona</h2>
    <ol class="mt-12 grid gap-10 md:grid-cols-3">
        @foreach ([
            ['bg-brand', 'Cadastre clientes e valores', 'Valor da sessão, ciclo de pagamento semanal, mensal ou por intervalo, e onde cobrar.'],
            ['bg-accent', 'Marque presença com um clique', 'Realizada, falta com ou sem aviso: cada status já entra na conta do mês.'],
            ['bg-danger', 'Feche o mês e cobre', 'O Iwori soma o que houve e envia a cobrança. Você confirma o pagamento e guarda o comprovante.'],
        ] as $index => [$tone, $title, $text])
            <li>
                <span class="inline-flex size-10 items-center justify-center rounded-full {{ $tone }} font-semibold text-white">{{ $index + 1 }}</span>
                <h3 class="mt-5 text-xl font-semibold tracking-tight">{{ $title }}</h3>
                <p class="mt-2 text-base leading-relaxed text-muted">{{ $text }}</p>
            </li>
        @endforeach
    </ol>
</section>

<section id="para-quem" class="scroll-mt-6 border-y border-line bg-white" x-data="{ audience: 'psi' }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-24 grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
        <div>
            <h2 class="text-[32px] sm:text-[38px] font-semibold leading-tight tracking-tight">Feito para quem acompanha pessoas</h2>
            <div role="group" aria-label="Escolha um perfil" class="mt-8 flex flex-wrap gap-2">
                @foreach ($audiences as $key => [$label])
                    <button type="button" @click="audience = '{{ $key }}'" :aria-pressed="(audience === '{{ $key }}').toString()"
                            :class="audience === '{{ $key }}' ? 'bg-brand border-brand text-white' : 'bg-white border-line-strong text-ink hover:bg-canvas'"
                            class="min-h-11 px-[18px] rounded-full border text-[15px] font-semibold transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="rounded-3xl border border-line bg-canvas p-8 sm:p-9">
            @foreach ($audiences as $key => [$label, $text, $items])
                <div x-show="audience === '{{ $key }}'" @if (! $loop->first) x-cloak @endif>
                    <p class="text-[13px] font-semibold uppercase tracking-wider text-accent-ink">{{ $label }}</p>
                    <p class="mt-3 text-2xl font-medium leading-snug tracking-tight">{{ $text }}</p>
                    <ul class="mt-6 space-y-3">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-3 text-base leading-normal text-ink/85">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mt-0.5 size-5 shrink-0 text-accent" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="max-w-6xl mx-auto px-4 sm:px-6 py-20 lg:py-24">
    <div class="flex flex-wrap items-center justify-between gap-8 rounded-[28px] bg-brand px-8 py-12 sm:px-12 sm:py-14">
        <div class="max-w-xl">
            <h2 class="text-[30px] sm:text-[36px] font-semibold leading-tight tracking-tight text-white">Comece a organizar sua semana hoje</h2>
            <p class="mt-3 text-[17px] leading-relaxed text-[#d8d7ec]">Cadastre o primeiro cliente e veja o mês se fechar sozinho.</p>
        </div>
        @auth
            <a href="{{ route('clients.create') }}" class="inline-flex items-center min-h-[52px] px-7 rounded-full bg-white text-base font-semibold text-brand hover:bg-brand-tint transition-colors">Cadastrar cliente</a>
        @else
            <a href="{{ route('register') }}" class="inline-flex items-center min-h-[52px] px-7 rounded-full bg-white text-base font-semibold text-brand hover:bg-brand-tint transition-colors">Criar conta</a>
        @endauth
    </div>
</section>
@endsection
