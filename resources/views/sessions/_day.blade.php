{{-- Visualização diária: lista detalhada das sessões do dia. --}}
@php $daySessions = $sessions->get($day->toDateString(), collect()); @endphp

<div class="bg-white rounded-2xl shadow overflow-hidden">
    @if ($daySessions->isEmpty())
        <div class="p-10 text-center text-gray-500">
            <p class="mb-4">Nenhuma sessão neste dia.</p>
            <a href="{{ route('sessions.create', ['date' => $day->toDateString()]) }}"
               class="bg-mvteal hover:bg-mvteal-dark text-white px-4 py-2 rounded-lg font-medium">
                Agendar neste dia
            </a>
        </div>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($daySessions as $session)
                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <span class="text-lg font-semibold text-mvindigo tabular-nums shrink-0">
                            {{ $session->scheduled_at->format('H:i') }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 truncate">
                                {{ $session->client->name }}
                                @if ($session->isRecurring())
                                    <span title="Faz parte de uma série recorrente">🔁</span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ $session->duration_minutes }} min — R$ {{ number_format($session->value, 2, ',', '.') }}
                            </p>
                        </div>
                        <span class="text-xs font-medium px-2 py-1 rounded-full shrink-0 {{ $session->status->badgeClasses() }}">
                            {{ $session->status->label() }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($session->status === App\Enums\SessionStatus::Scheduled)
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="text-xs bg-mvteal hover:bg-mvteal-dark text-white px-3 py-2 rounded-lg font-medium">✓ Realizado</button>
                            </form>
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="no_show">
                                <button type="submit" title="Cobrada" class="text-xs bg-mvrose-dark hover:bg-mvrose text-white px-3 py-2 rounded-lg font-medium">✗ Falta (não avisou)</button>
                            </form>
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="no_show_excused">
                                <button type="submit" title="Abonada" class="text-xs bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-lg font-medium">Falta (avisou)</button>
                            </form>
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="canceled">
                                <button type="submit" class="text-xs bg-gray-400 hover:bg-gray-500 text-white px-3 py-2 rounded-lg font-medium">– Cancelar</button>
                            </form>
                        @endif

                        @if ($session->status !== App\Enums\SessionStatus::Canceled)
                            <a href="{{ route('sessions.edit', $session) }}"
                               class="text-xs bg-mvindigo-light hover:bg-mvindigo text-white px-3 py-2 rounded-lg font-medium">✎ Editar</a>
                            <form method="POST" action="{{ route('sessions.charge', $session) }}">
                                @csrf
                                <button type="submit" title="Enviar cobrança ({{ $session->client->billing_channel->label() }})"
                                        class="text-xs bg-mvsand-dark hover:bg-mvlilac text-mvindigo px-3 py-2 rounded-lg font-medium">$ Cobrar</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
