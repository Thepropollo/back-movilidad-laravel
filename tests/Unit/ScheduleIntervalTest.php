<?php

namespace Tests\Unit;

use Domain\Requests\Support\ScheduleInterval;
use PHPUnit\Framework\TestCase;

class ScheduleIntervalTest extends TestCase
{
    public function test_overlapping_intervals_are_detected(): void
    {
        $this->assertTrue(ScheduleInterval::overlaps(
            '2026-09-29 08:00:00',
            '2026-09-29 12:00:00',
            '2026-09-29 11:59:00',
            '2026-09-29 14:00:00'
        ));
    }

    public function test_adjacent_intervals_do_not_overlap(): void
    {
        $this->assertFalse(ScheduleInterval::overlaps(
            '2026-09-29 08:00:00',
            '2026-09-29 10:00:00',
            '2026-09-29 10:00:00',
            '2026-09-29 12:00:00'
        ));
    }

    public function test_multiday_intervals_are_compared_as_full_datetimes(): void
    {
        $this->assertTrue(ScheduleInterval::overlaps(
            '2026-09-29 20:00:00',
            '2026-09-30 08:00:00',
            '2026-09-30 07:00:00',
            '2026-09-30 10:00:00'
        ));
    }
}
