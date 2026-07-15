<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class ServiceInventorySnapshot
{
    /**
     * @param list<array{name: string, loadState: string, activeState: string, subState: string, description: string}> $services
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $services,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
