@extends('layouts.app')

@section('title', 'Painel — Agenda Iwori')

@section('content')
@php
    $firstName = Illuminate\Support\Str::before(auth()->user()->name, ' ');
    $progress = $monthPlanned > 0 ? min(100, (int) round($monthCompleted / $monthPlanned * 100)) : 0;
    $statusActions = [
        ['completed', 'Realizada', 'Marcar como realizada', 'border-accent text-accent-ink hover:bg-accent-tint'],
        ['no_show', 'Falta s/ aviso', 'Falta não informada (cobrada)', 'border-line-strong text-danger-ink hover:bg-danger-tint'],
        ['no_show_excused', 'Falta c/ aviso', 'Falta informada (abonada)', 'border-line-strong text-warn-ink hover:bg-warn-tint'],
    ];
@endphp

<div class="flex flex-wrap items-end justify-between gap-4 mb-7">
    <div>
        <p class="text-sm font-medium text-muted">{{ ucfirst(today()->locale('pt_BR')->translatedFormat('l, j \d\e F')) }}</p>
        <h1 class="mt-1 text-[32px] font-semibold tracking-tight">Olá, {{ $firstName }}</h1>
    </div>
    <a href="{{ route('clients.create') }}"
       class="inline-flex items-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white hover:bg-canvas text-[15px] font-semibold transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        Novo cliente
    </a>
</div>

@if (session('status'))
    <div role="status" class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 mb-6 text-sm font-medium text-accent-ink">
        {{ session('status') }}
    </div>
@endif

<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4 mb-6">
    <a href="{{ route('billing.index') }}" class="rounded-[18px] border border-line bg-white px-4 py-4 sm:px-[22px] sm:py-5 hover:border-line-strong transition-colors">
        <p class="text-sm font-medium text-muted">Faturado em {{ today()->locale('pt_BR')->translatedFormat('F') }}</p>
        <p class="mt-2 sm:mt-2.5 text-xl sm:text-[30px] leading-none font-semibold tracking-tight tabular-nums">R$ {{ number_format($monthTotal, 2, ',', '.') }}</p>
        <p class="mt-2 text-[13px] text-muted">Realizadas + faltas sem aviso</p>
    </a>
    <a href="{{ route('billing.index') }}" class="rounded-[18px] border border-line bg-white px-4 py-4 sm:px-[22px] sm:py-5 hover:border-line-strong transition-colors">
        <p class="text-sm font-medium text-muted">Sessões realizadas</p>
        <p class="mt-2 sm:mt-2.5 text-xl sm:text-[30px] leading-none font-semibold tracking-tight tabular-nums">{{ $monthCompleted }}</p>
        <div class="mt-3 h-1.5 rounded-full bg-brand-tint overflow-hidden" aria-hidden="true">
            <div class="h-1.5 rounded-full bg-brand" style="width: {{ $progress }}%"></div>
        </div>
        <p class="mt-2 text-[13px] text-muted">de {{ $monthPlanned }} {{ $monthPlanned === 1 ? 'sessão' : 'sessões' }} no mês</p>
    </a>
    <a href="{{ route('charges.index', ['status' => 'pending']) }}" class="rounded-[18px] border border-line bg-white px-4 py-4 sm:px-[22px] sm:py-5 hover:border-line-strong transition-colors">
        <p class="text-sm font-medium text-muted">A receber</p>
        <p class="mt-2 sm:mt-2.5 text-xl sm:text-[30px] leading-none font-semibold tracking-tight tabular-nums">R$ {{ number_format($pendingTotal, 2, ',', '.') }}</p>
        <p class="mt-2 text-[13px] text-muted">
            @if ($pendingCount === 0)
                Nenhuma cobrança pendente
            @else
                {{ $pendingCount }} {{ $pendingCount === 1 ? 'cobrança pendente' : 'cobranças pendentes' }}
            @endif
        </p>
    </a>
    <a href="{{ route('clients.index') }}" class="rounded-[18px] border border-line bg-white px-4 py-4 sm:px-[22px] sm:py-5 hover:border-line-strong transition-colors">
        <p class="text-sm font-medium text-muted">Clientes ativos</p>
        <p class="mt-2 sm:mt-2.5 text-xl sm:text-[30px] leading-none font-semibold tracking-tight tabular-nums">{{ $activeClients }}</p>
        <p class="mt-2 text-[13px] text-muted">
            @if ($newClients === 0)
                Nenhum novo este mês
            @else
                {{ $newClients }} {{ $newClients === 1 ? 'entrou' : 'entraram' }} este mês
            @endif
        </p>
    </a>
</div>

<div class="grid gap-4 lg:grid-cols-[2fr_1fr] items-start">
    <section aria-labelledby="hoje" class="rounded-[18px] border border-line bg-white px-6 pt-2 pb-3">
        <div class="flex items-center justify-between gap-3 pt-4 pb-2">
            <h2 id="hoje" class="text-lg font-semibold tracking-tight">Sessões de hoje</h2>
            <a href="{{ route('sessions.index', ['view' => 'day']) }}" class="inline-flex items-center min-h-11 text-sm font-semibold text-brand hover:underline">Ver agenda</a>
        </div>

        @if ($todaySessions->isEmpty())
            <p class="border-t border-line py-8 text-center text-muted">Nenhuma sessão agendada para hoje.</p>
        @else
            <ul>
                @foreach ($todaySessions as $session)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-line py-3.5">
                        <span class="w-[52px] text-[15px] font-semibold tabular-nums">{{ $session->scheduled_at->format('H:i') }}</span>
                        <div class="min-w-0 flex-1 basis-40">
                            <p class="truncate text-[15px] font-medium">{{ $session->client->name }}</p>
                            <p class="mt-0.5 text-[13px] text-muted">{{ $session->duration_minutes }} min · R$ {{ number_format($session->value, 2, ',', '.') }}</p>
                        </div>

                        @if ($session->status === App\Enums\SessionStatus::Scheduled)
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($statusActions as [$status, $label, $title, $tone])
                                    <form method="POST" action="{{ route('sessions.status', $session) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $status }}">
                                        <button type="submit" title="{{ $title }}"
                                                class="min-h-11 px-3.5 rounded-[10px] border bg-white text-[13px] font-semibold transition-colors {{ $tone }}">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <div class="flex items-center gap-1">
                                <span class="rounded-full px-3 py-1.5 text-[13px] font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
                                @if ($session->status !== App\Enums\SessionStatus::Canceled)
                                    <form method="POST" action="{{ route('sessions.status', $session) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="scheduled">
                                        <button type="submit" class="min-h-11 px-2.5 text-[13px] font-medium text-muted underline hover:text-ink">Desfazer</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="flex flex-col gap-4">
        <section aria-labelledby="cobrancas" class="rounded-[18px] border border-line bg-white px-[22px] pt-2 pb-3">
            <div class="flex items-center justify-between gap-3 pt-4 pb-2">
                <h2 id="cobrancas" class="text-lg font-semibold tracking-tight">Cobranças</h2>
                <a href="{{ route('charges.index') }}" class="inline-flex items-center min-h-11 text-sm font-semibold text-brand hover:underline">Ver todas</a>
            </div>
            @forelse ($pendingCharges as $charge)
                <div class="flex flex-col gap-2.5 border-t border-line py-3.5">
                    <div class="flex justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[15px] font-medium">{{ $charge->client->name }}</p>
                            <p class="mt-0.5 text-[13px] text-muted">
                                {{ $charge->client_session_id ? 'Sessão avulsa de '.$charge->period_start->format('d/m') : $charge->period_start->format('d/m').' a '.$charge->period_end->format('d/m') }}
                            </p>
                        </div>
                        <p class="whitespace-nowrap text-[15px] font-semibold tabular-nums">R$ {{ number_format($charge->amount, 2, ',', '.') }}</p>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        @if ($charge->isOverdue())
                            <span class="text-[13px] font-semibold text-danger-ink">Atrasada</span>
                        @else
                            <span class="text-[13px] text-muted">Enviada em {{ $charge->sent_at?->format('d/m') ?? '—' }}</span>
                        @endif
                        <a href="{{ route('charges.index', ['status' => 'pending', 'client' => $charge->client_id]) }}"
                           class="inline-flex items-center min-h-11 px-3.5 rounded-[10px] bg-brand-tint text-[13px] font-semibold text-brand hover:bg-line transition-colors">Confirmar pagamento</a>
                    </div>
                </div>
            @empty
                <p class="border-t border-line py-6 text-center text-sm text-muted">Nenhuma cobrança pendente.</p>
            @endforelse
        </section>

        <section aria-labelledby="proximos" class="rounded-[18px] border border-line bg-white px-[22px] py-5">
            <h2 id="proximos" class="text-lg font-semibold tracking-tight">Próximos dias</h2>
            @if ($upcomingDays->isEmpty())
                <p class="mt-3 text-sm text-muted">Nenhuma sessão nas próximas duas semanas.</p>
            @else
                <ul class="mt-3 flex flex-col">
                    @foreach ($upcomingDays as $date => $count)
                        <li>
                            <a href="{{ route('sessions.index', ['view' => 'day', 'date' => $date]) }}"
                               class="flex items-center justify-between min-h-11 text-[15px] hover:text-brand">
                                <span>{{ ucfirst(Carbon\CarbonImmutable::parse($date)->locale('pt_BR')->translatedFormat('l, j')) }}</span>
                                <span class="text-muted">{{ $count }} {{ $count === 1 ? 'sessão' : 'sessões' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- No desktop o status do Google Agenda fica no menu lateral. --}}
        @if (config('services.google.client_id'))
            <section class="lg:hidden rounded-[18px] border border-line bg-white px-[22px] py-5 flex items-start gap-3">
                <span class="mt-1.5 size-2 rounded-full shrink-0 {{ auth()->user()->hasGoogleCalendar() ? 'bg-accent' : 'bg-line-strong' }}"></span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-[15px] font-semibold">Google Agenda</h2>
                    @if (auth()->user()->hasGoogleCalendar())
                        <p class="mt-0.5 text-sm text-muted">Conectado. Novas sessões aparecem no seu calendário.</p>
                        <form method="POST" action="{{ route('google.disconnect') }}">
                            @csrf
                            <button type="submit" class="min-h-11 text-sm font-medium text-danger-ink hover:underline">Desconectar</button>
                        </form>
                    @else
                        <p class="mt-0.5 text-sm text-muted">Espelhe suas sessões no calendário do Google.</p>
                        <a href="{{ route('google.connect') }}" class="inline-flex items-center min-h-11 text-sm font-semibold text-brand hover:underline">Conectar</a>
                    @endif
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
