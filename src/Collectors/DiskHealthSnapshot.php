<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class DiskHealthSnapshot
{
    /**
     * @param list<array{
     *     device: string,
     *     smartAvailable: bool,
     *     healthStatus: string|null,
     *     temperatureCelsius: int|null,
     *     powerOnHours: int|null,
     *     reallocatedSectors: int|null,
     *     criticalErrors: int|null
     * }> $disks
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $disks,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
