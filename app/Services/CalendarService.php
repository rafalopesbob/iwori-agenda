<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class CalendarService
{
    /**
     * Monta a grade mensal do calendário (semanas de domingo a sábado,
     * incluindo os dias vizinhos do mês anterior/seguinte).
     *
     * @return array{
     *     month: CarbonImmutable,
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     weeks: array<int, array<int, CarbonImmutable>>
     * }
     */
    public function monthGrid(?string $month = null): array
    {
        $reference = ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month))
            ? CarbonImmutable::createFromFormat('!Y-m', $month)
            : CarbonImmutable::now()->startOfMonth();

        $start = $reference->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $reference->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return [
            'month' => $reference,
            'start' => $start,
            'end' => $end->endOfDay(),
            'weeks' => array_chunk($days, 7),
        ];
    }
}
