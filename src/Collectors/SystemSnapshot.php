<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class SystemSnapshot
{
    /**
     * @param array{oneMinute: float|null, fiveMinutes: float|null, fifteenMinutes: float|null} $loadAverage
     */
    public function __construct(
        public readonly string $hostname,
        public readonly ?string $debianVersion,
        public readonly ?string $kernelRelease,
        public readonly ?int $uptimeSeconds,
        public readonly DateTimeImmutable $collectedAt,
        public readonly array $loadAverage,
    ) {
    }
}
