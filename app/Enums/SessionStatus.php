<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Canceled = 'canceled';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Completed => 'Realizado',
            self::NoShow => 'Falta',
            self::Canceled => 'Cancelado',
        };
    }

    /**
     * Indica se a sessão conta para o faturamento do ciclo mensal.
     */
    public function isBillable(): bool
    {
        return $this === self::Completed;
    }
}
