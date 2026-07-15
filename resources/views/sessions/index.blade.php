@extends('layouts.app')

@section('title', 'Agenda Iwori')

@section('content')
@php
    $today = Carbon\CarbonImmutable::today();

    if ($view === 'month') {
        $title = ucfirst($grid['month']->locale('pt_BR')->translatedFormat('F \d\e Y'));
        $focus = $grid['month']->isSameMonth($today) ? $today : $grid['month'];
        $prevParams = ['view' => 'month', 'month' => $grid['month']->subMonth()->format('Y-m')];
        $nextParams = ['view' => 'month', 'month' => $grid['month']->addMonth()->format('Y-m')];
        $todayParams = ['view' => 'month'];
    } elseif ($view === 'week') {
        $weekStart = $week['days'][0];
        $weekEnd = $week['days'][6];
        $title = $weekStart->isSameMonth($weekEnd)
            ? $weekStart->day.' – '.$weekEnd->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y')
            : $weekStart->locale('pt_BR')->translatedFormat('d \d\e M').' – '.$weekEnd->locale('pt_BR')->translatedFormat('d \d\e M \d\e Y');
        $focus = $week['reference'];
        $prevParams = ['view' => 'week', 'date' => $focus->subWeek()->toDateString()];
        $nextParams = ['view' => 'week', 'date' => $focus->addWeek()->toDateString()];
        $todayParams = ['view' => 'week'];
    } else {
        $title = ucfirst($day->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y'));
        $focus = $day;
        $prevParams = ['view' => 'day', 'date' => $day->subDay()->toDateString()];
        $nextParams = ['view' => 'day', 'date' => $day->addDay()->toDateString()];
        $todayParams = ['view' => 'day'];
    }
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="font-display text-2xl font-semibold text-mvindigo">{{ $title }}</h1>

    <div class="flex flex-wrap items-center gap-2">
        {{-- Seletor de visualização --}}
        <div class="flex items-center rounded-xl bg-white border border-gray-200 p-1 text-sm font-medium">
            @foreach (['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'] as $key => $label)
                @php
                    $params = $key === 'month'
                        ? ['view' => 'month', 'month' => $focus->format('Y-m')]
                        : ['view' => $key, 'date' => $focus->toDateString()];
                @endphp
                <a href="{{ route('sessions.index', $params) }}"
                   class="px-3 py-1.5 rounded-lg transition-colors {{ $view === $key ? 'bg-mvteal text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <a href="{{ route('sessions.index', $prevParams) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">&larr;</a>
        <a href="{{ route('sessions.index', $todayParams) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">Hoje</a>
        <a href="{{ route('sessions.index', $nextParams) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">&rarr;</a>
        <a href="{{ route('sessions.create', ['date' => $focus->toDateString()]) }}"
           class="ml-2 bg-mvteal hover:bg-mvteal-dark text-white px-4 py-2 rounded-lg font-medium">Nova sessão</a>
    </div>
</div>

@if (session('status'))
    <div class="bg-mvteal-light border border-mvteal/30 text-mvteal-dark rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

@if ($view === 'month')
    @include('sessions._month')
@elseif ($view === 'week')
    @include('sessions._week')
@else
    @include('sessions._day')
@endif

<div class="flex flex-wrap gap-4 mt-4 text-xs text-gray-600">
    <span><span class="inline-block w-3 h-3 rounded bg-mvlilac align-middle mr-1"></span>Agendado</span>
    <span><span class="inline-block w-3 h-3 rounded bg-mvteal/50 align-middle mr-1"></span>Realizado</span>
    <span><span class="inline-block w-3 h-3 rounded bg-mvrose align-middle mr-1"></span>Falta não informada (cobrada)</span>
    <span><span class="inline-block w-3 h-3 rounded bg-amber-300 align-middle mr-1"></span>Falta informada (abonada)</span>
    <span><span class="inline-block w-3 h-3 rounded bg-gray-200 align-middle mr-1"></span>Cancelado</span>
    @if ($view !== 'day')
        <span class="text-gray-400">Dica: clique num dia vazio para agendar, ou arraste uma sessão agendada para outro dia para reagendar.</span>
    @endif
</div>
@endsection
