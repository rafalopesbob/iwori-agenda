<?php

namespace App\Enums;

enum ChargeStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Canceled = 'canceled';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Paid => 'Paga',
            self::Canceled => 'Cancelada',
        };
    }

    /**
     * Classes Tailwind do badge de status.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-mvrose-light text-mvrose-dark',
            self::Paid => 'bg-mvteal-light text-mvteal-dark',
            self::Canceled => 'bg-gray-100 text-gray-500',
        };
    }
}
