<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class MemoryCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): MemorySnapshot
    {
        $values = $this->parseMeminfo($this->dataSource->readFile('/proc/meminfo') ?? '');
        $ramTotal = $values['MemTotal'] ?? 0;
        $ramAvailable = $values['MemAvailable'] ?? (($values['MemFree'] ?? 0) + ($values['Buffers'] ?? 0) + ($values['Cached'] ?? 0));
        $swapTotal = $values['SwapTotal'] ?? 0;
        $swapFree = $values['SwapFree'] ?? 0;

        return new MemorySnapshot(
            ramTotalBytes: $ramTotal,
            ramAvailableBytes: $ramAvailable,
            ramUsedBytes: max(0, $ramTotal - $ramAvailable),
            swapTotalBytes: $swapTotal,
            swapFreeBytes: $swapFree,
            swapUsedBytes: max(0, $swapTotal - $swapFree),
            buffersBytes: $values['Buffers'] ?? 0,
            cachedBytes: ($values['Cached'] ?? 0) + ($values['SReclaimable'] ?? 0),
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, int>
     */
    private function parseMeminfo(string $content): array
    {
        $values = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (!preg_match('/^([A-Za-z_()]+):\s+([0-9]+)\s+kB$/', trim($line), $matches)) {
                continue;
            }

            $values[$matches[1]] = (int) $matches[2] * 1024;
        }

        return $values;
    }
}
