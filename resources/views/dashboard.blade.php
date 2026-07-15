@extends('layouts.app')

@section('title', 'Painel — Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="font-display text-2xl font-semibold text-mvindigo">Bem-vindo(a), {{ auth()->user()->name }}!</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('sessions.create') }}" class="bg-mvteal hover:bg-mvteal-dark text-white px-4 py-2 rounded-lg font-medium">Nova sessão</a>
        <a href="{{ route('clients.create') }}" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-medium">Novo cliente</a>
    </div>
</div>

@if (session('status'))
    <div class="bg-mvteal-light border border-mvteal/30 text-mvteal-dark rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

@if (config('services.google.client_id'))
    <div class="bg-white rounded-2xl shadow p-5 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="text-2xl">📅</span>
            <div>
                <p class="font-medium text-gray-900">Google Calendar</p>
                @if (auth()->user()->hasGoogleCalendar())
                    <p class="text-sm text-mvteal-dark">Conectado — novas sessões aparecem no seu calendário.</p>
                @else
                    <p class="text-sm text-gray-500">Conecte para espelhar suas sessões no calendário do Google.</p>
                @endif
            </div>
        </div>

        @if (auth()->user()->hasGoogleCalendar())
            <form method="POST" action="{{ route('google.disconnect') }}">
                @csrf
                <button type="submit" class="text-sm text-mvrose-dark hover:underline font-medium">Desconectar</button>
            </form>
        @else
            <a href="{{ route('google.connect') }}"
               class="bg-mvteal hover:bg-mvteal-dark text-white px-4 py-2 rounded-lg font-medium text-sm">Conectar</a>
        @endif
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('billing.index') }}" class="bg-white rounded-2xl shadow p-5 hover:shadow-md transition-shadow">
        <p class="text-sm text-gray-500">Faturamento do mês</p>
        <p class="text-2xl font-semibold text-mvteal-dark mt-1">R$ {{ number_format($monthTotal, 2, ',', '.') }}</p>
    </a>
    <a href="{{ route('billing.index') }}" class="bg-white rounded-2xl shadow p-5 hover:shadow-md transition-shadow">
        <p class="text-sm text-gray-500">Sessões realizadas no mês</p>
        <p class="text-2xl font-semibold text-gray-800 mt-1">{{ $monthCompleted }}</p>
    </a>
    <a href="{{ route('clients.index') }}" class="bg-white rounded-2xl shadow p-5 hover:shadow-md transition-shadow">
        <p class="text-sm text-gray-500">Clientes ativos</p>
        <p class="text-2xl font-semibold text-gray-800 mt-1">{{ $activeClients }}</p>
    </a>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="flex items-center justify-between px-6 pt-5 pb-3">
        <h2 class="font-semibold text-mvindigo">Sessões de hoje — {{ today()->locale('pt_BR')->translatedFormat('d \d\e F') }}</h2>
        <a href="{{ route('sessions.index') }}" class="text-sm text-mvteal-dark hover:underline font-medium">Ver agenda completa</a>
    </div>

    @if ($todaySessions->isEmpty())
        <p class="px-6 pb-6 text-gray-500">Nenhuma sessão agendada para hoje.</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($todaySessions as $session)
                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-gray-800 tabular-nums">{{ $session->scheduled_at->format('H:i') }}</span>
                        <span class="text-gray-700">{{ $session->client->name }}</span>
                        <span class="text-xs font-medium px-2 py-1 rounded-full {{ $session->status->badgeClasses() }}">
                            {{ $session->status->label() }}
                        </span>
                    </div>

                    @if ($session->status === App\Enums\SessionStatus::Scheduled)
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="text-xs bg-mvteal hover:bg-mvteal-dark text-white px-3 py-1.5 rounded-lg font-medium">✓ Realizado</button>
                            </form>
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="no_show">
                                <button type="submit" title="Falta cobrada" class="text-xs bg-mvrose-dark hover:bg-mvrose text-white px-3 py-1.5 rounded-lg font-medium">✗ Falta (não avisou)</button>
                            </form>
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="no_show_excused">
                                <button type="submit" title="Falta abonada" class="text-xs bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg font-medium">Falta (avisou)</button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
