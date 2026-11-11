<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * Situações do filtro "Mostrar".
     */
    protected const SHOW_FILTERS = [
        'to_charge' => 'Com valor a cobrar agora',
        'no_show' => 'Com faltas',
    ];

    /**
     * Fechamento mensal do profissional autenticado.
     */
    public function index(Request $request, BillingService $billing, WhatsAppService $whatsapp): View
    {
        $report = $billing->monthlyReport($request->user(), $request->query('month'));

        $search = trim((string) $request->query('q'));
        $show = array_key_exists((string) $request->query('show'), self::SHOW_FILTERS)
            ? (string) $request->query('show')
            : null;

        // Prévia do botão Cobrar de cada cliente (período, valor e canal).
        $whatsappOn = $whatsapp->isConfigured();
        $previews = $report['rows']->mapWithKeys(fn (array $row) => [
            $row['client']->id => $billing->chargePreview($row['client'], $whatsappOn),
        ]);

        $needle = Str::lower(Str::ascii($search));
        $rows = $report['rows']
            ->when($needle !== '', fn ($rows) => $rows->filter(
                fn (array $row) => Str::contains(Str::lower(Str::ascii($row['client']->name)), $needle),
            ))
            ->when($show === 'to_charge', fn ($rows) => $rows->filter(
                fn (array $row) => $previews[$row['client']->id]['total'] > 0,
            ))
            ->when($show === 'no_show', fn ($rows) => $rows->filter(
                fn (array $row) => $row['no_show'] + $row['no_show_excused'] > 0,
            ))
            ->values();

        $month = $report['month'];

        return view('billing.index', [
            'month' => $month,
            'rows' => $rows,
            'totals' => $billing->totals($rows),
            'previews' => $previews,
            'search' => $search,
            'show' => $show,
            'showFilters' => self::SHOW_FILTERS,
            'filtered' => $search !== '' || $show !== null,
            'emailsSent' => $billing->chargeEmailsSent($request->user(), $month->startOfMonth(), $month->endOfMonth()),
        ]);
    }
}
