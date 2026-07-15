<?php

declare(strict_types=1);

namespace TPanel\Command;

use InvalidArgumentException;

final class CommandIdempotencyPolicy
{
    public function __construct(
        private readonly int $windowSeconds = 900,
    ) {
        if ($windowSeconds <= 0) {
            throw new InvalidArgumentException('Idempotency window must be positive.');
        }
    }

    public function windowSeconds(): int
    {
        return $this->windowSeconds;
    }
}
