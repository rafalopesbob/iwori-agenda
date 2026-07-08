<?php

namespace Tests\Unit;

use App\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RecurrenceFrequencyTest extends TestCase
{
    public function test_labels_em_portugues(): void
    {
        $this->assertSame('Semanalmente', RecurrenceFrequency::Weekly->label());
        $this->assertSame('A cada 15 dias', RecurrenceFrequency::Biweekly->label());
        $this->assertSame('Mensalmente', RecurrenceFrequency::Monthly->label());
        $this->assertSame('Personalizada (a cada X dias)', RecurrenceFrequency::Custom->label());
    }

    public function test_semanal_avanca_sete_dias(): void
    {
        $next = RecurrenceFrequency::Weekly->nextOccurrence(CarbonImmutable::parse('2026-07-07 14:00:00'));

        $this->assertSame('2026-07-14 14:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_quinzenal_avanca_quinze_dias(): void
    {
        $next = RecurrenceFrequency::Biweekly->nextOccurrence(CarbonImmutable::parse('2026-07-07 14:00:00'));

        $this->assertSame('2026-07-22 14:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_mensal_avanca_um_mes(): void
    {
        $next = RecurrenceFrequency::Monthly->nextOccurrence(CarbonImmutable::parse('2026-07-10 14:00:00'));

        $this->assertSame('2026-08-10 14:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_mensal_no_dia_31_nao_estoura_para_o_mes_seguinte(): void
    {
        $next = RecurrenceFrequency::Monthly->nextOccurrence(CarbonImmutable::parse('2026-01-31 09:00:00'));

        // Fevereiro não tem 31: deve cair no último dia do mês, não em março.
        $this->assertSame('2026-02-28', $next->format('Y-m-d'));
    }

    public function test_personalizada_avanca_a_quantidade_de_dias_informada(): void
    {
        $next = RecurrenceFrequency::Custom->nextOccurrence(CarbonImmutable::parse('2026-07-01 10:00:00'), 10);

        $this->assertSame('2026-07-11 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_personalizada_sem_dias_informados_avanca_um_dia(): void
    {
        $next = RecurrenceFrequency::Custom->nextOccurrence(CarbonImmutable::parse('2026-07-01 10:00:00'));

        $this->assertSame('2026-07-02', $next->format('Y-m-d'));
    }
}
