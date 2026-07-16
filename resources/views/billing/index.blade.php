@extends('layouts.app')

@section('title', 'Faturamento — Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold text-mvindigo">
        Faturamento — <span class="capitalize">{{ $month->locale('pt_BR')->translatedFormat('F \d\e Y') }}</span>
    </h1>

    <div class="flex items-center gap-2">
        <a href="{{ route('billing.index', ['month' => $month->subMonth()->format('Y-m')]) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">&larr;</a>
        <a href="{{ route('billing.index') }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">Mês atual</a>
        <a href="{{ route('billing.index', ['month' => $month->addMonth()->format('Y-m')]) }}"
           class="px-3 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">&rarr;</a>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <p class="text-sm text-gray-500">Faturamento do mês</p>
        <p class="text-2xl font-semibold text-mvteal-dark mt-1">R$ {{ number_format($totals['total'], 2, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <p class="text-sm text-gray-500">Sessões realizadas</p>
        <p class="text-2xl font-semibold text-gray-800 mt-1">{{ $totals['completed'] }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <p class="text-sm text-gray-500">Faltas</p>
        <p class="text-2xl font-semibold text-gray-800 mt-1">{{ $totals['no_show'] }}</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    @if ($rows->isEmpty())
        <p class="p-8 text-gray-500 text-center">Nenhuma sessão neste mês.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-700 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium text-center">Realizadas</th>
                        <th class="px-4 py-3 font-medium text-center" title="Falta não informada — cobrada">Faltas s/ aviso</th>
                        <th class="px-4 py-3 font-medium text-center" title="Falta informada — abonada">Faltas c/ aviso</th>
                        <th class="px-4 py-3 font-medium text-center">Canceladas</th>
                        <th class="px-4 py-3 font-medium text-center">Agendadas</th>
                        <th class="px-4 py-3 font-medium text-right">Faturamento</th>
                        <th class="px-4 py-3 font-medium text-right">Cobrança</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('clients.show', $row['client']) }}" class="font-medium text-mvteal-dark hover:underline">
                                    {{ $row['client']->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">{{ $row['completed'] }}</td>
                            <td class="px-4 py-3 text-center {{ $row['no_show'] > 0 ? 'text-mvrose-dark font-medium' : '' }}">{{ $row['no_show'] }}</td>
                            <td class="px-4 py-3 text-center {{ $row['no_show_excused'] > 0 ? 'text-amber-600 font-medium' : 'text-gray-500' }}">{{ $row['no_show_excused'] }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $row['canceled'] }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $row['scheduled'] }}</td>
                            <td class="px-4 py-3 text-right font-medium">R$ {{ number_format($row['total'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('billing.charge', $row['client']) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            title="Enviar cobrança do período por {{ $row['client']->billing_channel->label() }}"
                                            class="text-xs bg-mvteal hover:bg-mvteal-dark text-white px-3 py-1.5 rounded-lg font-medium">
                                        Cobrar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-mvindigo">
                    <tr>
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-center">{{ $totals['completed'] }}</td>
                        <td class="px-4 py-3 text-center">{{ $totals['no_show'] }}</td>
                        <td class="px-4 py-3 text-center">{{ $totals['no_show_excused'] }}</td>
                        <td class="px-4 py-3" colspan="2"></td>
                        <td class="px-4 py-3 text-right">R$ {{ number_format($totals['total'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="px-4 py-3 text-xs text-gray-500 border-t border-gray-100">
            O faturamento soma sessões <strong>Realizadas</strong> e <strong>Faltas não informadas</strong> (sem aviso).
            Faltas informadas são abonadas; agendadas e canceladas não entram no total.
        </p>
    @endif
</div>
@endsection
