<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class RaidSnapshot
{
    /**
     * @param list<array{name: string, state: string, syncPercent: float|null, degradedDisks: int}> $arrays
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $arrays,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
