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

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-line p-5">
        <p class="text-sm font-medium text-muted">Faturamento do mês</p>
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
</div>

<div class="bg-white rounded-2xl border border-line overflow-hidden">
    @if ($rows->isEmpty())
        <p class="p-10 text-center text-muted">Nenhuma sessão neste mês.</p>
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
                        <tr class="hover:bg-canvas transition-colors">
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
                            <td class="px-5 py-2 text-right">
                                <form method="POST" action="{{ route('billing.charge', $row['client']) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            title="Enviar cobrança do período por {{ $row['client']->billing_channel->label() }}"
                                            class="inline-flex items-center justify-center min-h-10 px-3 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors cursor-pointer">
                                        Cobrar
                                    </button>
                                </form>
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
