<?php

namespace App\Enums;

enum MessageChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Whatsapp => 'WhatsApp',
        };
    }

    /**
     * Tamanho máximo do texto. No WhatsApp o texto vai dentro do link
     * wa.me, e links muito longos falham em alguns navegadores.
     */
    public function maxBodyLength(): int
    {
        return match ($this) {
            self::Email => 5000,
            self::Whatsapp => 1500,
        };
    }
}
