{{-- Visualização mensal: grade de semanas. --}}
<div class="min-w-[760px]">
    <div class="grid grid-cols-7 border-b border-line text-[13px] font-medium text-muted">
        @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
            <div class="px-3 py-3">{{ $weekday }}</div>
        @endforeach
    </div>

    @foreach ($grid['weeks'] as $weekDays)
        <div class="grid grid-cols-7 border-b border-line last:border-b-0">
            @foreach ($weekDays as $gridDay)
                @php
                    $isCurrentMonth = $gridDay->isSameMonth($grid['month']);
                    $daySessions = $sessions->get($gridDay->toDateString(), collect());
                    $visible = $daySessions->take(3);
                    $hidden = $daySessions->count() - $visible->count();
                @endphp
                <div class="min-h-[120px] border-r border-line p-2 last:border-r-0 cursor-pointer transition-colors hover:bg-brand-tint/40 {{ $isCurrentMonth ? 'bg-white' : 'bg-canvas' }}"
                     data-calendar-day="{{ $gridDay->toDateString() }}"
                     data-day-url="{{ route('sessions.create', ['date' => $gridDay->toDateString()]) }}"
                     title="Agendar neste dia">
                    <a href="{{ route('sessions.index', ['view' => 'day', 'date' => $gridDay->toDateString()]) }}"
                       data-session-item title="Ver o dia {{ $gridDay->format('d/m') }}"
                       class="mb-1.5 flex size-7 items-center justify-center rounded-full text-[13px] font-semibold hover:ring-2 hover:ring-brand/30
                              {{ $gridDay->isToday() ? 'bg-brand text-white' : ($isCurrentMonth ? 'text-ink' : 'text-subtle') }}">
                        {{ $gridDay->day }}
                    </a>

                    <div class="flex flex-col gap-1">
                        @foreach ($visible as $session)
                            @include('sessions._chip', ['session' => $session])
                        @endforeach
                        @if ($hidden > 0)
                            <a href="{{ route('sessions.index', ['view' => 'day', 'date' => $gridDay->toDateString()]) }}" data-session-item
                               class="px-1.5 py-0.5 text-xs font-medium text-muted hover:text-ink hover:underline">+{{ $hidden }} {{ $hidden === 1 ? 'sessão' : 'sessões' }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
