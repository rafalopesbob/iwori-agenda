@extends('layouts.app')

@section('title', 'Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold text-mvindigo capitalize">
        {{ $grid['month']->locale('pt_BR')->translatedFormat('F \d\e Y') }}
    </h1>

    <div class="flex items-center gap-2">
        <a href="{{ route('sessions.index', ['month' => $grid['month']->subMonth()->format('Y-m')]) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">&larr;</a>
        <a href="{{ route('sessions.index') }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">Hoje</a>
        <a href="{{ route('sessions.index', ['month' => $grid['month']->addMonth()->format('Y-m')]) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">&rarr;</a>
        <a href="{{ route('sessions.create') }}"
           class="ml-2 bg-mvteal hover:bg-mvteal-dark text-white px-4 py-2 rounded-lg font-medium">Nova sessão</a>
    </div>
</div>

@if (session('status'))
    <div class="bg-mvteal-light border border-mvteal/30 text-mvteal-dark rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="grid grid-cols-7 bg-gray-50 text-gray-700 text-xs font-medium text-center">
        @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
            <div class="py-2">{{ $weekday }}</div>
        @endforeach
    </div>

    @foreach ($grid['weeks'] as $week)
        <div class="grid grid-cols-7 divide-x divide-gray-100 border-t border-gray-100">
            @foreach ($week as $day)
                @php
                    $isCurrentMonth = $day->isSameMonth($grid['month']);
                    $daySessions = $sessions->get($day->toDateString(), collect());
                @endphp
                <div class="min-h-28 p-1.5 {{ $isCurrentMonth ? '' : 'bg-gray-50' }}">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium px-1.5 py-0.5 rounded-full
                            {{ $day->isToday() ? 'bg-mvrose-dark text-white' : ($isCurrentMonth ? 'text-gray-700' : 'text-gray-400') }}">
                            {{ $day->day }}
                        </span>
                        <a href="{{ route('sessions.create', ['date' => $day->toDateString()]) }}"
                           class="text-gray-300 hover:text-mvteal text-sm leading-none" title="Agendar neste dia">+</a>
                    </div>

                    <div class="space-y-1">
                        @foreach ($daySessions as $session)
                            <div class="rounded-lg px-1.5 py-1 text-xs {{ $session->status->badgeClasses() }}">
                                <div class="font-medium truncate" title="{{ $session->client->name }} — {{ $session->status->label() }}">
                                    {{ $session->scheduled_at->format('H:i') }} {{ $session->client->name }}
                                </div>

                                @if ($session->status === App\Enums\SessionStatus::Scheduled)
                                    <div class="flex gap-1 mt-1">
                                        <form method="POST" action="{{ route('sessions.status', $session) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" title="Marcar como Realizado"
                                                    class="w-5 h-5 rounded bg-mvteal text-white hover:bg-mvteal-dark leading-none">✓</button>
                                        </form>
                                        <form method="POST" action="{{ route('sessions.status', $session) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="no_show">
                                            <button type="submit" title="Marcar como Falta"
                                                    class="w-5 h-5 rounded bg-mvrose-dark text-white hover:bg-mvrose leading-none">✗</button>
                                        </form>
                                        <form method="POST" action="{{ route('sessions.status', $session) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="canceled">
                                            <button type="submit" title="Cancelar sessão"
                                                    class="w-5 h-5 rounded bg-gray-400 text-white hover:bg-gray-500 leading-none">–</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>

<div class="flex flex-wrap gap-4 mt-4 text-xs text-gray-600">
    <span><span class="inline-block w-3 h-3 rounded bg-mvlilac align-middle mr-1"></span>Agendado</span>
    <span><span class="inline-block w-3 h-3 rounded bg-mvteal/50 align-middle mr-1"></span>Realizado</span>
    <span><span class="inline-block w-3 h-3 rounded bg-mvrose align-middle mr-1"></span>Falta</span>
    <span><span class="inline-block w-3 h-3 rounded bg-gray-200 align-middle mr-1"></span>Cancelado</span>
</div>
@endsection
