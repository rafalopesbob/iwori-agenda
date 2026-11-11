@extends('layouts.app')

@section('title', $client->name . ' — Agenda Iwori')

@section('content')
@php
    $cycle = $client->payment_cycle->label();
    if ($client->payment_cycle === App\Enums\PaymentCycle::Monthly && $client->payment_day) {
        $cycle .= ' — todo dia '.$client->payment_day;
    } elseif ($client->payment_cycle === App\Enums\PaymentCycle::Interval && $client->payment_interval_days) {
        $cycle .= ' — a cada '.$client->payment_interval_days.' dias';
    }

    $details = [
        'E-mail' => $client->email ?? '—',
        'Telefone' => $client->phone ?? '—',
        'Valor da sessão' => 'R$ '.number_format($client->session_value, 2, ',', '.'),
        'Ciclo de pagamento' => $cycle,
        'Canal de cobrança' => $client->billing_channel->label(),
        'Última cobrança' => $client->last_charged_at?->format('d/m/Y') ?? 'Nunca cobrado',
        'Cobrança por WhatsApp' => $client->whatsapp_opt_in_at ? 'Autorizada em '.$client->whatsapp_opt_in_at->format('d/m/Y') : 'Não autorizada',
    ];
@endphp

<div class="max-w-4xl mx-auto">
    <a href="{{ route('clients.index') }}" class="inline-flex items-center gap-1.5 min-h-11 text-sm font-medium text-muted hover:text-ink">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
        Voltar para a lista
    </a>

    <div class="mt-2 mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-semibold tracking-tight">{{ $client->name }}</h1>
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $client->active ? 'bg-accent-tint text-accent-ink' : 'bg-neutral-tint text-muted' }}">
                {{ $client->active ? 'Ativo' : 'Inativo' }}
            </span>
        </div>
        <a href="{{ route('clients.edit', $client) }}"
           class="inline-flex items-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white hover:bg-canvas text-sm font-semibold transition-colors">Editar</a>
    </div>

    <div class="rounded-2xl border border-line bg-white p-6 sm:p-8">
        <dl class="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
            @foreach ($details as $term => $value)
                <div>
                    <dt class="text-[13px] text-muted">{{ $term }}</dt>
                    <dd class="mt-1 text-[15px] font-medium">{{ $value }}</dd>
                </div>
            @endforeach

            @if ($client->notes)
                <div class="sm:col-span-2">
                    <dt class="text-[13px] text-muted">Anotações</dt>
                    <dd class="mt-1 text-[15px] whitespace-pre-line">{{ $client->notes }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Resumo de todo o histórico --}}
    <section class="mt-8" aria-labelledby="resumo_titulo">
        <h2 id="resumo_titulo" class="text-lg font-semibold tracking-tight text-ink">Resumo</h2>
        <p class="text-sm text-muted mt-1 mb-4">
            @if ($summary['first'])
                Desde a primeira sessão, em {{ $summary['first']->format('d/m/Y') }}.
            @else
                Ainda não há sessões com este cliente.
            @endif
        </p>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            <div class="bg-white rounded-2xl border border-line p-4 sm:p-5">
                <p class="text-[13px] sm:text-sm font-medium text-muted">Sessões realizadas</p>
                <p class="mt-2 text-2xl sm:text-[28px] leading-none font-semibold tracking-tight tabular-nums text-ink">{{ $summary['completed'] }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-line p-4 sm:p-5">
                <p class="text-[13px] sm:text-sm font-medium text-muted">Faltas</p>
                <p class="mt-2 text-2xl sm:text-[28px] leading-none font-semibold tracking-tight tabular-nums text-ink">{{ $summary['no_show'] + $summary['no_show_excused'] }}</p>
                <p class="mt-1.5 text-[13px] text-muted">{{ $summary['no_show'] }} sem aviso · {{ $summary['no_show_excused'] }} com aviso</p>
            </div>
            <div class="bg-white rounded-2xl border border-line p-4 sm:p-5">
                <p class="text-[13px] sm:text-sm font-medium text-muted">Próxima sessão</p>
                @if ($summary['next'])
                    <p class="mt-2 text-lg sm:text-xl leading-tight font-semibold tracking-tight tabular-nums text-ink">{{ $summary['next']->scheduled_at->format('d/m/Y') }}</p>
                    <p class="mt-1 text-[13px] text-muted">
                        {{ ucfirst($summary['next']->scheduled_at->locale('pt_BR')->translatedFormat('l')) }}, {{ $summary['next']->scheduled_at->format('H:i') }}
                    </p>
                @else
                    <p class="mt-2 text-lg font-semibold text-muted">Nenhuma agendada</p>
                @endif
            </div>
            <div class="bg-white rounded-2xl border border-line p-4 sm:p-5">
                <p class="text-[13px] sm:text-sm font-medium text-muted">Faturado</p>
                <p class="mt-2 text-xl sm:text-2xl leading-none font-semibold tracking-tight tabular-nums text-ink">R$ {{ number_format($summary['billed'], 2, ',', '.') }}</p>
                <p class="mt-1.5 text-[13px] text-muted">Realizadas e faltas sem aviso</p>
            </div>
            <div class="bg-white rounded-2xl border border-line p-4 sm:p-5">
                <p class="text-[13px] sm:text-sm font-medium text-muted">Recebido</p>
                <p class="mt-2 text-xl sm:text-2xl leading-none font-semibold tracking-tight tabular-nums text-accent-ink">R$ {{ number_format($summary['received'], 2, ',', '.') }}</p>
                <p class="mt-1.5 text-[13px] text-muted">Cobranças pagas</p>
            </div>
            <div class="bg-white rounded-2xl border border-line p-4 sm:p-5">
                <p class="text-[13px] sm:text-sm font-medium text-muted">A receber</p>
                <p class="mt-2 text-xl sm:text-2xl leading-none font-semibold tracking-tight tabular-nums text-ink">R$ {{ number_format($summary['pending'], 2, ',', '.') }}</p>
                <p class="mt-1.5 text-[13px] {{ $summary['overdue'] > 0 ? 'text-danger-ink font-medium' : 'text-muted' }}">
                    @if ($summary['overdue'] > 0)
                        {{ $summary['overdue'] }} {{ $summary['overdue'] === 1 ? 'cobrança atrasada' : 'cobranças atrasadas' }}
                    @else
                        Cobranças pendentes
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Mês a mês --}}
    @if ($months->isNotEmpty())
        <section class="mt-8" aria-labelledby="meses_titulo">
            <h2 id="meses_titulo" class="text-lg font-semibold tracking-tight text-ink">Mês a mês</h2>
            <p class="text-sm text-muted mt-1 mb-4">Últimos 12 meses com sessões.</p>

            <div class="bg-white rounded-2xl border border-line overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[13px] text-muted">
                                <th class="px-5 py-3 font-medium border-b border-line">Mês</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-center">Realizadas</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-center" title="Falta não informada — cobrada">Faltas s/ aviso</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-center" title="Falta informada — abonada">Faltas c/ aviso</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-center">Canceladas</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-center">Agendadas</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-right">Faturado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($months as $row)
                                <tr>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <a href="{{ route('billing.index', ['month' => $row['month']->format('Y-m'), 'q' => $client->name]) }}"
                                           class="font-medium text-brand hover:underline">{{ ucfirst($row['month']->locale('pt_BR')->translatedFormat('F \d\e Y')) }}</a>
                                    </td>
                                    <td class="px-5 py-3 text-center tabular-nums text-ink">{{ $row['completed'] }}</td>
                                    <td class="px-5 py-3 text-center tabular-nums {{ $row['no_show'] > 0 ? 'text-danger-ink font-semibold' : 'text-muted' }}">{{ $row['no_show'] }}</td>
                                    <td class="px-5 py-3 text-center tabular-nums {{ $row['no_show_excused'] > 0 ? 'text-warn-ink font-semibold' : 'text-muted' }}">{{ $row['no_show_excused'] }}</td>
                                    <td class="px-5 py-3 text-center tabular-nums text-muted">{{ $row['canceled'] }}</td>
                                    <td class="px-5 py-3 text-center tabular-nums text-muted">{{ $row['scheduled'] }}</td>
                                    <td class="px-5 py-3 text-right font-medium tabular-nums text-ink whitespace-nowrap">R$ {{ number_format($row['billed'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    {{-- Sessões --}}
    <section id="sessoes" class="mt-8 scroll-mt-6" aria-labelledby="sessoes_titulo">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <h2 id="sessoes_titulo" class="text-lg font-semibold tracking-tight text-ink">Sessões</h2>
            <nav class="inline-flex flex-wrap rounded-xl bg-brand-tint p-1 gap-1" aria-label="Filtrar sessões por situação">
                @php
                    $sessionFilters = ['' => 'Todas'];
                    foreach (App\Enums\SessionStatus::cases() as $status) {
                        $sessionFilters[$status->value] = $status->label();
                    }
                @endphp
                @foreach ($sessionFilters as $value => $label)
                    @php($isActive = ($sessionStatus?->value ?? '') === $value)
                    <a href="{{ route('clients.show', array_filter(['client' => $client, 'situacao' => $value])) }}#sessoes"
                       @if ($isActive) aria-current="page" @endif
                       class="inline-flex items-center min-h-9 px-3 rounded-lg text-[13px] font-semibold transition-colors {{ $isActive ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="bg-white rounded-2xl border border-line overflow-hidden">
            @if ($sessions->isEmpty())
                <p class="p-8 text-center text-muted">{{ $sessionStatus ? 'Nenhuma sessão com esta situação.' : 'Nenhuma sessão ainda.' }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[13px] text-muted">
                                <th class="px-5 py-3 font-medium border-b border-line">Data</th>
                                <th class="px-5 py-3 font-medium border-b border-line">Horário</th>
                                <th class="px-5 py-3 font-medium border-b border-line">Situação</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-right">Valor</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-right"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($sessions as $session)
                                <tr class="hover:bg-canvas transition-colors">
                                    <td class="px-5 py-3 whitespace-nowrap tabular-nums text-ink">
                                        {{ $session->scheduled_at->format('d/m/Y') }}
                                        <span class="text-muted">· {{ $session->scheduled_at->locale('pt_BR')->translatedFormat('D') }}</span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap tabular-nums text-muted">
                                        {{ $session->scheduled_at->format('H:i') }} – {{ $session->scheduled_at->copy()->addMinutes($session->duration_minutes)->format('H:i') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums whitespace-nowrap {{ $session->status->isBillable() ? 'font-medium text-ink' : 'text-muted' }}">
                                        R$ {{ number_format($session->value, 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-2 text-right whitespace-nowrap">
                                        @if ($session->status !== App\Enums\SessionStatus::Canceled)
                                            <a href="{{ route('sessions.edit', $session) }}" class="inline-flex items-center min-h-10 px-2 font-medium text-brand hover:underline">Abrir</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($sessions->hasPages())
                    <div class="px-5 py-4 border-t border-line">{{ $sessions->links() }}</div>
                @endif
            @endif
        </div>
    </section>

    {{-- Cobranças --}}
    <section id="cobrancas" class="mt-8 mb-4 scroll-mt-6" aria-labelledby="cobrancas_titulo">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <h2 id="cobrancas_titulo" class="text-lg font-semibold tracking-tight text-ink">Cobranças</h2>
            <a href="{{ route('charges.index', ['client' => $client->id]) }}" class="inline-flex items-center min-h-11 text-sm font-medium text-brand hover:underline">Ver em Cobranças</a>
        </div>

        <div class="bg-white rounded-2xl border border-line overflow-hidden">
            @if ($charges->isEmpty())
                <p class="p-8 text-center text-muted">Nenhuma cobrança registrada para este cliente.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[13px] text-muted">
                                <th class="px-5 py-3 font-medium border-b border-line">Período</th>
                                <th class="px-5 py-3 font-medium border-b border-line text-right">Valor</th>
                                <th class="px-5 py-3 font-medium border-b border-line">Enviada em</th>
                                <th class="px-5 py-3 font-medium border-b border-line">E-mail</th>
                                <th class="px-5 py-3 font-medium border-b border-line">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($charges as $charge)
                                <tr>
                                    <td class="px-5 py-3 text-muted tabular-nums whitespace-nowrap">
                                        {{ $charge->period_start->format('d/m/Y') }}
                                        @if (! $charge->period_start->isSameDay($charge->period_end))
                                            – {{ $charge->period_end->format('d/m/Y') }}
                                        @endif
                                        @if ($charge->client_session_id)
                                            <span class="block text-[13px]">Cobrança avulsa</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-medium text-ink tabular-nums whitespace-nowrap">R$ {{ number_format($charge->amount, 2, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-muted tabular-nums whitespace-nowrap">{{ $charge->sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @switch($charge->emailStatus())
                                            @case('sent')
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-accent-tint text-accent-ink">Enviado</span>
                                                @break
                                            @case('queued')
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-neutral-tint text-muted">Na fila</span>
                                                @break
                                            @case('failed')
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-danger-tint text-danger-ink">Falhou</span>
                                                @break
                                            @default
                                                <span class="text-[13px] text-muted">{{ $charge->channel === App\Enums\BillingChannel::Whatsapp ? 'Pelo WhatsApp' : '—' }}</span>
                                        @endswitch
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $charge->status->badgeClasses() }}">{{ $charge->status->label() }}</span>
                                            @if ($charge->isOverdue())
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-danger-tint text-danger-ink">Atrasada</span>
                                            @endif
                                        </div>
                                        @if ($charge->paid_at)
                                            <span class="block text-[13px] text-muted mt-1 tabular-nums">em {{ $charge->paid_at->format('d/m/Y') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($charges->hasPages())
                    <div class="px-5 py-4 border-t border-line">{{ $charges->links() }}</div>
                @endif
            @endif
        </div>
    </section>
</div>
@endsection
