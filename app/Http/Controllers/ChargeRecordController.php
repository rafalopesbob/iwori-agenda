<?php

namespace App\Http\Controllers;

use App\Enums\ChargeStatus;
use App\Http\Requests\ConfirmChargePaymentRequest;
use App\Models\Charge;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
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
    public function index(Request $request, BillingService $billing): View
    {
        $this->authorize('viewAny', Charge::class);

        // Situação: pendentes, atrasadas (pendentes há mais de 7 dias) ou pagas.
        $status = in_array($request->query('status'), ['pending', 'overdue', 'paid'], true)
            ? (string) $request->query('status')
            : null;
        $clientId = $request->integer('client');
        // Mês em que a cobrança foi enviada (AAAA-MM).
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', (string) $request->query('month'))
            : null;
        // Situação do e-mail: saiu, na fila ou falhou.
        $email = in_array($request->query('email'), ['sent', 'queued', 'failed'], true)
            ? (string) $request->query('email')
            : null;

        $charges = $request->user()
            ->charges()
            ->with('client')
            ->when($status === 'pending', fn ($query) => $query->pending())
            ->when($status === 'overdue', fn ($query) => $query->overdue())
            ->when($status === 'paid', fn ($query) => $query->paid())
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->when($month, fn ($query) => $query->whereBetween('sent_at', [$month->startOfMonth(), $month->endOfMonth()]))
            ->when($email === 'failed', fn ($query) => $query->whereNotNull('email_failed_at'))
            ->when($email === 'sent', fn ($query) => $query->whereNull('email_failed_at')
                ->whereNotNull('email_sent_at')->whereColumn('email_sent_at', '>=', 'email_queued_at'))
            ->when($email === 'queued', fn ($query) => $query->whereNull('email_failed_at')->whereNotNull('email_queued_at')
                ->where(fn ($q) => $q->whereNull('email_sent_at')->orWhereColumn('email_sent_at', '<', 'email_queued_at')))
            ->latest('sent_at')
            ->paginate(15)
            ->withQueryString();

        $emailsMonth = $month ?? CarbonImmutable::now()->startOfMonth();

        $totals = [
            'pending' => (float) $request->user()->charges()->pending()->sum('amount'),
            'paid_this_month' => (float) $request->user()->charges()->paid()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'emails_sent' => $billing->chargeEmailsSent($request->user(), $emailsMonth->startOfMonth(), $emailsMonth->endOfMonth()),
        ];

        return view('charges.index', [
            'charges' => $charges,
            'totals' => $totals,
            'emailsMonth' => $emailsMonth,
            'status' => $status,
            'clients' => $request->user()->clients()->orderBy('name')->get(['id', 'name']),
            'clientId' => $clientId,
            'month' => $month,
            'email' => $email,
            'filtered' => $status !== null || $clientId || $month !== null || $email !== null,
        ]);
    }

    /**
     * Confirma o recebimento do pagamento, com comprovante opcional.
     */
    public function pay(ConfirmChargePaymentRequest $request, Charge $charge): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt')) {
            $disk = config('filesystems.uploads');

            if ($charge->receipt_path !== null) {
                Storage::disk($disk)->delete($charge->receipt_path);
            }

            $charge->forceFill([
                'receipt_path' => $request->file('receipt')->store("receipts/{$charge->user_id}", $disk),
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

        $disk = Storage::disk(config('filesystems.uploads'));

        abort_unless($charge->receipt_path !== null && $disk->exists($charge->receipt_path), 404);

        return $disk->response($charge->receipt_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
