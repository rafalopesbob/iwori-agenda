<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Http\Requests\StoreSessionRequest;
use App\Models\ClientSession;
use App\Services\CalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with('client:id,name')
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

        return redirect()
            ->route('sessions.index', ['month' => $session->scheduled_at->format('Y-m')])
            ->with('status', 'Sessão agendada com sucesso.');
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

        return back()->with('status', 'Sessão marcada como '.$session->status->label().'.');
    }
}
