<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Models\ClientSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BillingService
{
    public function __construct(
        private CalendarService $calendar,
    ) {}

    /**
     * Fechamento do ciclo mensal: por cliente, soma apenas as sessões
     * realizadas (comparecimento), listando os demais status como apoio.
     *
     * @return array{
     *     month: CarbonImmutable,
     *     rows: Collection<int, array<string, mixed>>,
     *     totals: array{completed: int, no_show: int, total: float}
     * }
     */
    public function monthlyReport(User $user, ?string $month = null): array
    {
        $reference = $this->calendar->resolveMonth($month);

        $sessions = ClientSession::query()
            ->whereHas('client', fn ($query) => $query->where('user_id', $user->id))
            ->with('client:id,name')
            ->scheduledBetween($reference, $reference->endOfMonth())
            ->get();

        $rows = $sessions
            ->groupBy('client_id')
            ->map(function (Collection $clientSessions) {
                $completed = $clientSessions->where('status', SessionStatus::Completed);

                return [
                    'client' => $clientSessions->first()->client,
                    'completed' => $completed->count(),
                    'no_show' => $clientSessions->where('status', SessionStatus::NoShow)->count(),
                    'canceled' => $clientSessions->where('status', SessionStatus::Canceled)->count(),
                    'scheduled' => $clientSessions->where('status', SessionStatus::Scheduled)->count(),
                    'total' => (float) $completed->sum('value'),
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
                'total' => (float) $rows->sum('total'),
            ],
        ];
    }
}
