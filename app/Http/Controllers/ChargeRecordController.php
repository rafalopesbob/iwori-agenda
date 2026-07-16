<?php

namespace App\Http\Controllers;

use App\Enums\ChargeStatus;
use App\Http\Requests\ConfirmChargePaymentRequest;
use App\Models\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contas a receber: registros de cobrança e confirmação de pagamento.
 * O disparo de cobranças continua no ChargeController.
 */
class ChargeRecordController extends Controller
{
    /**
     * Lista as cobranças do profissional, com filtros por situação e cliente.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Charge::class);

        $status = ChargeStatus::tryFrom((string) $request->query('status'));
        $clientId = $request->integer('client');

        $charges = $request->user()
            ->charges()
            ->with('client')
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->latest('sent_at')
            ->paginate(15)
            ->withQueryString();

        $totals = [
            'pending' => (float) $request->user()->charges()->pending()->sum('amount'),
            'paid_this_month' => (float) $request->user()->charges()->paid()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];

        return view('charges.index', [
            'charges' => $charges,
            'totals' => $totals,
            'status' => $status,
            'clients' => $request->user()->clients()->orderBy('name')->get(['id', 'name']),
            'clientId' => $clientId,
        ]);
    }

    /**
     * Confirma o recebimento do pagamento, com comprovante opcional.
     */
    public function pay(ConfirmChargePaymentRequest $request, Charge $charge): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt')) {
            if ($charge->receipt_path !== null) {
                Storage::disk('local')->delete($charge->receipt_path);
            }

            $charge->forceFill([
                'receipt_path' => $request->file('receipt')->store("receipts/{$charge->user_id}", 'local'),
            ]);
        }

        $charge->forceFill([
            'status' => ChargeStatus::Paid,
            'paid_at' => $validated['paid_at'] ?? now(),
        ]);
        $charge->notes = $validated['notes'] ?? $charge->notes;
        $charge->save();

        return redirect()->route('charges.index')
            ->with('status', 'Pagamento confirmado.');
    }

    /**
     * Reabre a cobrança (confirmação equivocada); o comprovante é mantido.
     */
    public function reopen(Request $request, Charge $charge): RedirectResponse
    {
        $this->authorize('update', $charge);

        $charge->forceFill([
            'status' => ChargeStatus::Pending,
            'paid_at' => null,
        ])->save();

        return redirect()->route('charges.index')
            ->with('status', 'Cobrança reaberta.');
    }

    /**
     * Exibe o comprovante (disco privado, apenas para o dono).
     */
    public function receipt(Charge $charge): StreamedResponse
    {
        $this->authorize('view', $charge);

        abort_unless($charge->receipt_path !== null, 404);

        return Storage::disk('local')->response($charge->receipt_path);
    }
}
