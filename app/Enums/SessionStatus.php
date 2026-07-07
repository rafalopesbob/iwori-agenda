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

    /**
     * Classes Tailwind do badge de status no calendário.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-mvlilac-light text-mvindigo',
            self::Completed => 'bg-mvteal-light text-mvteal-dark',
            self::NoShow => 'bg-mvrose-light text-mvrose-dark',
            self::Canceled => 'bg-gray-100 text-gray-500 line-through',
        };
    }
}
