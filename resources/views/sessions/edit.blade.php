@extends('layouts.app')

@section('title', 'Reagendar sessão — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-8">
    <h1 class="font-display text-2xl font-semibold text-mvindigo mb-1">Reagendar sessão</h1>
    <p class="text-gray-600 mb-6">
        {{ $session->client->name }} —
        <span class="text-xs font-medium px-2 py-1 rounded-full {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
    </p>

    <form method="POST" action="{{ route('sessions.update', $session) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="scheduled_at" class="block text-sm font-medium mb-1">Data e hora *</label>
                <input id="scheduled_at" type="datetime-local" name="scheduled_at" required
                       value="{{ old('scheduled_at', $session->scheduled_at->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @error('scheduled_at')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="duration_minutes" class="block text-sm font-medium mb-1">Duração (minutos) *</label>
                <input id="duration_minutes" type="number" name="duration_minutes" min="5" max="600"
                       value="{{ old('duration_minutes', $session->duration_minutes) }}" required
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @error('duration_minutes')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="value" class="block text-sm font-medium mb-1">Valor (R$)</label>
            <input id="value" type="number" name="value" step="0.01" min="0"
                   value="{{ old('value', $session->value) }}"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
            @error('value')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium mb-1">Anotações</label>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">{{ old('notes', $session->notes) }}</textarea>
            <p class="text-xs text-gray-500 mt-1">Armazenadas criptografadas — visíveis apenas para você.</p>
            @error('notes')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-mvteal hover:bg-mvteal-dark text-white font-medium px-5 py-2.5 rounded-lg">
                Salvar alterações
            </button>
            <a href="{{ route('sessions.index', ['month' => $session->scheduled_at->format('Y-m')]) }}" class="text-gray-600 hover:underline">Cancelar</a>
        </div>
    </form>

    @if ($session->isRecurring())
        <div class="mt-6 pt-6 border-t border-gray-100">
            <p class="text-sm text-gray-600 mb-2">
                🔁 Esta sessão faz parte de uma série recorrente.
            </p>
            <form method="POST" action="{{ route('sessions.recurrence.cancel', $session) }}"
                  onsubmit="return confirm('Cancelar esta e todas as sessões futuras agendadas desta série? Sessões já realizadas ou com falta não serão alteradas.');">
                @csrf
                <button type="submit" class="text-sm text-mvrose-dark hover:underline font-medium">
                    Cancelar esta e as próximas sessões da série
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
