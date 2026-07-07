<?php

namespace App\Http\Controllers;

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

        $todaySessions = ClientSession::query()
            ->whereHas('client', fn ($query) => $query->where('user_id', $user->id))
            ->with('client:id,name')
            ->whereDate('scheduled_at', today())
            ->orderBy('scheduled_at')
            ->get();

        return view('dashboard', [
            'todaySessions' => $todaySessions,
            'monthTotal' => $report['totals']['total'],
            'monthCompleted' => $report['totals']['completed'],
            'activeClients' => $user->clients()->active()->count(),
        ]);
    }
}
