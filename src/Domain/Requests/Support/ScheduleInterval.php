<?php

namespace Domain\Requests\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ScheduleInterval
{
    public static function overlaps(
        DateTimeInterface|string $firstStart,
        DateTimeInterface|string $firstEnd,
        DateTimeInterface|string $secondStart,
        DateTimeInterface|string $secondEnd
    ): bool {
        $firstStart = CarbonImmutable::parse($firstStart);
        $firstEnd = CarbonImmutable::parse($firstEnd);
        $secondStart = CarbonImmutable::parse($secondStart);
        $secondEnd = CarbonImmutable::parse($secondEnd);

        return $firstStart->lt($secondEnd) && $firstEnd->gt($secondStart);
    }
}
