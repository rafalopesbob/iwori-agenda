<?php

namespace App\Services;

use App\Enums\BillingChannel;
use App\Enums\ChargeStatus;
use App\Enums\SessionStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private CalendarService $calendar,
    ) {}

    /**
     * Fechamento do ciclo mensal: por cliente, soma as sessões faturáveis
     * (realizadas + faltas não informadas), listando os demais status
     * como apoio. Falta informada é abonada e não entra no total.
     *
     * @return array{
     *     month: CarbonImmutable,
     *     rows: Collection<int, array<string, mixed>>,
     *     totals: array{completed: int, no_show: int, no_show_excused: int, total: float}
     * }
     */
    public function monthlyReport(User $user, ?string $month = null): array
    {
        $reference = $this->calendar->resolveMonth($month);

        $sessions = ClientSession::query()
            ->whereHas('client', fn ($query) => $query->withoutTrashed()->where('user_id', $user->id))
            ->with('client:id,name,billing_channel,phone')
            ->scheduledBetween($reference, $reference->endOfMonth())
            ->get();

        $rows = $sessions
            ->groupBy('client_id')
            ->map(function (Collection $clientSessions) {
                $billable = $clientSessions->filter(fn (ClientSession $session) => $session->status->isBillable());

                return [
                    'client' => $clientSessions->first()->client,
                    'completed' => $clientSessions->where('status', SessionStatus::Completed)->count(),
                    'no_show' => $clientSessions->where('status', SessionStatus::NoShowUnexcused)->count(),
                    'no_show_excused' => $clientSessions->where('status', SessionStatus::NoShowExcused)->count(),
                    'canceled' => $clientSessions->where('status', SessionStatus::Canceled)->count(),
                    'scheduled' => $clientSessions->where('status', SessionStatus::Scheduled)->count(),
                    'total' => (float) $billable->sum('value'),
                ];
            })
            ->sortBy(fn (array $row) => $row['client']->name)
            ->values();

        return [
            'month' => $reference,
            'rows' => $rows,
            'totals' => [
                'completed' => $rows->sum('completed'),
                'no_show' => $rows->sum('no_show'),
                'no_show_excused' => $rows->sum('no_show_excused'),
                'total' => (float) $rows->sum('total'),
            ],
        ];
    }

    /**
     * Valor a cobrar de um cliente no período: sessões faturáveis
     * (realizadas + faltas não informadas) entre as datas.
     *
     * @return array{sessions: Collection<int, ClientSession>, total: float}
     */
    public function periodCharge(Client $client, CarbonInterface $start, CarbonInterface $end): array
    {
        $sessions = $client->sessions()
            ->billable()
            ->scheduledBetween($start, $end)
            ->orderBy('scheduled_at')
            ->get();

        return [
            'sessions' => $sessions,
            'total' => (float) $sessions->sum('value'),
        ];
    }

    /**
     * Registra a cobrança do período e fecha o ciclo do cliente.
     *
     * Uma cobrança por período: retry do job ou novo clique devolvem a
     * cobrança já registrada, sem duplicar.
     */
    public function recordPeriodCharge(Client $client, CarbonInterface $start, CarbonInterface $end, float $amount, BillingChannel $channel): Charge
    {
        return DB::transaction(function () use ($client, $start, $end, $amount, $channel) {
            $charge = Charge::query()
                ->where('client_id', $client->id)
                ->whereNull('client_session_id')
                ->where('period_start', $start->toDateString())
                ->where('period_end', $end->toDateString())
                ->first();

            if (! $charge) {
                $charge = (new Charge([
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'amount' => $amount,
                    'status' => ChargeStatus::Pending,
                    'channel' => $channel,
                    'sent_at' => now(),
                ]))->forceFill([
                    'user_id' => $client->user_id,
                    'client_id' => $client->id,
                ]);
                $charge->save();
            }

            $client->forceFill(['last_charged_at' => now()])->save();

            return $charge;
        });
    }

    /**
     * Registra a cobrança avulsa de uma sessão. Não mexe no ciclo do cliente.
     *
     * Enquanto houver uma cobrança pendente da sessão, ela é reaproveitada.
     */
    public function recordSessionCharge(ClientSession $session, BillingChannel $channel): Charge
    {
        $existing = Charge::query()
            ->where('client_session_id', $session->id)
            ->where('status', ChargeStatus::Pending->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        $client = $session->client;

        $charge = (new Charge([
            'period_start' => $session->scheduled_at->toDateString(),
            'period_end' => $session->scheduled_at->toDateString(),
            'amount' => (float) $session->value,
            'status' => ChargeStatus::Pending,
            'channel' => $channel,
            'sent_at' => now(),
        ]))->forceFill([
            'user_id' => $client->user_id,
            'client_id' => $client->id,
            'client_session_id' => $session->id,
        ]);
        $charge->save();

        return $charge;
    }
}
