@extends('layouts.app')

@section('title', 'Reagendar sessão — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink mb-1">Reagendar sessão</h1>
    <p class="flex flex-wrap items-center gap-2 text-sm text-muted mb-6">
        {{ $session->client->name }} —
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
    </p>

    <form method="POST" action="{{ route('sessions.update', $session) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="scheduled_at" class="block text-sm font-medium text-ink mb-1.5">Data e hora *</label>
                <input id="scheduled_at" type="datetime-local" name="scheduled_at" required
                       value="{{ old('scheduled_at', $session->scheduled_at->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @error('scheduled_at')
                    <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="duration_minutes" class="block text-sm font-medium text-ink mb-1.5">Duração (minutos) *</label>
                <input id="duration_minutes" type="number" name="duration_minutes" min="5" max="600"
                       value="{{ old('duration_minutes', $session->duration_minutes) }}" required
                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @error('duration_minutes')
                    <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="value" class="block text-sm font-medium text-ink mb-1.5">Valor (R$)</label>
            <input id="value" type="number" name="value" step="0.01" min="0"
                   value="{{ old('value', $session->value) }}"
                   class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            @error('value')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-ink mb-1.5">Anotações</label>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">{{ old('notes', $session->notes) }}</textarea>
            <p class="text-[13px] text-muted mt-1.5">Armazenadas criptografadas — visíveis apenas para você.</p>
            @error('notes')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                Salvar alterações
            </button>
            <a href="{{ route('sessions.index', ['month' => $session->scheduled_at->format('Y-m')]) }}" class="inline-flex items-center min-h-11 px-2 text-sm font-medium text-muted hover:text-ink">Cancelar</a>
        </div>
    </form>

    @if ($session->isRecurring())
        <div class="mt-8 pt-6 border-t border-line">
            <p class="flex items-center gap-2 text-sm text-muted mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-4 shrink-0 text-brand" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                Esta sessão faz parte de uma série recorrente.
            </p>
            <form method="POST" action="{{ route('sessions.recurrence.cancel', $session) }}"
                  onsubmit="return confirm('Cancelar esta e todas as sessões futuras agendadas desta série? Sessões já realizadas ou com falta não serão alteradas.');">
                @csrf
                <button type="submit" class="inline-flex items-center min-h-11 text-sm font-medium text-danger-ink hover:underline">
                    Cancelar esta e as próximas sessões da série
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
