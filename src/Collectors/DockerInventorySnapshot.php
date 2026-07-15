<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class DockerInventorySnapshot
{
    /**
     * @param list<array{name: string, id: string, image: string, status: string, state: string}> $containers
     */
    public function __construct(
        public readonly bool $available,
        public readonly bool $dockerAvailable,
        public readonly array $containers,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
