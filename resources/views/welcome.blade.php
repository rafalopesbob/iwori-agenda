@extends('layouts.app')

@section('title', 'Agenda Iwori — Gestão de sessões, presenças e faturamento')

@section('content')
<section class="grid lg:grid-cols-[1.1fr_0.9fr] gap-10 items-center py-8 lg:py-16">
    <div>
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-mvteal-dark mb-5">
            Agenda para o desenvolvimento humano
        </p>
        <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-semibold text-mvindigo leading-[1.05] tracking-tight">
            O seu tempo de cuidar,<br>
            <span class="italic text-mvrose-dark">em ordem.</span>
        </h1>
        <p class="text-lg text-gray-600 mt-6 max-w-xl leading-relaxed">
            Sessões, presenças e faturamento num só lugar. Você acompanha cada pessoa
            ao longo das semanas — o Iwori cuida das planilhas, das cobranças e dos lembretes.
        </p>
        <div class="flex flex-wrap items-center gap-3 mt-8">
            @auth
                <a href="{{ route('sessions.index') }}"
                   class="bg-mvteal hover:bg-mvteal-dark text-white px-6 py-3 rounded-xl font-medium text-lg transition-colors">Ir para a agenda</a>
                <a href="{{ route('dashboard') }}"
                   class="text-mvindigo hover:text-mvteal-dark px-2 py-3 font-medium text-lg">Meu painel &rarr;</a>
            @else
                <a href="{{ route('register') }}"
                   class="bg-mvteal hover:bg-mvteal-dark text-white px-6 py-3 rounded-xl font-medium text-lg transition-colors">Começar agora</a>
                <a href="{{ route('login') }}"
                   class="text-mvindigo hover:text-mvteal-dark px-2 py-3 font-medium text-lg">Entrar &rarr;</a>
            @endauth
        </div>
    </div>

    {{-- Assinatura: portal de arcos concêntricos, na arquitetura impossível de
         Monument Valley — um limiar, ecoando Ìwòrì (visão profunda, travessia). --}}
    <div class="hidden lg:flex justify-center">
        <svg viewBox="0 0 320 360" class="w-full max-w-sm" role="img"
             aria-label="Portal de arcos concêntricos">
            <defs>
                <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#55527f"/>
                    <stop offset="1" stop-color="#3c3a68"/>
                </linearGradient>
            </defs>

            @php
                // Arcos aninhados: cada camada recua e muda de cor da paleta.
                $arches = [
                    ['w' => 300, 'fill' => 'url(#sky)'],
                    ['w' => 232, 'fill' => '#b7b2e2'],
                    ['w' => 168, 'fill' => '#14a3a1'],
                    ['w' => 108, 'fill' => '#e08fa9'],
                    ['w' => 56,  'fill' => '#f6efe2'],
                ];
            @endphp

            @foreach ($arches as $arch)
                @php $x = (320 - $arch['w']) / 2; $r = $arch['w'] / 2; @endphp
                <path d="M{{ $x }},340 V{{ 340 - $r }} A{{ $r }},{{ $r }} 0 0 1 {{ $x + $arch['w'] }},{{ 340 - $r }} V340 Z"
                      fill="{{ $arch['fill'] }}"/>
            @endforeach

            {{-- Sol/lua no vão central, com respiro suave --}}
            <circle cx="160" cy="150" r="16" fill="#f6efe2" class="iwori-orb"/>
        </svg>
    </div>
</section>

<section class="border-t border-mvsand-dark/60 pt-10 pb-12">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <div>
            <div class="w-10 h-10 rounded-xl bg-mvteal-light flex items-center justify-center text-xl">🗓️</div>
            <h2 class="font-display text-lg font-semibold text-mvindigo mt-3">Calendário dinâmico</h2>
            <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">
                Veja o dia, a semana ou o mês e marque presença com um clique — arraste para reagendar.
            </p>
        </div>
        <div>
            <div class="w-10 h-10 rounded-xl bg-mvrose-light flex items-center justify-center text-xl">👥</div>
            <h2 class="font-display text-lg font-semibold text-mvindigo mt-3">Gestão de clientes</h2>
            <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">
                Perfis de pacientes e alunos com valor de contrato, ciclo de pagamento e histórico.
            </p>
        </div>
        <div>
            <div class="w-10 h-10 rounded-xl bg-mvsand-dark flex items-center justify-center text-xl">💰</div>
            <h2 class="font-display text-lg font-semibold text-mvindigo mt-3">Faturamento inteligente</h2>
            <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">
                Fechamento mensal automático, cobrando só o que houve — com aviso por e-mail ou WhatsApp.
            </p>
        </div>
        <div>
            <div class="w-10 h-10 rounded-xl bg-mvlilac-light flex items-center justify-center text-xl">🔒</div>
            <h2 class="font-display text-lg font-semibold text-mvindigo mt-3">Dados protegidos</h2>
            <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">
                Anotações criptografadas e acesso isolado — cada profissional enxerga apenas os seus.
            </p>
        </div>
    </div>
</section>
@endsection
