<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class SecuritySnapshot
{
    /**
     * @param list<string> $recentSshLogins
     * @param list<string> $recentSshFailures
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $recentSshLogins,
        public readonly array $recentSshFailures,
        public readonly ?string $firewallState,
        public readonly ?int $availableUpdates,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
