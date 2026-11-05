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

<div class="max-w-3xl mx-auto">
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

        <p class="mt-8 rounded-xl bg-canvas px-4 py-3 text-sm text-muted">
            Em breve: histórico de sessões e faturamento do cliente.
        </p>
    </div>
</div>
@endsection
