@extends('layouts.app')

@section('title', 'Faturamento — Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">
        Faturamento — <span class="capitalize">{{ $month->locale('pt_BR')->translatedFormat('F \d\e Y') }}</span>
    </h1>

    <nav class="flex items-center gap-2" aria-label="Navegação entre meses">
        <a href="{{ route('billing.index', ['month' => $month->subMonth()->format('Y-m')]) }}"
           aria-label="Mês anterior" title="Mês anterior"
           class="inline-flex items-center justify-center size-11 p-0 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas transition-colors">
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
        </a>
        <a href="{{ route('billing.index') }}"
           class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors">Mês atual</a>
        <a href="{{ route('billing.index', ['month' => $month->addMonth()->format('Y-m')]) }}"
           aria-label="Próximo mês" title="Próximo mês"
           class="inline-flex items-center justify-center size-11 p-0 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas transition-colors">
            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
        </a>
    </nav>
</div>

@if (session('status'))
    <div class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink mb-6">
        {{ session('status') }}
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-line p-5">
        <p class="text-sm font-medium text-muted">Faturamento do mês{{ $filtered ? ' (filtro)' : '' }}</p>
        <p class="mt-2 text-[30px] leading-none font-semibold tracking-tight tabular-nums text-ink">R$ {{ number_format($totals['total'], 2, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-line p-5">
        <p class="text-sm font-medium text-muted">Sessões realizadas</p>
        <p class="mt-2 text-[30px] leading-none font-semibold tracking-tight tabular-nums text-ink">{{ $totals['completed'] }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-line p-5">
        <p class="text-sm font-medium text-muted">Faltas</p>
        <p class="mt-2 text-[30px] leading-none font-semibold tracking-tight tabular-nums text-ink">{{ $totals['no_show'] }}</p>
    </div>
    <a href="{{ route('charges.index', ['month' => $month->format('Y-m')]) }}" class="bg-white rounded-2xl border border-line p-5 hover:border-line-strong transition-colors">
        <p class="text-sm font-medium text-muted">E-mails de cobrança enviados</p>
        <p class="mt-2 text-[30px] leading-none font-semibold tracking-tight tabular-nums text-ink">{{ $emailsSent }}</p>
        <p class="mt-2 text-[13px] text-muted">No mês, contando reenvios. Ver em Cobranças</p>
    </a>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('billing.index') }}" class="flex flex-wrap items-end gap-3 mb-6" role="search">
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <div class="w-full sm:w-auto sm:min-w-64">
        <label for="billing_q" class="block text-[13px] font-medium text-muted mb-1">Cliente</label>
        <input id="billing_q" type="search" name="q" value="{{ $search }}" placeholder="Buscar pelo nome"
               class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
    </div>
    <div class="w-full sm:w-auto">
        <label for="billing_show" class="block text-[13px] font-medium text-muted mb-1">Mostrar</label>
        <select id="billing_show" name="show" onchange="this.form.submit()"
                class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            <option value="">Todos os clientes</option>
            @foreach ($showFilters as $value => $label)
                <option value="{{ $value }}" @selected($show === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="inline-flex items-center justify-center min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors">Filtrar</button>
    @if ($filtered)
        <a href="{{ route('billing.index', ['month' => $month->format('Y-m')]) }}"
           class="inline-flex items-center min-h-11 px-2 text-sm font-medium text-brand hover:underline">Limpar filtros</a>
    @endif
</form>

<div class="bg-white rounded-2xl border border-line overflow-hidden">
    @if ($rows->isEmpty())
        <p class="p-10 text-center text-muted">{{ $filtered ? 'Nenhum cliente encontrado com esses filtros.' : 'Nenhuma sessão neste mês.' }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[13px] text-muted">
                        <th class="px-5 py-3 font-medium border-b border-line">Cliente</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-center">Realizadas</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-center" title="Falta não informada — cobrada">Faltas s/ aviso</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-center" title="Falta informada — abonada">Faltas c/ aviso</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-center">Canceladas</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-center">Agendadas</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-right">Faturamento</th>
                        <th class="px-5 py-3 font-medium border-b border-line text-right">Cobrança</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        @php
                            $client = $row['client'];
                            $preview = $previews[$client->id];
                        @endphp
                        <tr x-data="{ confirm: false }" class="hover:bg-canvas transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('clients.show', $row['client']) }}" class="font-medium text-brand hover:underline">
                                    {{ $row['client']->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-ink">{{ $row['completed'] }}</td>
                            <td class="px-5 py-3.5 text-center tabular-nums {{ $row['no_show'] > 0 ? 'text-danger-ink font-semibold' : 'text-muted' }}">{{ $row['no_show'] }}</td>
                            <td class="px-5 py-3.5 text-center tabular-nums {{ $row['no_show_excused'] > 0 ? 'text-warn-ink font-semibold' : 'text-muted' }}">{{ $row['no_show_excused'] }}</td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-muted">{{ $row['canceled'] }}</td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-muted">{{ $row['scheduled'] }}</td>
                            <td class="px-5 py-3.5 text-right font-medium tabular-nums text-ink whitespace-nowrap">R$ {{ number_format($row['total'], 2, ',', '.') }}</td>
                            <td class="px-5 py-2 text-right whitespace-nowrap">
                                <button type="button" @click="confirm = true"
                                        class="inline-flex items-center justify-center min-h-10 px-3 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors cursor-pointer">
                                    Cobrar
                                </button>
                                @if ($row['client']->whatsappNumber())
                                    {{-- Nova aba: o Faturamento continua aberto enquanto o WhatsApp abre. --}}
                                    <form method="POST" action="{{ route('billing.whatsapp', $row['client']) }}" target="_blank" class="inline">
                                        @csrf
                                        <button type="submit"
                                                title="Registrar a cobrança do período e abrir o seu WhatsApp com a mensagem pronta"
                                                class="inline-flex items-center justify-center min-h-10 px-3 rounded-lg border border-line-strong bg-white text-ink hover:bg-canvas text-[13px] font-semibold transition-colors cursor-pointer">
                                            WhatsApp
                                        </button>
                                    </form>
                                @endif

                                {{-- Confirmação do Cobrar: mostra o que vai ser cobrado e para onde vai. --}}
                                <div x-cloak x-show="confirm" @keydown.escape.window="confirm = false"
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                    <div class="absolute inset-0 bg-ink/40" @click="confirm = false"></div>
                                    <div class="relative bg-white rounded-2xl border border-line shadow-xl max-w-md w-full p-6 text-left whitespace-normal"
                                         role="dialog" aria-modal="true" aria-labelledby="charge_title_{{ $client->id }}" x-transition.opacity>
                                        <h3 id="charge_title_{{ $client->id }}" class="text-lg font-semibold text-ink">Cobrar {{ $client->name }}?</h3>

                                        @if ($preview['total'] > 0)
                                            <dl class="mt-4 space-y-2.5 text-sm">
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-muted">Período</dt>
                                                    <dd class="font-medium text-ink tabular-nums">{{ $preview['start']->format('d/m/Y') }} a {{ $preview['end']->format('d/m/Y') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-muted">Sessões cobradas</dt>
                                                    <dd class="font-medium text-ink tabular-nums">{{ $preview['sessions'] }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-muted">Valor</dt>
                                                    <dd class="font-semibold text-ink tabular-nums">R$ {{ number_format($preview['total'], 2, ',', '.') }}</dd>
                                                </div>
                                                <div class="flex justify-between gap-4">
                                                    <dt class="text-muted">Envio</dt>
                                                    <dd class="font-medium text-ink text-right break-all">
                                                        @switch($preview['delivery'])
                                                            @case(App\Enums\BillingChannel::Email)
                                                                E-mail para {{ $client->email }}
                                                                @break
                                                            @case(App\Enums\BillingChannel::Both)
                                                                E-mail para {{ $client->email }} e WhatsApp automático
                                                                @break
                                                            @case(App\Enums\BillingChannel::Whatsapp)
                                                                WhatsApp automático
                                                                @break
                                                            @default
                                                                <span class="text-danger-ink">Nenhum: cliente sem e-mail</span>
                                                        @endswitch
                                                    </dd>
                                                </div>
                                            </dl>
                                            <p class="mt-4 text-[13px] text-muted">
                                                O período vai da última cobrança até hoje, por isso o valor pode ser diferente do faturamento do mês.
                                                Depois de enviar, a situação do e-mail aparece em Cobranças.
                                            </p>

                                            <form method="POST" action="{{ route('billing.charge', $client) }}" class="mt-5 flex flex-wrap items-center gap-3">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center justify-center min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors cursor-pointer">
                                                    {{ $preview['delivery'] ? 'Enviar cobrança' : 'Só registrar' }}
                                                </button>
                                                <button type="button" @click="confirm = false"
                                                        class="inline-flex items-center min-h-11 px-2 text-sm font-medium text-muted hover:text-ink cursor-pointer">Cancelar</button>
                                            </form>
                                        @else
                                            <p class="mt-3 text-sm text-muted">
                                                Nada a cobrar agora: não há sessões realizadas nem faltas sem aviso de
                                                {{ $preview['start']->format('d/m/Y') }} a {{ $preview['end']->format('d/m/Y') }}
                                                (desde a última cobrança).
                                            </p>
                                            <div class="mt-5">
                                                <button type="button" @click="confirm = false"
                                                        class="inline-flex items-center justify-center min-h-11 px-5 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors cursor-pointer">Fechar</button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-canvas font-semibold text-ink">
                    <tr class="border-t border-line">
                        <td class="px-5 py-3.5">Total</td>
                        <td class="px-5 py-3.5 text-center tabular-nums">{{ $totals['completed'] }}</td>
                        <td class="px-5 py-3.5 text-center tabular-nums">{{ $totals['no_show'] }}</td>
                        <td class="px-5 py-3.5 text-center tabular-nums">{{ $totals['no_show_excused'] }}</td>
                        <td class="px-5 py-3.5" colspan="2"></td>
                        <td class="px-5 py-3.5 text-right tabular-nums whitespace-nowrap">R$ {{ number_format($totals['total'], 2, ',', '.') }}</td>
                        <td class="px-5 py-3.5"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="px-5 py-4 text-[13px] text-muted border-t border-line">
            O faturamento soma sessões <strong class="font-semibold text-ink">Realizadas</strong> e <strong class="font-semibold text-ink">Faltas não informadas</strong> (sem aviso).
            Faltas informadas são abonadas; agendadas e canceladas não entram no total.
        </p>
    @endif
</div>
@endsection
