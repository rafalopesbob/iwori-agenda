{{-- Chip de sessão usado nas visualizações de mês e semana. --}}
<div class="rounded-lg px-2 py-1.5 text-xs transition-transform duration-150 {{ $session->status->badgeClasses() }}
            {{ $session->status === App\Enums\SessionStatus::Scheduled ? 'hover:-translate-y-0.5 hover:shadow-sm' : '' }}"
     data-session-item
     @if ($session->status === App\Enums\SessionStatus::Scheduled)
         data-session-chip="{{ route('sessions.move', $session) }}"
     @endif>
    <div class="font-medium truncate {{ $session->status === App\Enums\SessionStatus::Scheduled ? 'cursor-grab touch-none select-none' : '' }}"
         @if ($session->status === App\Enums\SessionStatus::Scheduled) data-drag-handle @endif
         title="{{ $session->client->name }} — {{ $session->status->label() }}{{ $session->isRecurring() ? ' (série recorrente)' : '' }}">
        {{ $session->scheduled_at->format('H:i') }} {{ $session->client->name }}
        @if ($session->isRecurring())
            <span title="Faz parte de uma série recorrente">🔁</span>
        @endif
    </div>

    <div class="flex flex-wrap gap-1.5 mt-1.5">
        @if ($session->status === App\Enums\SessionStatus::Scheduled)
            <form method="POST" action="{{ route('sessions.status', $session) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="completed">
                <button type="submit" title="Marcar como Realizado"
                        class="w-8 h-8 rounded-lg bg-mvteal text-white hover:bg-mvteal-dark leading-none text-base font-bold flex items-center justify-center">✓</button>
            </form>
            <form method="POST" action="{{ route('sessions.status', $session) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="no_show">
                <button type="submit" title="Falta não informada (não avisou — cobrada)"
                        class="w-8 h-8 rounded-lg bg-mvrose-dark text-white hover:bg-mvrose leading-none text-base font-bold flex items-center justify-center">✗</button>
            </form>
            <form method="POST" action="{{ route('sessions.status', $session) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="no_show_excused">
                <button type="submit" title="Falta informada (avisou — abonada)"
                        class="w-8 h-8 rounded-lg bg-amber-500 text-white hover:bg-amber-600 leading-none text-base flex items-center justify-center">⚠</button>
            </form>
            <form method="POST" action="{{ route('sessions.status', $session) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="canceled">
                <button type="submit" title="Cancelar sessão"
                        class="w-8 h-8 rounded-lg bg-gray-400 text-white hover:bg-gray-500 leading-none text-base font-bold flex items-center justify-center">–</button>
            </form>
        @endif

        @if ($session->status !== App\Enums\SessionStatus::Canceled)
            <a href="{{ route('sessions.edit', $session) }}" title="Editar / reagendar"
               class="w-8 h-8 rounded-lg bg-mvindigo-light text-white hover:bg-mvindigo leading-none text-base flex items-center justify-center">✎</a>
            <form method="POST" action="{{ route('sessions.charge', $session) }}">
                @csrf
                <button type="submit" title="Enviar cobrança desta sessão ({{ $session->client->billing_channel->label() }})"
                        class="w-8 h-8 rounded-lg bg-mvsand-dark text-mvindigo hover:bg-mvlilac leading-none text-base font-bold flex items-center justify-center">$</button>
            </form>
        @endif
    </div>
</div>
