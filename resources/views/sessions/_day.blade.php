{{-- Visualização diária: lista detalhada das sessões do dia, com as ações à mão. --}}
@php $daySessions = $sessions->get($day->toDateString(), collect()); @endphp

<div class="rounded-[18px] border border-line bg-white">
    @if ($daySessions->isEmpty())
        <div class="px-6 py-14 text-center">
            <p class="text-muted">Nenhuma sessão neste dia.</p>
            <a href="{{ route('sessions.create', ['date' => $day->toDateString()]) }}"
               class="mt-5 inline-flex items-center gap-2 min-h-11 px-[18px] rounded-xl bg-brand hover:bg-brand-dark text-white text-[15px] font-semibold transition-colors">
                Agendar neste dia
            </a>
        </div>
    @else
        <ul class="divide-y divide-line">
            @foreach ($daySessions as $session)
                <li class="flex flex-wrap items-center gap-x-5 gap-y-3 px-6 py-4">
                    <span class="w-14 text-lg font-semibold tabular-nums">{{ $session->scheduled_at->format('H:i') }}</span>
                    <div class="min-w-0 flex-1 basis-48">
                        <p class="flex items-center gap-1.5 truncate text-[15px] font-semibold">
                            {{ $session->client->name }}
                            @if ($session->isRecurring())
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-3.5 shrink-0 text-muted" aria-label="Série recorrente" role="img"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                            @endif
                        </p>
                        <p class="mt-0.5 text-[13px] text-muted">
                            {{ $session->duration_minutes }} min · R$ {{ number_format($session->value, 2, ',', '.') }}
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1.5 text-[13px] font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>

                    <div class="flex w-full flex-wrap gap-1.5 lg:w-auto">
                        @if ($session->status === App\Enums\SessionStatus::Scheduled)
                            @foreach ([
                                ['completed', 'Realizada', 'Marcar como realizada', 'border-accent text-accent-ink hover:bg-accent-tint'],
                                ['no_show', 'Falta s/ aviso', 'Falta não informada (cobrada)', 'border-line-strong text-danger-ink hover:bg-danger-tint'],
                                ['no_show_excused', 'Falta c/ aviso', 'Falta informada (abonada)', 'border-line-strong text-warn-ink hover:bg-warn-tint'],
                                ['canceled', 'Cancelar', 'Cancelar sessão', 'border-line-strong text-muted hover:bg-canvas hover:text-ink'],
                            ] as [$status, $label, $title, $tone])
                                <form method="POST" action="{{ route('sessions.status', $session) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" title="{{ $title }}"
                                            class="min-h-11 px-3.5 rounded-[10px] border bg-white text-[13px] font-semibold transition-colors {{ $tone }}">{{ $label }}</button>
                                </form>
                            @endforeach
                        @elseif ($session->status !== App\Enums\SessionStatus::Canceled)
                            <form method="POST" action="{{ route('sessions.status', $session) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="scheduled">
                                <button type="submit" class="min-h-11 px-3 text-[13px] font-medium text-muted underline hover:text-ink">Voltar para agendada</button>
                            </form>
                        @endif

                        @if ($session->status !== App\Enums\SessionStatus::Canceled)
                            <a href="{{ route('sessions.edit', $session) }}"
                               class="inline-flex items-center min-h-11 px-3.5 rounded-[10px] border border-line-strong bg-white hover:bg-canvas text-[13px] font-semibold transition-colors">Editar</a>
                            <form method="POST" action="{{ route('sessions.charge', $session) }}">
                                @csrf
                                <button type="submit" title="Enviar cobrança ({{ $session->client->billing_channel->label() }})"
                                        class="min-h-11 px-3.5 rounded-[10px] border border-line-strong bg-white hover:bg-canvas text-[13px] font-semibold transition-colors">Cobrar</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
