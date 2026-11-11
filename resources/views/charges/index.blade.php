@extends('layouts.app')

@section('title', 'Cobranças — Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">Cobranças</h1>
</div>

@if (session('status'))
    <div role="status" class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink mb-6">
        {{ session('status') }}
    </div>
@endif

{{-- Totais --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-line p-5">
        <p class="text-sm font-medium text-muted">A receber (pendentes)</p>
        <p class="mt-2 text-[30px] leading-none font-semibold tracking-tight tabular-nums text-ink">R$ {{ number_format($totals['pending'], 2, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-line p-5">
        <p class="text-sm font-medium text-muted">Recebido neste mês</p>
        <p class="mt-2 text-[30px] leading-none font-semibold tracking-tight tabular-nums text-accent-ink">R$ {{ number_format($totals['paid_this_month'], 2, ',', '.') }}</p>
    </div>
</div>

{{-- Filtros --}}
<div class="flex flex-wrap items-center gap-3 mb-6">
    <nav class="inline-flex rounded-xl bg-brand-tint p-1 gap-1" aria-label="Filtrar por situação">
        @foreach ([null => 'Todas', 'pending' => 'Pendentes', 'paid' => 'Pagas'] as $value => $label)
            {{-- A chave null do array vira '' no PHP; por isso o "Todas" compara com ''. --}}
            @php($isActive = ($status?->value ?? '') === $value)
            <a href="{{ route('charges.index', array_filter(['status' => $value, 'client' => $clientId])) }}"
               @if ($isActive) aria-current="page" @endif
               class="inline-flex items-center min-h-9 px-4 rounded-lg text-sm font-semibold transition-colors {{ $isActive ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('charges.index') }}" class="flex flex-wrap items-center gap-2">
        @if ($status)
            <input type="hidden" name="status" value="{{ $status->value }}">
        @endif
        <select name="client" onchange="this.form.submit()" aria-label="Filtrar por cliente"
                class="rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            <option value="">Todos os clientes</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
        @if ($status || $clientId)
            <a href="{{ route('charges.index') }}"
               class="inline-flex items-center min-h-11 px-2 text-sm font-medium text-brand hover:underline">Limpar</a>
        @endif
    </form>
</div>

@if ($charges->isEmpty())
    <div class="bg-white rounded-2xl border border-line p-10 text-center text-muted">
        Nenhuma cobrança por aqui ainda. Elas aparecem automaticamente quando o ciclo
        de um cliente fecha ou quando você dispara uma cobrança no Faturamento.
    </div>
@else
    <div class="bg-white rounded-2xl border border-line overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[13px] text-muted">
                        <th class="px-5 py-3 font-medium border-b border-line">Cliente</th>
                        <th class="px-5 py-3 font-medium border-b border-line">Período</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-right">Valor</th>
                        <th class="px-5 py-3 font-medium border-b border-line">Enviada em</th>
                        <th class="px-5 py-3 font-medium border-b border-line">Situação</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($charges as $charge)
                        <tr x-data="{ open: false }" class="hover:bg-canvas transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-medium text-ink">{{ $charge->client->name }}</span>
                                @if ($charge->client_session_id)
                                    <span class="block text-[13px] text-muted">Cobrança avulsa</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-muted tabular-nums whitespace-nowrap">
                                {{ $charge->period_start->format('d/m/Y') }}
                                @if (! $charge->period_start->isSameDay($charge->period_end))
                                    – {{ $charge->period_end->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right font-medium text-ink tabular-nums whitespace-nowrap">
                                R$ {{ number_format($charge->amount, 2, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5 text-muted tabular-nums whitespace-nowrap">
                                {{ $charge->sent_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $charge->status->badgeClasses() }}">
                                        {{ $charge->status->label() }}
                                    </span>
                                    @if ($charge->isOverdue())
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-danger-tint text-danger-ink">Atrasada</span>
                                    @endif
                                </div>
                                @if ($charge->paid_at)
                                    <span class="block text-[13px] text-muted mt-1 tabular-nums">em {{ $charge->paid_at->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if ($charge->receipt_path)
                                        <a href="{{ route('charges.receipt', $charge) }}" target="_blank"
                                           class="inline-flex items-center min-h-10 px-2 text-[13px] font-medium text-brand hover:underline">Comprovante</a>
                                    @endif

                                    @if ($charge->status === App\Enums\ChargeStatus::Pending && $charge->client?->whatsappNumber())
                                        <a href="{{ route('charges.whatsapp', $charge) }}" target="_blank" rel="noopener"
                                           title="Abrir o seu WhatsApp com a cobrança pronta (reenvio ou lembrete)"
                                           class="inline-flex items-center justify-center min-h-10 px-3 rounded-lg border border-line-strong bg-white text-ink hover:bg-canvas text-[13px] font-semibold transition-colors">WhatsApp</a>
                                    @endif

                                    @if ($charge->status === App\Enums\ChargeStatus::Pending)
                                        <button type="button" @click="open = true"
                                                class="inline-flex items-center justify-center min-h-10 px-3 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors cursor-pointer">
                                            Confirmar pagamento
                                        </button>
                                    @elseif ($charge->status === App\Enums\ChargeStatus::Paid)
                                        <form method="POST" action="{{ route('charges.reopen', $charge) }}" class="inline"
                                              onsubmit="return confirm('Reabrir esta cobrança como pendente?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center min-h-10 px-3 rounded-lg border border-line-strong bg-white text-ink hover:bg-canvas text-[13px] font-semibold transition-colors cursor-pointer">Reabrir</button>
                                        </form>
                                    @endif
                                </div>

                                {{-- Modal de confirmação de pagamento --}}
                                <div x-cloak x-show="open" @keydown.escape.window="open = false"
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                    <div class="absolute inset-0 bg-ink/40" @click="open = false"></div>
                                    <div class="relative bg-white rounded-2xl border border-line shadow-xl max-w-md w-full p-6 text-left whitespace-normal"
                                         role="dialog" aria-modal="true" aria-labelledby="pay_title_{{ $charge->id }}"
                                         x-transition.opacity>
                                        <h3 id="pay_title_{{ $charge->id }}" class="text-lg font-semibold text-ink mb-1">Confirmar pagamento</h3>
                                        <p class="text-sm text-muted mb-5">
                                            {{ $charge->client->name }} — R$ {{ number_format($charge->amount, 2, ',', '.') }}
                                        </p>

                                        <form method="POST" action="{{ route('charges.pay', $charge) }}"
                                              enctype="multipart/form-data" class="space-y-4">
                                            @csrf
                                            @method('PATCH')

                                            <div>
                                                <label class="block text-sm font-medium text-ink mb-1.5" for="paid_at_{{ $charge->id }}">Data do pagamento</label>
                                                <input id="paid_at_{{ $charge->id }}" type="date" name="paid_at"
                                                       value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
                                                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-ink mb-1.5" for="receipt_{{ $charge->id }}">Comprovante (opcional)</label>
                                                <input id="receipt_{{ $charge->id }}" type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"
                                                       class="block w-full text-sm text-muted file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-brand file:text-white file:text-sm file:font-semibold hover:file:bg-brand-dark file:cursor-pointer">
                                                <p class="text-[13px] text-muted mt-1.5">PDF, JPG ou PNG, até 5 MB.</p>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-ink mb-1.5" for="notes_{{ $charge->id }}">Observações</label>
                                                <textarea id="notes_{{ $charge->id }}" name="notes" rows="2" maxlength="1000"
                                                          class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25"></textarea>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-3 pt-1">
                                                <button type="submit"
                                                        class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors cursor-pointer">
                                                    Confirmar
                                                </button>
                                                <button type="button" @click="open = false"
                                                        class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors cursor-pointer">
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
    <div role="alert" class="rounded-xl border border-danger/20 bg-danger-tint px-4 py-3 text-sm text-danger-ink mt-6">{{ $message }}</div>
@enderror
@error('paid_at')
    <div role="alert" class="rounded-xl border border-danger/20 bg-danger-tint px-4 py-3 text-sm text-danger-ink mt-6">{{ $message }}</div>
@enderror
@endsection
