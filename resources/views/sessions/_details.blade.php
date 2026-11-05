{{-- Detalhes de uma sessão no painel lateral da Agenda (mês e semana). --}}
@php
    $client = $session->client;
    $end = $session->scheduled_at->copy()->addMinutes($session->duration_minutes);
    $cycle = $client->payment_cycle?->label();
    if ($client->payment_cycle === App\Enums\PaymentCycle::Monthly && $client->payment_day) {
        $cycle .= ', dia '.$client->payment_day;
    } elseif ($client->payment_cycle === App\Enums\PaymentCycle::Interval && $client->payment_interval_days) {
        $cycle = 'A cada '.$client->payment_interval_days.' dias';
    }
@endphp

<span class="inline-flex rounded-full px-3 py-1.5 text-[13px] font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
<h2 class="mt-3.5 text-[22px] font-semibold leading-tight tracking-tight">{{ $client->name }}</h2>
<p class="mt-1.5 text-[15px] text-muted">
    {{ ucfirst($session->scheduled_at->locale('pt_BR')->translatedFormat('l, j \d\e F')) }}
    · {{ $session->scheduled_at->format('H:i') }} às {{ $end->format('H:i') }}
</p>
@if ($session->isRecurring())
    <p class="mt-1 flex items-center gap-1.5 text-[13px] text-muted">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
        Faz parte de uma série recorrente
    </p>
@endif

<dl class="mt-5 grid grid-cols-2 gap-3.5">
    <div>
        <dt class="text-[13px] text-muted">Valor</dt>
        <dd class="mt-1 text-[15px] font-semibold tabular-nums">R$ {{ number_format($session->value, 2, ',', '.') }}</dd>
    </div>
    <div>
        <dt class="text-[13px] text-muted">Duração</dt>
        <dd class="mt-1 text-[15px] font-semibold">{{ $session->duration_minutes }} min</dd>
    </div>
    @if ($cycle)
        <div>
            <dt class="text-[13px] text-muted">Ciclo</dt>
            <dd class="mt-1 text-[15px] font-semibold">{{ $cycle }}</dd>
        </div>
    @endif
    <div>
        <dt class="text-[13px] text-muted">Cobrança</dt>
        <dd class="mt-1 text-[15px] font-semibold">{{ $client->billing_channel->label() }}</dd>
    </div>
</dl>

<div class="mt-5 flex flex-col gap-2">
    @if ($session->status === App\Enums\SessionStatus::Scheduled)
        <p class="text-[13px] font-medium text-muted">Registrar presença</p>
        <form method="POST" action="{{ route('sessions.status', $session) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="w-full min-h-11 rounded-xl bg-accent hover:bg-accent-ink text-[15px] font-semibold text-white transition-colors">Realizada</button>
        </form>
        <div class="grid grid-cols-2 gap-2">
            <form method="POST" action="{{ route('sessions.status', $session) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="no_show">
                <button type="submit" title="Falta não informada (cobrada)"
                        class="w-full min-h-11 rounded-xl border border-line-strong bg-white hover:bg-danger-tint text-sm font-semibold text-danger-ink transition-colors">Falta s/ aviso</button>
            </form>
            <form method="POST" action="{{ route('sessions.status', $session) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="no_show_excused">
                <button type="submit" title="Falta informada (abonada)"
                        class="w-full min-h-11 rounded-xl border border-line-strong bg-white hover:bg-warn-tint text-sm font-semibold text-warn-ink transition-colors">Falta c/ aviso</button>
            </form>
        </div>
        <form method="POST" action="{{ route('sessions.status', $session) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="canceled">
            <button type="submit" class="w-full min-h-11 rounded-xl text-sm font-medium text-muted hover:bg-canvas hover:text-ink transition-colors">Cancelar sessão</button>
        </form>
    @else
        <form method="POST" action="{{ route('sessions.status', $session) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="scheduled">
            <button type="submit" class="w-full min-h-11 rounded-xl border border-line-strong bg-white hover:bg-canvas text-sm font-semibold transition-colors">Voltar para agendada</button>
        </form>
    @endif

    @if ($session->status !== App\Enums\SessionStatus::Canceled)
        <div class="mt-1 grid grid-cols-2 gap-2">
            <a href="{{ route('sessions.edit', $session) }}"
               class="inline-flex items-center justify-center min-h-11 rounded-xl border border-line-strong bg-white hover:bg-canvas text-sm font-semibold transition-colors">Editar</a>
            <form method="POST" action="{{ route('sessions.charge', $session) }}">
                @csrf
                <button type="submit" title="Enviar cobrança desta sessão ({{ $client->billing_channel->label() }})"
                        class="w-full min-h-11 rounded-xl border border-line-strong bg-white hover:bg-canvas text-sm font-semibold transition-colors">Cobrar</button>
            </form>
        </div>
    @endif
</div>
