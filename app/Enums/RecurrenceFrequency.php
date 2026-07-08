<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum RecurrenceFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanalmente',
            self::Biweekly => 'A cada 15 dias',
            self::Monthly => 'Mensalmente',
        };
    }

    /**
     * Data da próxima ocorrência a partir da data informada.
     */
    public function nextOccurrence(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Weekly => $date->addWeek(),
            self::Biweekly => $date->addDays(15),
            // addMonthNoOverflow evita que dia 31 vire dia 1 do mês seguinte.
            self::Monthly => $date->addMonthNoOverflow(),
        };
    }
}
