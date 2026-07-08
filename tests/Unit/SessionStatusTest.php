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
        $this->assertSame('Falta não informada', SessionStatus::NoShowUnexcused->label());
        $this->assertSame('Falta informada', SessionStatus::NoShowExcused->label());
        $this->assertSame('Cancelado', SessionStatus::Canceled->label());
    }

    public function test_realizada_e_falta_nao_informada_sao_faturaveis(): void
    {
        $this->assertTrue(SessionStatus::Completed->isBillable());
        $this->assertTrue(SessionStatus::NoShowUnexcused->isBillable());
    }

    public function test_falta_informada_agendada_e_cancelada_nao_faturam(): void
    {
        $this->assertFalse(SessionStatus::NoShowExcused->isBillable());
        $this->assertFalse(SessionStatus::Scheduled->isBillable());
        $this->assertFalse(SessionStatus::Canceled->isBillable());
    }

    public function test_billable_cases_retorna_exatamente_os_faturaveis(): void
    {
        $this->assertEqualsCanonicalizing(
            [SessionStatus::Completed, SessionStatus::NoShowUnexcused],
            SessionStatus::billableCases(),
        );
    }
}
