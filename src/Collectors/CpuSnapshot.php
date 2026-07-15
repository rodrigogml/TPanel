<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class CpuSnapshot
{
    /**
     * @param array<string, float> $perCoreUsagePercent
     */
    public function __construct(
        public readonly ?float $totalUsagePercent,
        public readonly array $perCoreUsagePercent,
        public readonly ?float $frequencyMhz,
        public readonly ?float $temperatureCelsius,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
