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
            self::Pending => 'bg-brand-tint text-brand',
            self::Paid => 'bg-accent-tint text-accent-ink',
            self::Canceled => 'bg-neutral-tint text-muted',
        };
    }
}
