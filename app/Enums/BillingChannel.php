<?php

namespace App\Enums;

enum BillingChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Both = 'both';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Whatsapp => 'WhatsApp',
            self::Both => 'E-mail + WhatsApp',
        };
    }

    /**
     * A cobrança deve sair por e-mail?
     */
    public function sendsEmail(): bool
    {
        return $this !== self::Whatsapp;
    }

    /**
     * A cobrança deve sair por WhatsApp?
     */
    public function sendsWhatsapp(): bool
    {
        return $this !== self::Email;
    }
}
