<?php

namespace App\Http\Controllers;

use App\Enums\SessionStatus;
use App\Models\ClientSession;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Painel com o resumo do dia e do mês do profissional.
     */
    public function index(Request $request, BillingService $billing): View
    {
        $user = $request->user();
        $report = $billing->monthlyReport($user);

        $sessions = ClientSession::query()
            ->whereHas('client', fn ($query) => $query->withoutTrashed()->where('user_id', $user->id));

        $todaySessions = (clone $sessions)
            ->with('client:id,name')
            ->whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->get();

        // Próximos dias com sessão (até duas semanas à frente), para o resumo lateral.
        $upcomingDays = (clone $sessions)
            ->whereBetween('scheduled_at', [today()->addDay(), today()->addDays(15)])
            ->where('status', '!=', SessionStatus::Canceled->value)
            ->orderBy('scheduled_at')
            ->pluck('scheduled_at')
            ->groupBy(fn ($scheduledAt) => $scheduledAt->toDateString())
            ->take(3)
            ->map(fn ($day) => $day->count());

        $pendingCharges = $user->charges()->pending();

        return view('dashboard', [
            'todaySessions' => $todaySessions,
            'upcomingDays' => $upcomingDays,
            'monthTotal' => $report['totals']['total'],
            'monthCompleted' => $report['totals']['completed'],
            // Sessões do mês que não foram canceladas: base da barra de progresso.
            'monthPlanned' => $report['rows']->sum(fn (array $row) => $row['completed'] + $row['no_show'] + $row['no_show_excused'] + $row['scheduled']),
            'pendingTotal' => (float) (clone $pendingCharges)->sum('amount'),
            'pendingCount' => (clone $pendingCharges)->count(),
            'pendingCharges' => (clone $pendingCharges)->with('client:id,name')->oldest('sent_at')->take(3)->get(),
            'activeClients' => $user->clients()->active()->count(),
            'newClients' => $user->clients()->where('created_at', '>=', now()->startOfMonth())->count(),
        ]);
    }
}
