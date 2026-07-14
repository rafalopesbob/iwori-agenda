{{-- Visualização semanal: 7 colunas com mais espaço por dia. --}}
<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="grid grid-cols-7 divide-x divide-gray-100">
        @foreach ($week['days'] as $weekDay)
            @php $daySessions = $sessions->get($weekDay->toDateString(), collect()); @endphp
            <div class="flex flex-col min-h-[26rem]">
                <a href="{{ route('sessions.index', ['view' => 'day', 'date' => $weekDay->toDateString()]) }}"
                   title="Ver o dia {{ $weekDay->format('d/m') }}"
                   class="py-2 text-center border-b border-gray-100 hover:bg-mvteal-light/40 transition-colors
                          {{ $weekDay->isToday() ? 'bg-mvteal-light' : 'bg-gray-50' }}">
                    <p class="text-xs text-gray-500">{{ ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][$weekDay->dayOfWeek] }}</p>
                    <p class="text-lg font-semibold {{ $weekDay->isToday() ? 'text-mvteal-dark' : 'text-mvindigo' }}">
                        {{ $weekDay->day }}
                    </p>
                </a>

                <div class="flex-1 p-1.5 space-y-1.5 cursor-pointer transition-colors hover:bg-mvteal-light/40"
                     data-calendar-day="{{ $weekDay->toDateString() }}"
                     data-day-url="{{ route('sessions.create', ['date' => $weekDay->toDateString()]) }}"
                     title="Agendar neste dia">
                    @foreach ($daySessions as $session)
                        @include('sessions._chip', ['session' => $session])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
