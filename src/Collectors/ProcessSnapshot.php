<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class ProcessSnapshot
{
    /**
     * @param list<array{pid: int, command: string, cpuPercent: float, memoryPercent: float}> $topByCpu
     * @param list<array{pid: int, command: string, cpuPercent: float, memoryPercent: float}> $topByMemory
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $topByCpu,
        public readonly array $topByMemory,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
