<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class ScheduleSnapshot
{
    /**
     * @param list<array{source: string, schedule: string, user: string|null, command: string}> $cronJobs
     * @param list<array{unit: string, activates: string|null, next: string|null, last: string|null, state: string|null}> $timers
     */
    public function __construct(
        public readonly bool $available,
        public readonly bool $cronAvailable,
        public readonly bool $timerAvailable,
        public readonly array $cronJobs,
        public readonly array $timers,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
