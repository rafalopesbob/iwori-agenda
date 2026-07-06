<?php

namespace Tests\Unit;

use App\Services\CalendarService;
use PHPUnit\Framework\TestCase;

class CalendarServiceTest extends TestCase
{
    public function test_grade_de_julho_de_2026(): void
    {
        $grid = (new CalendarService)->monthGrid('2026-07');

        $this->assertSame('2026-07-01', $grid['month']->toDateString());
        // Julho/2026 começa numa quarta: a grade abre no domingo 28/06.
        $this->assertSame('2026-06-28', $grid['start']->toDateString());
        // E fecha no sábado 01/08.
        $this->assertSame('2026-08-01', $grid['end']->toDateString());

        $this->assertCount(5, $grid['weeks']);
        foreach ($grid['weeks'] as $week) {
            $this->assertCount(7, $week);
        }
    }

    public function test_mes_invalido_usa_o_mes_atual(): void
    {
        $grid = (new CalendarService)->monthGrid('9999-99');

        $this->assertTrue($grid['month']->isCurrentMonth());
        $this->assertSame(1, $grid['month']->day);
    }

    public function test_semanas_vao_de_domingo_a_sabado(): void
    {
        $grid = (new CalendarService)->monthGrid('2026-02');

        foreach ($grid['weeks'] as $week) {
            $this->assertSame(0, $week[0]->dayOfWeek, 'Semana deve começar no domingo');
            $this->assertSame(6, $week[6]->dayOfWeek, 'Semana deve terminar no sábado');
        }
    }
}
