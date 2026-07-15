<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class SensorSnapshot
{
    /**
     * @param list<array{label: string, value: float, unit: string}> $readings
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $readings,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
