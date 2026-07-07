@extends('layouts.app')

@section('title', 'Clientes — Agenda Iwori')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold text-gray-900">Clientes</h1>
    <a href="{{ route('clients.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium">
        Novo cliente
    </a>
</div>

@if (session('status'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

<div class="bg-white rounded-2xl shadow overflow-hidden">
    @if ($clients->isEmpty())
        <p class="p-8 text-gray-500 text-center">
            Nenhum cliente cadastrado ainda.
            <a href="{{ route('clients.create') }}" class="text-indigo-600 font-medium hover:underline">Cadastre o primeiro</a>.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-700 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nome</th>
                        <th class="px-4 py-3 font-medium">Contato</th>
                        <th class="px-4 py-3 font-medium">Valor da sessão</th>
                        <th class="px-4 py-3 font-medium">Situação</th>
                        <th class="px-4 py-3 font-medium text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($clients as $client)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('clients.show', $client) }}" class="font-medium text-indigo-600 hover:underline">
                                    {{ $client->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $client->phone ?? $client->email ?? '—' }}
                            </td>
                            <td class="px-4 py-3">R$ {{ number_format($client->session_value, 2, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                @if ($client->active)
                                    <span class="bg-emerald-100 text-emerald-800 text-xs font-medium px-2 py-1 rounded-full">Ativo</span>
                                @else
                                    <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2 py-1 rounded-full">Inativo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('clients.edit', $client) }}" class="text-indigo-600 hover:underline font-medium mr-3">Editar</a>
                                <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline"
                                      onsubmit="return confirm('Remover o cliente {{ $client->name }}? O histórico de sessões será preservado.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline font-medium">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $clients->links() }}
        </div>
    @endif
</div>
@endsection
