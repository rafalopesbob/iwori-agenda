@extends('layouts.app')

@section('title', 'Cobranças — Agenda Iwori')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold text-mvindigo">Cobranças</h1>
</div>

@if (session('status'))
    <div class="bg-mvteal-light border border-mvteal/30 text-mvteal-dark rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

{{-- Totais --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <p class="text-sm text-gray-500">A receber (pendentes)</p>
        <p class="text-2xl font-semibold text-mvrose-dark mt-1">R$ {{ number_format($totals['pending'], 2, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <p class="text-sm text-gray-500">Recebido neste mês</p>
        <p class="text-2xl font-semibold text-mvteal-dark mt-1">R$ {{ number_format($totals['paid_this_month'], 2, ',', '.') }}</p>
    </div>
</div>

{{-- Filtros --}}
<div class="flex flex-wrap items-center gap-3 mb-6">
    <div class="flex rounded-xl overflow-hidden border border-gray-200 bg-white text-sm">
        @foreach ([null => 'Todas', 'pending' => 'Pendentes', 'paid' => 'Pagas'] as $value => $label)
            <a href="{{ route('charges.index', array_filter(['status' => $value, 'client' => $clientId])) }}"
               class="px-4 py-2 font-medium {{ ($status?->value ?? null) === $value ? 'bg-mvteal text-white' : 'text-gray-600 hover:bg-mvsand' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('charges.index') }}" class="flex items-center gap-2">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status->value }}">
        @endif
        <select name="client" onchange="this.form.submit()"
                class="rounded-xl border-gray-200 border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mvteal">
            <option value="">Todos os clientes</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
        @if ($status || $clientId)
            <a href="{{ route('charges.index') }}" class="text-sm text-gray-500 hover:underline">Limpar</a>
        @endif
    </form>
</div>

@if ($charges->isEmpty())
    <div class="bg-white rounded-2xl shadow p-10 text-center text-gray-500">
        Nenhuma cobrança por aqui ainda. Elas aparecem automaticamente quando o ciclo
        de um cliente fecha ou quando você dispara uma cobrança no Faturamento.
    </div>
@else
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-5 py-3 font-medium">Cliente</th>
                        <th class="px-5 py-3 font-medium">Período</th>
                        <th class="px-5 py-3 font-medium text-right">Valor</th>
                        <th class="px-5 py-3 font-medium">Enviada em</th>
                        <th class="px-5 py-3 font-medium">Situação</th>
                        <th class="px-5 py-3 font-medium text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($charges as $charge)
                        <tr x-data="{ open: false }">
                            <td class="px-5 py-3">
                                <span class="font-medium text-gray-800">{{ $charge->client->name }}</span>
                                @if ($charge->client_session_id)
                                    <span class="block text-xs text-gray-400">Cobrança avulsa</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                {{ $charge->period_start->format('d/m/Y') }}
                                @if (! $charge->period_start->isSameDay($charge->period_end))
                                    – {{ $charge->period_end->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-medium text-gray-800">
                                R$ {{ number_format($charge->amount, 2, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                {{ $charge->sent_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $charge->status->badgeClasses() }}">
                                    {{ $charge->status->label() }}
                                </span>
                                @if ($charge->isOverdue())
                                    <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-red-100 text-red-700">Atrasada</span>
                                @endif
                                @if ($charge->paid_at)
                                    <span class="block text-xs text-gray-400 mt-1">em {{ $charge->paid_at->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($charge->receipt_path)
                                    <a href="{{ route('charges.receipt', $charge) }}" target="_blank"
                                       class="text-mvindigo text-xs font-medium hover:underline mr-3">Comprovante</a>
                                @endif

                                @if ($charge->status === App\Enums\ChargeStatus::Pending)
                                    <button type="button" @click="open = true"
                                            class="bg-mvteal hover:bg-mvteal-dark text-white text-xs font-medium px-3 py-1.5 rounded-lg">
                                        Confirmar pagamento
                                    </button>
                                @elseif ($charge->status === App\Enums\ChargeStatus::Paid)
                                    <form method="POST" action="{{ route('charges.reopen', $charge) }}" class="inline"
                                          onsubmit="return confirm('Reabrir esta cobrança como pendente?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-gray-500 text-xs hover:underline">Reabrir</button>
                                    </form>
                                @endif

                                {{-- Modal de confirmação de pagamento --}}
                                <div x-cloak x-show="open" @keydown.escape.window="open = false"
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                    <div class="absolute inset-0 bg-mvindigo/40" @click="open = false"></div>
                                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-left"
                                         x-transition.opacity>
                                        <h3 class="text-lg font-semibold text-mvindigo mb-1">Confirmar pagamento</h3>
                                        <p class="text-sm text-gray-600 mb-4">
                                            {{ $charge->client->name }} — R$ {{ number_format($charge->amount, 2, ',', '.') }}
                                        </p>

                                        <form method="POST" action="{{ route('charges.pay', $charge) }}"
                                              enctype="multipart/form-data" class="space-y-4">
                                            @csrf
                                            @method('PATCH')

                                            <div>
                                                <label class="block text-sm font-medium mb-1" for="paid_at_{{ $charge->id }}">Data do pagamento</label>
                                                <input id="paid_at_{{ $charge->id }}" type="date" name="paid_at"
                                                       value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
                                                       class="w-full rounded-lg border-gray-300 border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mvteal">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium mb-1" for="receipt_{{ $charge->id }}">Comprovante (opcional)</label>
                                                <input id="receipt_{{ $charge->id }}" type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"
                                                       class="w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-mvindigo file:text-white file:text-xs file:font-medium hover:file:bg-mvindigo/90 file:cursor-pointer">
                                                <p class="text-xs text-gray-500 mt-1">PDF, JPG ou PNG, até 5 MB.</p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium mb-1" for="notes_{{ $charge->id }}">Observações</label>
                                                <textarea id="notes_{{ $charge->id }}" name="notes" rows="2" maxlength="1000"
                                                          class="w-full rounded-lg border-gray-300 border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-mvteal"></textarea>
                                            </div>

                                            <div class="flex items-center gap-3 pt-1">
                                                <button type="submit"
                                                        class="bg-mvteal hover:bg-mvteal-dark text-white text-sm font-medium px-4 py-2 rounded-lg">
                                                    Confirmar
                                                </button>
                                                <button type="button" @click="open = false" class="text-gray-600 text-sm hover:underline">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $charges->links() }}
    </div>
@endif

@error('receipt')
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mt-6">{{ $message }}</div>
@enderror
@error('paid_at')
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mt-6">{{ $message }}</div>
@enderror
@endsection
