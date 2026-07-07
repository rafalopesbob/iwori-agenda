@extends('layouts.app')

@section('title', $client->name . ' — Iwori Agenda')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $client->name }}</h1>
        <a href="{{ route('clients.edit', $client) }}" class="text-indigo-600 hover:underline font-medium">Editar</a>
    </div>

    <dl class="space-y-4 text-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-gray-500">E-mail</dt>
                <dd class="font-medium">{{ $client->email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Telefone</dt>
                <dd class="font-medium">{{ $client->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Valor da sessão</dt>
                <dd class="font-medium">R$ {{ number_format($client->session_value, 2, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Situação</dt>
                <dd class="font-medium">{{ $client->active ? 'Ativo' : 'Inativo' }}</dd>
            </div>
        </div>

        @if ($client->notes)
            <div>
                <dt class="text-gray-500">Anotações</dt>
                <dd class="font-medium whitespace-pre-line">{{ $client->notes }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-8 pt-6 border-t border-gray-100 text-sm text-gray-500">
        Em breve: histórico de sessões e faturamento do cliente.
    </div>

    <div class="mt-6">
        <a href="{{ route('clients.index') }}" class="text-gray-600 hover:underline">&larr; Voltar para a lista</a>
    </div>
</div>
@endsection
