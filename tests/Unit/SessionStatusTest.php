<?php

namespace Tests\Unit;

use App\Enums\SessionStatus;
use PHPUnit\Framework\TestCase;

class SessionStatusTest extends TestCase
{
    public function test_labels_em_portugues(): void
    {
        $this->assertSame('Agendado', SessionStatus::Scheduled->label());
        $this->assertSame('Realizado', SessionStatus::Completed->label());
        $this->assertSame('Falta', SessionStatus::NoShow->label());
        $this->assertSame('Cancelado', SessionStatus::Canceled->label());
    }

    public function test_apenas_sessao_realizada_e_faturavel(): void
    {
        $this->assertTrue(SessionStatus::Completed->isBillable());
        $this->assertFalse(SessionStatus::Scheduled->isBillable());
        $this->assertFalse(SessionStatus::NoShow->isBillable());
        $this->assertFalse(SessionStatus::Canceled->isBillable());
    }
}
