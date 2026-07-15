<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class NetworkSnapshot
{
    /**
     * @param list<array{name: string, ips: list<string>, rxBytes: int|null, txBytes: int|null, rxErrors: int|null, txErrors: int|null}> $interfaces
     * @param list<string> $dnsServers
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $interfaces,
        public readonly ?string $gateway,
        public readonly array $dnsServers,
        public readonly ?float $latencyMs,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
