<?php

namespace App\Enums;

enum PaymentCycle: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Interval = 'interval';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal (por sessão)',
            self::Monthly => 'Mensal',
            self::Interval => 'A cada X dias',
        };
    }
}
