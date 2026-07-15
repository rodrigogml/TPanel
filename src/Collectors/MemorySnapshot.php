<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class MemorySnapshot
{
    public function __construct(
        public readonly int $ramTotalBytes,
        public readonly int $ramAvailableBytes,
        public readonly int $ramUsedBytes,
        public readonly int $swapTotalBytes,
        public readonly int $swapFreeBytes,
        public readonly int $swapUsedBytes,
        public readonly int $buffersBytes,
        public readonly int $cachedBytes,
        public readonly DateTimeImmutable $collectedAt,
    ) {
    }

    public function ramUsedPercent(): ?float
    {
        if ($this->ramTotalBytes <= 0) {
            return null;
        }

        return round(($this->ramUsedBytes / $this->ramTotalBytes) * 100, 2);
    }

    public function swapUsedPercent(): ?float
    {
        if ($this->swapTotalBytes <= 0) {
            return null;
        }

        return round(($this->swapUsedBytes / $this->swapTotalBytes) * 100, 2);
    }
}
