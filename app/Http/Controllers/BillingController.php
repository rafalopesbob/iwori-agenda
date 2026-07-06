<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * Fechamento mensal do profissional autenticado.
     */
    public function index(Request $request, BillingService $billing): View
    {
        $report = $billing->monthlyReport($request->user(), $request->query('month'));

        return view('billing.index', $report);
    }
}
