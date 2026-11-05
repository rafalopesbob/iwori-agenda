@extends('layouts.app')

@section('title', 'Clientes — Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">Clientes</h1>
    <a href="{{ route('clients.create') }}"
       class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Novo cliente
    </a>
</div>

@if (session('status'))
    <div role="status" class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink mb-6">
        {{ session('status') }}
    </div>
@endif

<div class="bg-white rounded-2xl border border-line overflow-hidden">
    @if ($clients->isEmpty())
        <p class="p-10 text-center text-muted">
            Nenhum cliente cadastrado ainda.
            <a href="{{ route('clients.create') }}" class="font-medium text-brand hover:underline">Cadastre o primeiro</a>.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[13px] text-muted">
                        <th class="px-5 py-3 font-medium border-b border-line">Nome</th>
                        <th class="px-5 py-3 font-medium border-b border-line">Contato</th>
                        <th class="px-5 py-3 font-medium border-b border-line">Valor da sessão</th>
                        <th class="px-5 py-3 font-medium border-b border-line">Situação</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($clients as $client)
                        <tr class="hover:bg-canvas transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('clients.show', $client) }}" class="font-medium text-brand hover:underline">
                                    {{ $client->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3.5 text-muted">
                                {{ $client->phone ?? $client->email ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-ink tabular-nums whitespace-nowrap">R$ {{ number_format($client->session_value, 2, ',', '.') }}</td>
                            <td class="px-5 py-3.5">
                                @if ($client->active)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-accent-tint text-accent-ink">Ativo</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-neutral-tint text-muted">Inativo</span>
                                @endif
                            </td>
                            <td class="px-5 py-2 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <a href="{{ route('clients.edit', $client) }}"
                                       class="inline-flex items-center min-h-10 px-2 font-medium text-brand hover:underline">Editar</a>
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline"
                                          onsubmit="return confirm(@js('Remover o cliente '.$client->name.'? O histórico de sessões será preservado.'));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center min-h-10 px-2 font-medium text-danger-ink hover:underline cursor-pointer">Remover</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($clients->hasPages())
            <div class="px-5 py-4 border-t border-line">
                {{ $clients->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
