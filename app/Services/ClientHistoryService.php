<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Models\Client;
use App\Models\ClientSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Histórico de um cliente para a ficha dele: resumo de sessões e de
 * dinheiro, e o faturamento mês a mês.
 */
class ClientHistoryService
{
    /**
     * Totais de todo o histórico do cliente.
     *
     * @return array{
     *     completed: int, no_show: int, no_show_excused: int, canceled: int,
     *     billed: float, received: float, pending: float, overdue: int,
     *     next: ?ClientSession, first: ?CarbonImmutable
     * }
     */
    public function summary(Client $client): array
    {
        $byStatus = $client->sessions()
            ->toBase()
            ->selectRaw('status, count(*) as total, coalesce(sum(value), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (SessionStatus $status) => (int) ($byStatus[$status->value]->total ?? 0);
        $amount = fn (SessionStatus $status) => (float) ($byStatus[$status->value]->amount ?? 0);

        $charges = $client->charges();

        return [
            'completed' => $count(SessionStatus::Completed),
            'no_show' => $count(SessionStatus::NoShowUnexcused),
            'no_show_excused' => $count(SessionStatus::NoShowExcused),
            'canceled' => $count(SessionStatus::Canceled),
            // Faturado: sessões realizadas e faltas sem aviso (mesma regra do Faturamento).
            'billed' => $amount(SessionStatus::Completed) + $amount(SessionStatus::NoShowUnexcused),
            'received' => (float) (clone $charges)->paid()->sum('amount'),
            'pending' => (float) (clone $charges)->pending()->sum('amount'),
            'overdue' => (clone $charges)->overdue()->count(),
            'next' => $client->sessions()
                ->where('status', SessionStatus::Scheduled->value)
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')
                ->first(),
            'first' => ($first = $client->sessions()->min('scheduled_at')) ? CarbonImmutable::parse($first) : null,
        ];
    }

    /**
     * Mês a mês, dos últimos 12 meses (o atual incluído), só os meses com sessões.
     *
     * @return Collection<int, array{month: CarbonImmutable, completed: int, no_show: int, no_show_excused: int, canceled: int, scheduled: int, billed: float}>
     */
    public function monthly(Client $client, int $months = 12): Collection
    {
        $from = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);

        return $client->sessions()
            ->where('scheduled_at', '>=', $from)
            ->get(['scheduled_at', 'status', 'value'])
            ->groupBy(fn (ClientSession $session) => $session->scheduled_at->format('Y-m'))
            ->map(fn (Collection $sessions, string $month) => [
                'month' => CarbonImmutable::createFromFormat('!Y-m', $month),
                'completed' => $sessions->where('status', SessionStatus::Completed)->count(),
                'no_show' => $sessions->where('status', SessionStatus::NoShowUnexcused)->count(),
                'no_show_excused' => $sessions->where('status', SessionStatus::NoShowExcused)->count(),
                'canceled' => $sessions->where('status', SessionStatus::Canceled)->count(),
                'scheduled' => $sessions->where('status', SessionStatus::Scheduled)->count(),
                'billed' => (float) $sessions->filter(fn (ClientSession $session) => $session->status->isBillable())->sum('value'),
            ])
            ->sortKeysDesc()
            ->values();
    }
}
