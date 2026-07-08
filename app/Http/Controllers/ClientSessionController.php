<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Jobs\RemoveSessionFromGoogleCalendar;
use App\Jobs\SyncSessionToGoogleCalendar;
use App\Mail\SessionScheduledMail;
use App\Models\ClientSession;
use App\Services\CalendarService;
use App\Services\GoogleCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
     * Agenda uma nova sessão.
     */
    public function store(StoreSessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $client = $request->user()->clients()->findOrFail($validated['client_id']);

        $session = $client->sessions()->create([
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'],
            // Congela o valor de contrato vigente quando nenhum valor é informado.
            'value' => $validated['value'] ?? $client->session_value,
            'notes' => $validated['notes'] ?? null,
            'status' => SessionStatus::Scheduled,
        ]);

        // Confirmação por e-mail via fila, quando o cliente tem e-mail cadastrado.
        if ($client->email) {
            Mail::to($client->email)->queue(new SessionScheduledMail($session));
        }

        $this->syncToGoogle($session);

        return redirect()
            ->route('sessions.index', ['month' => $session->scheduled_at->format('Y-m')])
            ->with('status', 'Sessão agendada com sucesso.');
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
