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

    $legend = [
        App\Enums\SessionStatus::Scheduled,
        App\Enums\SessionStatus::Completed,
        App\Enums\SessionStatus::NoShowUnexcused,
        App\Enums\SessionStatus::NoShowExcused,
        App\Enums\SessionStatus::Canceled,
    ];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
        <h1 class="mr-2 text-[28px] font-semibold tracking-tight">{{ $title }}</h1>
        <a href="{{ route('sessions.index', $prevParams) }}" aria-label="Período anterior"
           class="flex items-center justify-center size-11 rounded-xl border border-line-strong bg-white hover:bg-canvas transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
        </a>
        <a href="{{ route('sessions.index', $nextParams) }}" aria-label="Próximo período"
           class="flex items-center justify-center size-11 rounded-xl border border-line-strong bg-white hover:bg-canvas transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
        </a>
        <a href="{{ route('sessions.index', $todayParams) }}"
           class="inline-flex items-center min-h-11 px-4 rounded-xl border border-line-strong bg-white hover:bg-canvas text-[15px] font-semibold transition-colors">Hoje</a>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        {{-- Seletor de visualização --}}
        <nav aria-label="Visualização" class="flex gap-1 rounded-[14px] bg-brand-tint p-1">
            @foreach (['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'] as $key => $label)
                @php
                    $params = $key === 'month'
                        ? ['view' => 'month', 'month' => $focus->format('Y-m')]
                        : ['view' => $key, 'date' => $focus->toDateString()];
                @endphp
                <a href="{{ route('sessions.index', $params) }}" @if ($view === $key) aria-current="page" @endif
                   class="inline-flex items-center min-h-10 px-[18px] rounded-[10px] text-sm font-semibold transition-colors
                          {{ $view === $key ? 'bg-white text-ink shadow-[0_1px_2px_rgba(28,27,51,0.12)]' : 'text-muted hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <a href="{{ route('sessions.create', ['date' => $focus->toDateString()]) }}"
           class="inline-flex items-center gap-2 min-h-11 px-[18px] rounded-xl bg-brand hover:bg-brand-dark text-white text-[15px] font-semibold transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Nova sessão
        </a>
    </div>
</div>

@if (session('status'))
    <div role="status" class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 mb-5 text-sm font-medium text-accent-ink">
        {{ session('status') }}
    </div>
@endif

@if ($view === 'day')
    @include('sessions._day')
@else
    {{-- Mês e semana: clique numa sessão para ver os detalhes ao lado (abaixo, no celular). --}}
    <div x-data="{ sel: null }" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px] items-start">
        <div class="min-w-0 rounded-[18px] border border-line bg-white overflow-x-auto">
            @if ($view === 'month')
                @include('sessions._month')
            @else
                @include('sessions._week')
            @endif
        </div>

        <aside x-ref="detalhes" aria-label="Detalhes da sessão" class="scroll-mt-20 rounded-[18px] border border-line bg-white p-6">
            <p x-show="sel === null" class="text-[15px] leading-relaxed text-muted">
                Selecione uma sessão para ver os detalhes e registrar a presença.
            </p>
            @foreach ($sessions->flatten(1) as $session)
                <div x-cloak x-show="sel === {{ $session->id }}">
                    @include('sessions._details', ['session' => $session])
                </div>
            @endforeach

            <div class="mt-6 border-t border-line pt-5">
                <p class="text-[13px] font-medium text-muted">Legenda</p>
                <ul class="mt-2.5 space-y-2">
                    @foreach ($legend as $status)
                        <li class="flex items-center gap-2.5 text-sm">
                            <span class="size-3.5 rounded {{ $status->badgeClasses() }} ring-1 ring-inset ring-current/30"></span>
                            {{ $status->label() }}{{ $status === App\Enums\SessionStatus::NoShowUnexcused ? ' (cobrada)' : '' }}{{ $status === App\Enums\SessionStatus::NoShowExcused ? ' (abonada)' : '' }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 rounded-xl bg-canvas px-3.5 py-3 text-[13px] leading-relaxed text-muted">
                    Dica: clique num dia vazio para agendar, ou arraste uma sessão agendada para outro dia para reagendar.
                </p>
            </div>
        </aside>
    </div>
@endif
@endsection
