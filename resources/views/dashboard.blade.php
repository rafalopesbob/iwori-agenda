@extends('layouts.app')

@section('title', 'Painel — Iwori Agenda')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold text-emerald-800">Bem-vindo(a), {{ auth()->user()->name }}!</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('sessions.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium">Nova sessão</a>
        <a href="{{ route('clients.create') }}" class="bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50 px-4 py-2 rounded-lg font-medium">Novo cliente</a>
    </div>
</div>

@if (session('status'))
    <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('billing.index') }}" class="bg-white rounded-2xl shadow p-5 hover:shadow-md transition-shadow">
        <p class="text-sm text-gray-500">Faturamento do mês</p>
        <p class="text-2xl font-semibold text-emerald-700 mt-1">R$ {{ number_format($monthTotal, 2, ',', '.') }}</p>
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
        <h2 class="font-semibold text-emerald-900">Sessões de hoje — {{ today()->locale('pt_BR')->translatedFormat('d \d\e F') }}</h2>
        <a href="{{ route('sessions.index') }}" class="text-sm text-emerald-700 hover:underline font-medium">Ver agenda completa</a>
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
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg font-medium">✓ Realizado</button>
                            </form>
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="no_show">
                                <button type="submit" class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg font-medium">✗ Falta</button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
