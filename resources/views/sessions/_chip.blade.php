{{-- Sessão compacta nas visualizações de mês e semana: clique mostra os detalhes; arraste (se agendada) reagenda. --}}
@php $draggable = $session->status === App\Enums\SessionStatus::Scheduled; @endphp
<button type="button"
        data-session-item
        @if ($draggable) data-session-chip="{{ route('sessions.move', $session) }}" data-drag-handle @endif
        @click="sel = {{ $session->id }}; $nextTick(() => window.innerWidth < 1280 && $refs.detalhes.scrollIntoView({ behavior: 'smooth' }))"
        :class="sel === {{ $session->id }} ? 'ring-2 ring-brand' : ''"
        title="{{ $session->client->name }} — {{ $session->status->label() }}{{ $session->isRecurring() ? ' (série recorrente)' : '' }}"
        aria-label="{{ $session->scheduled_at->format('H:i') }}, {{ $session->client->name }}, {{ $session->status->label() }}"
        class="block w-full overflow-hidden rounded-lg px-2 py-1.5 text-left transition-shadow {{ $session->status->badgeClasses() }} {{ $draggable ? 'cursor-grab select-none hover:shadow-sm' : 'cursor-pointer' }} {{ $class ?? '' }}"
        @isset($style) style="{{ $style }}" @endisset>
    <span class="flex items-center gap-1 text-xs font-semibold tabular-nums">
        {{ $session->scheduled_at->format('H:i') }}
        @if ($session->isRecurring())
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3 opacity-70" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
        @endif
    </span>
    <span class="block truncate text-[13px] font-medium">{{ $session->client->name }}</span>
</button>
