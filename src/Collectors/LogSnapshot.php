<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class LogSnapshot
{
    /**
     * @param list<string> $journalErrors
     * @param list<string> $syslogErrors
     */
    public function __construct(
        public readonly bool $available,
        public readonly array $journalErrors,
        public readonly array $syslogErrors,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }
}
