{{-- Visualização mensal: grade de semanas. --}}
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="grid grid-cols-7 bg-gray-50 text-gray-700 text-xs font-medium text-center">
        @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
            <div class="py-2">{{ $weekday }}</div>
        @endforeach
    </div>

    @foreach ($grid['weeks'] as $weekDays)
        <div class="grid grid-cols-7 divide-x divide-gray-100 border-t border-gray-100">
            @foreach ($weekDays as $gridDay)
                @php
                    $isCurrentMonth = $gridDay->isSameMonth($grid['month']);
                    $daySessions = $sessions->get($gridDay->toDateString(), collect());
                @endphp
                <div class="min-h-28 p-1.5 cursor-pointer transition-colors hover:bg-mvteal-light/40 {{ $isCurrentMonth ? '' : 'bg-gray-50' }}"
                     data-calendar-day="{{ $gridDay->toDateString() }}"
                     data-day-url="{{ route('sessions.create', ['date' => $gridDay->toDateString()]) }}"
                     title="Agendar neste dia">
                    <div class="flex items-center mb-1">
                        <a href="{{ route('sessions.index', ['view' => 'day', 'date' => $gridDay->toDateString()]) }}"
                           data-session-item title="Ver o dia {{ $gridDay->format('d/m') }}"
                           class="text-xs font-medium px-1.5 py-0.5 rounded-full hover:underline
                            {{ $gridDay->isToday() ? 'bg-mvrose-dark text-white' : ($isCurrentMonth ? 'text-gray-700' : 'text-gray-400') }}">
                            {{ $gridDay->day }}
                        </a>
                    </div>

                    <div class="space-y-1.5">
                        @foreach ($daySessions as $session)
                            @include('sessions._chip', ['session' => $session])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
