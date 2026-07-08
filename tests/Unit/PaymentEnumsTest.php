<?php

namespace Tests\Unit;

use App\Enums\BillingChannel;
use App\Enums\PaymentCycle;
use PHPUnit\Framework\TestCase;

class PaymentEnumsTest extends TestCase
{
    public function test_labels_do_ciclo_de_pagamento(): void
    {
        $this->assertSame('Semanal (por sessão)', PaymentCycle::Weekly->label());
        $this->assertSame('Mensal', PaymentCycle::Monthly->label());
        $this->assertSame('A cada X dias', PaymentCycle::Interval->label());
    }

    public function test_labels_do_canal_de_cobranca(): void
    {
        $this->assertSame('E-mail', BillingChannel::Email->label());
        $this->assertSame('WhatsApp', BillingChannel::Whatsapp->label());
        $this->assertSame('E-mail + WhatsApp', BillingChannel::Both->label());
    }

    public function test_canal_define_os_meios_de_envio(): void
    {
        $this->assertTrue(BillingChannel::Email->sendsEmail());
        $this->assertFalse(BillingChannel::Email->sendsWhatsapp());

        $this->assertFalse(BillingChannel::Whatsapp->sendsEmail());
        $this->assertTrue(BillingChannel::Whatsapp->sendsWhatsapp());

        $this->assertTrue(BillingChannel::Both->sendsEmail());
        $this->assertTrue(BillingChannel::Both->sendsWhatsapp());
    }
}
