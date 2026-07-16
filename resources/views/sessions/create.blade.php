@extends('layouts.app')

@section('title', 'Nova sessão — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-mvindigo mb-6">Nova sessão</h1>

    @if ($clients->isEmpty())
        <p class="text-gray-600">
            Você ainda não tem clientes ativos.
            <a href="{{ route('clients.create') }}" class="text-mvteal-dark font-medium hover:underline">Cadastre um cliente</a>
            para poder agendar sessões.
        </p>
    @else
        <form method="POST" action="{{ route('sessions.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="client_id" class="block text-sm font-medium mb-1">Cliente *</label>
                <select id="client_id" name="client_id" required
                        class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                    <option value="">Selecione…</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                            {{ $client->name }} — R$ {{ number_format($client->session_value, 2, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="scheduled_at" class="block text-sm font-medium mb-1">Data e hora *</label>
                    <input id="scheduled_at" type="datetime-local" name="scheduled_at" required
                           value="{{ old('scheduled_at', $presetDate ? $presetDate.'T09:00' : '') }}"
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                    @error('scheduled_at')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="duration_minutes" class="block text-sm font-medium mb-1">Duração (minutos) *</label>
                    <input id="duration_minutes" type="number" name="duration_minutes" min="5" max="600"
                           value="{{ old('duration_minutes', 50) }}" required
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                    @error('duration_minutes')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="value" class="block text-sm font-medium mb-1">Valor (R$)</label>
                <input id="value" type="number" name="value" step="0.01" min="0" value="{{ old('value') }}"
                       placeholder="Em branco, usa o valor de contrato do cliente"
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @error('value')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium mb-1">Anotações</label>
                <textarea id="notes" name="notes" rows="3"
                          class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">{{ old('notes') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">Armazenadas criptografadas — visíveis apenas para você.</p>
                @error('notes')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <fieldset class="border border-gray-200 rounded-xl p-4 space-y-4"
                      x-data="{ recurrence: '{{ old('recurrence') }}' }">
                <legend class="text-sm font-semibold text-mvindigo px-2">Repetir sessão</legend>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="recurrence" class="block text-sm font-medium mb-1">Frequência</label>
                        <select id="recurrence" name="recurrence" x-model="recurrence"
                                class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                            <option value="">Não repetir</option>
                            @foreach (App\Enums\RecurrenceFrequency::cases() as $frequency)
                                <option value="{{ $frequency->value }}" @selected(old('recurrence') === $frequency->value)>
                                    {{ $frequency->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('recurrence')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-cloak x-show="recurrence !== ''"
                         x-transition:enter="transition ease-out duration-200 origin-top"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-y-90"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-y-100"
                         x-transition:leave="transition ease-in duration-150 origin-top"
                         x-transition:leave-start="opacity-100 scale-y-100"
                         x-transition:leave-end="opacity-0 scale-y-90">
                        <label for="recurrence_count" class="block text-sm font-medium mb-1">Quantas vezes?</label>
                        <input id="recurrence_count" type="number" name="recurrence_count" min="2" max="52"
                               value="{{ old('recurrence_count', 4) }}"
                               class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                        @error('recurrence_count')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div x-cloak x-show="recurrence === 'custom'"
                     x-transition:enter="transition ease-out duration-200 origin-top"
                     x-transition:enter-start="opacity-0 -translate-y-1 scale-y-90"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-y-100"
                     x-transition:leave="transition ease-in duration-150 origin-top"
                     x-transition:leave-start="opacity-100 scale-y-100"
                     x-transition:leave-end="opacity-0 scale-y-90">
                    <label for="recurrence_custom_days" class="block text-sm font-medium mb-1">A cada quantos dias?</label>
                    <input id="recurrence_custom_days" type="number" name="recurrence_custom_days" min="1" max="365"
                           value="{{ old('recurrence_custom_days') }}" placeholder="Ex.: 10"
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                    @error('recurrence_custom_days')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <p class="text-xs text-gray-500">
                    Cria as sessões futuras de uma vez, no mesmo horário. Você pode cancelar as
                    ocorrências restantes depois, a qualquer momento.
                </p>
            </fieldset>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-mvteal hover:bg-mvteal-dark text-white font-medium px-5 py-2.5 rounded-lg">
                    Agendar
                </button>
                <a href="{{ route('sessions.index') }}" class="text-gray-600 hover:underline">Cancelar</a>
            </div>
        </form>
    @endif
</div>
@endsection
