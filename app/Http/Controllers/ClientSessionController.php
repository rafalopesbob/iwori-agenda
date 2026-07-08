<?php

namespace App\Http\Controllers;

use App\Enums\RecurrenceFrequency;
use App\Enums\SessionStatus;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Jobs\RemoveSessionFromGoogleCalendar;
use App\Jobs\SyncSessionToGoogleCalendar;
use App\Mail\SessionScheduledMail;
use App\Models\Client;
use App\Models\ClientSession;
use App\Services\CalendarService;
use App\Services\GoogleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientSessionController extends Controller
{
    /**
     * Calendário mensal com as sessões dos clientes do profissional.
     */
    public function index(Request $request, CalendarService $calendar): View
    {
        $grid = $calendar->monthGrid($request->query('month'));

        $sessions = ClientSession::query()
            ->whereHas('client', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('client:id,name,billing_channel')
            ->scheduledBetween($grid['start'], $grid['end'])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn (ClientSession $session) => $session->scheduled_at->toDateString());

        return view('sessions.index', [
            'grid' => $grid,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Formulário de agendamento.
     */
    public function create(Request $request): View
    {
        $clients = $request->user()->clients()->active()->orderBy('name')->get();

        return view('sessions.create', [
            'clients' => $clients,
            'presetDate' => $request->query('date'),
        ]);
    }

    /**
     * Agenda uma nova sessão (ou uma série recorrente).
     */
    public function store(StoreSessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $client = $request->user()->clients()->findOrFail($validated['client_id']);
        $frequency = isset($validated['recurrence']) ? RecurrenceFrequency::from($validated['recurrence']) : null;
        $occurrences = $frequency ? $validated['recurrence_count'] : 1;
        // Cada série ganha um identificador próprio para permitir cancelar as futuras em lote.
        $groupId = $frequency ? (string) Str::ulid() : null;

        $scheduledAt = CarbonImmutable::parse($validated['scheduled_at']);
        $first = null;

        for ($i = 0; $i < $occurrences; $i++) {
            $session = $this->createOccurrence($client, $scheduledAt, $validated, $groupId);

            $first ??= $session;

            // Evita enviar a confirmação por e-mail uma vez por ocorrência da série.
            if ($i === 0 && $client->email) {
                Mail::to($client->email)->queue(new SessionScheduledMail($session));
            }

            $this->syncToGoogle($session);

            $scheduledAt = $frequency
                ? $frequency->nextOccurrence($scheduledAt, $validated['recurrence_custom_days'] ?? null)
                : $scheduledAt;
        }

        $message = $occurrences > 1
            ? "Sessão agendada com {$occurrences} repetições."
            : 'Sessão agendada com sucesso.';

        return redirect()
            ->route('sessions.index', ['month' => $first->scheduled_at->format('Y-m')])
            ->with('status', $message);
    }

    /**
     * Cria uma única ocorrência da sessão.
     *
     * @param array<string, mixed> $validated
     */
    protected function createOccurrence(Client $client, CarbonImmutable $scheduledAt, array $validated, ?string $groupId): ClientSession
    {
        $session = $client->sessions()->make([
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $validated['duration_minutes'],
            // Congela o valor de contrato vigente quando nenhum valor é informado.
            'value' => $validated['value'] ?? $client->session_value,
            'notes' => $validated['notes'] ?? null,
            'status' => SessionStatus::Scheduled,
        ]);

        $session->recurrence_group_id = $groupId;
        $session->save();

        return $session;
    }

    /**
     * Formulário de reagendamento/edição da sessão.
     */
    public function edit(ClientSession $session): View
    {
        $this->authorize('update', $session);

        return view('sessions.edit', compact('session'));
    }

    /**
     * Reagenda/atualiza a sessão e ressincroniza o evento no Google.
     */
    public function update(UpdateSessionRequest $request, ClientSession $session): RedirectResponse
    {
        $validated = $request->validated();

        $session->update([
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'],
            'value' => $validated['value'] ?? $session->value,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncToGoogle($session);

        return redirect()
            ->route('sessions.index', ['month' => $session->scheduled_at->format('Y-m')])
            ->with('status', 'Sessão atualizada com sucesso.');
    }

    /**
     * Move a sessão para outro dia (arrastar e soltar), mantendo o horário.
     */
    public function move(Request $request, ClientSession $session): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $session);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $session->update([
            'scheduled_at' => CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'])
                ->setTimeFrom($session->scheduled_at),
        ]);

        $this->syncToGoogle($session);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'scheduled_at' => $session->scheduled_at->toIso8601String(),
            ]);
        }

        return back()->with('status', 'Sessão movida para '.$session->scheduled_at->format('d/m/Y').'.');
    }

    /**
     * Marca o status da sessão (Realizado, Falta ou Cancelado).
     */
    public function updateStatus(Request $request, ClientSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(SessionStatus::class)],
        ]);

        $session->update(['status' => $validated['status']]);

        // Sessão cancelada sai do Google Calendar do profissional.
        if ($session->status === SessionStatus::Canceled && $session->google_event_id) {
            RemoveSessionFromGoogleCalendar::dispatch($session);
        }

        return back()->with('status', 'Sessão marcada como '.$session->status->label().'.');
    }

    /**
     * Cancela as demais ocorrências agendadas da mesma série recorrente,
     * a partir desta sessão (inclusive). Sessões já realizadas, com falta
     * ou já canceladas não são alteradas.
     */
    public function cancelRecurrence(ClientSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        if (! $session->isRecurring()) {
            return back()->with('status', 'Esta sessão não faz parte de uma série recorrente.');
        }

        $futureSessions = ClientSession::query()
            ->sameRecurrenceGroup($session)
            ->where('status', SessionStatus::Scheduled)
            ->where('scheduled_at', '>=', $session->scheduled_at)
            ->get();

        $futureSessions->each(function (ClientSession $futureSession) {
            $futureSession->update(['status' => SessionStatus::Canceled]);

            if ($futureSession->google_event_id) {
                RemoveSessionFromGoogleCalendar::dispatch($futureSession);
            }
        });

        return redirect()
            ->route('sessions.index', ['month' => $session->scheduled_at->format('Y-m')])
            ->with('status', "{$futureSessions->count()} sessão(ões) futura(s) da série foram canceladas.");
    }

    /**
     * Espelha a sessão no Google Calendar quando o profissional conectou
     * a conta (upsert: cria ou atualiza, sem duplicar).
     */
    protected function syncToGoogle(ClientSession $session): void
    {
        if (app(GoogleCalendarService::class)->isConfigured() && $session->client->user->hasGoogleCalendar()) {
            SyncSessionToGoogleCalendar::dispatch($session);
        }
    }
}
