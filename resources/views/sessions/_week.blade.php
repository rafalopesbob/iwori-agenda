{{-- Visualização semanal: grade de horários, cada sessão posicionada pelo início e pela duração. --}}
@php
    $hourHeight = 56;
    $weekSessions = collect($week['days'])->flatMap(fn ($weekDay) => $sessions->get($weekDay->toDateString(), collect()));
    $minutesOf = fn ($session) => $session->scheduled_at->hour * 60 + $session->scheduled_at->minute;
    $startHour = min(8, $weekSessions->min(fn ($session) => $session->scheduled_at->hour) ?? 8);
    $endHour = min(24, max(20, $weekSessions->max(fn ($session) => (int) ceil(($minutesOf($session) + $session->duration_minutes) / 60)) ?? 20));
    $gridHeight = ($endHour - $startHour) * $hourHeight;
@endphp

<div class="min-w-[760px]">
    <div class="flex border-b border-line">
        <div class="w-16 shrink-0"></div>
        @foreach ($week['days'] as $weekDay)
            <a href="{{ route('sessions.index', ['view' => 'day', 'date' => $weekDay->toDateString()]) }}"
               title="Ver o dia {{ $weekDay->format('d/m') }}"
               class="flex-1 min-w-0 border-l border-line/70 px-2 py-3.5 text-center hover:bg-canvas transition-colors">
                <span class="block text-[13px] font-medium text-muted">{{ ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][$weekDay->dayOfWeek] }}</span>
                <span class="mx-auto mt-1.5 flex size-9 items-center justify-center rounded-full text-[17px] font-semibold
                             {{ $weekDay->isToday() ? 'bg-brand text-white' : 'text-ink' }}">{{ $weekDay->day }}</span>
            </a>
        @endforeach
    </div>

    <div class="flex">
        <div class="relative w-16 shrink-0" style="height: {{ $gridHeight }}px">
            @for ($hour = $startHour; $hour < $endHour; $hour++)
                <span class="absolute right-2.5 text-xs tabular-nums text-muted" style="top: {{ max(2, ($hour - $startHour) * $hourHeight - 7) }}px">{{ sprintf('%02d:00', $hour) }}</span>
            @endfor
        </div>

        @foreach ($week['days'] as $weekDay)
            @php
                // Distribui sessões simultâneas em faixas lado a lado.
                $lanesEnd = [];
                $placed = $sessions->get($weekDay->toDateString(), collect())->map(function ($session) use (&$lanesEnd, $minutesOf) {
                    $start = $minutesOf($session);
                    $lane = collect($lanesEnd)->search(fn ($end) => $end <= $start);
                    $lane = $lane === false ? count($lanesEnd) : $lane;
                    $lanesEnd[$lane] = $start + $session->duration_minutes;

                    return ['session' => $session, 'start' => $start, 'lane' => $lane];
                });
                $lanes = max(1, count($lanesEnd));
            @endphp
            <div class="relative flex-1 min-w-0 border-l border-line/70 cursor-pointer transition-colors hover:bg-brand-tint/30 {{ $weekDay->isToday() ? 'bg-canvas/60' : '' }}"
                 style="height: {{ $gridHeight }}px; background-image: repeating-linear-gradient(to bottom, transparent 0, transparent {{ $hourHeight - 1 }}px, #f1f0f6 {{ $hourHeight - 1 }}px, #f1f0f6 {{ $hourHeight }}px)"
                 data-calendar-day="{{ $weekDay->toDateString() }}"
                 data-day-url="{{ route('sessions.create', ['date' => $weekDay->toDateString()]) }}"
                 title="Agendar neste dia">
                @foreach ($placed as $item)
                    @php
                        $top = ($item['start'] - $startHour * 60) / 60 * $hourHeight + 2;
                        $height = max(26, $item['session']->duration_minutes / 60 * $hourHeight - 4);
                        $width = 100 / $lanes;
                    @endphp
                    @include('sessions._chip', [
                        'session' => $item['session'],
                        'class' => 'absolute',
                        'style' => sprintf('top: %dpx; height: %dpx; left: calc(%.4f%% + 3px); width: calc(%.4f%% - 6px)', $top, $height, $item['lane'] * $width, $width),
                    ])
                @endforeach
            </div>
        @endforeach
    </div>
</div>
