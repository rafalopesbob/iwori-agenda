<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NoShowUnexcused = 'no_show';
    case NoShowExcused = 'no_show_excused';
    case Canceled = 'canceled';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Completed => 'Realizado',
            self::NoShowUnexcused => 'Falta não informada',
            self::NoShowExcused => 'Falta informada',
            self::Canceled => 'Cancelado',
        };
    }

    /**
     * Indica se a sessão conta para o faturamento do ciclo.
     *
     * Falta não informada é cobrada; falta informada é abonada.
     */
    public function isBillable(): bool
    {
        return $this === self::Completed || $this === self::NoShowUnexcused;
    }

    /**
     * Status que entram no faturamento (para uso em queries).
     *
     * @return array<int, self>
     */
    public static function billableCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isBillable()));
    }

    /**
     * Classes Tailwind do badge de status no calendário.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-mvlilac-light text-mvindigo',
            self::Completed => 'bg-mvteal-light text-mvteal-dark',
            self::NoShowUnexcused => 'bg-mvrose-light text-mvrose-dark',
            self::NoShowExcused => 'bg-amber-100 text-amber-800',
            self::Canceled => 'bg-gray-100 text-gray-500 line-through',
        };
    }
}
