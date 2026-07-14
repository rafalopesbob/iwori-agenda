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
        $reference = $this->resolveMonth($month);

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

    /**
     * Semana (domingo a sábado) que contém a data de referência.
     *
     * @return array{
     *     reference: CarbonImmutable,
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     days: array<int, CarbonImmutable>
     * }
     */
    public function weekRange(?string $date = null): array
    {
        $reference = $this->resolveDate($date);
        $start = $reference->startOfWeek(CarbonImmutable::SUNDAY);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->addDays($i);
        }

        return [
            'reference' => $reference,
            'start' => $start,
            'end' => $start->addDays(6)->endOfDay(),
            'days' => $days,
        ];
    }

    /**
     * Converte o parâmetro "Y-m" da URL no primeiro dia do mês,
     * usando o mês atual quando ausente ou inválido.
     */
    public function resolveMonth(?string $month = null): CarbonImmutable
    {
        return ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month))
            ? CarbonImmutable::createFromFormat('!Y-m', $month)
            : CarbonImmutable::now()->startOfMonth();
    }

    /**
     * Converte o parâmetro "Y-m-d" da URL numa data,
     * usando hoje quando ausente ou inválido.
     */
    public function resolveDate(?string $date = null): CarbonImmutable
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);

                // O round-trip rejeita datas com overflow (ex.: mês 13 virando janeiro).
                if ($parsed->format('Y-m-d') === $date) {
                    return $parsed;
                }
            } catch (\Exception) {
                // data inválida: cai no padrão abaixo
            }
        }

        return CarbonImmutable::today();
    }
}
