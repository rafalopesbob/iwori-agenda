@extends('layouts.app')

@section('title', 'Nova sessão — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Nova sessão</h1>

    @if ($clients->isEmpty())
        <p class="text-gray-600">
            Você ainda não tem clientes ativos.
            <a href="{{ route('clients.create') }}" class="text-indigo-600 font-medium hover:underline">Cadastre um cliente</a>
            para poder agendar sessões.
        </p>
    @else
        <form method="POST" action="{{ route('sessions.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="client_id" class="block text-sm font-medium mb-1">Cliente *</label>
                <select id="client_id" name="client_id" required
                        class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @error('scheduled_at')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="duration_minutes" class="block text-sm font-medium mb-1">Duração (minutos) *</label>
                    <input id="duration_minutes" type="number" name="duration_minutes" min="5" max="600"
                           value="{{ old('duration_minutes', 50) }}" required
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @error('duration_minutes')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="value" class="block text-sm font-medium mb-1">Valor (R$)</label>
                <input id="value" type="number" name="value" step="0.01" min="0" value="{{ old('value') }}"
                       placeholder="Em branco, usa o valor de contrato do cliente"
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @error('value')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium mb-1">Anotações</label>
                <textarea id="notes" name="notes" rows="3"
                          class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">Armazenadas criptografadas — visíveis apenas para você.</p>
                @error('notes')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg">
                    Agendar
                </button>
                <a href="{{ route('sessions.index') }}" class="text-gray-600 hover:underline">Cancelar</a>
            </div>
        </form>
    @endif
</div>
@endsection
