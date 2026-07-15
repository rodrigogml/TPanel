<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class CpuCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): CpuSnapshot
    {
        $stat = $this->parseProcStat($this->dataSource->readFile('/proc/stat') ?? '');

        return new CpuSnapshot(
            totalUsagePercent: $stat['cpu'] ?? null,
            perCoreUsagePercent: array_filter(
                $stat,
                static fn (string $key): bool => $key !== 'cpu',
                ARRAY_FILTER_USE_KEY
            ),
            frequencyMhz: $this->parseFrequencyMhz($this->dataSource->readFile('/proc/cpuinfo') ?? ''),
            temperatureCelsius: $this->readTemperatureCelsius(),
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, float>
     */
    private function parseProcStat(string $content): array
    {
        $usage = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            $label = $parts[0] ?? '';

            if (!preg_match('/^cpu[0-9]*$/', $label)) {
                continue;
            }

            $values = array_map('intval', array_slice($parts, 1));

            if (count($values) < 4) {
                continue;
            }

            $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
            $total = array_sum($values);

            if ($total <= 0) {
                continue;
            }

            $usage[$label] = round((($total - $idle) / $total) * 100, 2);
        }

        return $usage;
    }

    private function parseFrequencyMhz(string $content): ?float
    {
        $frequencies = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (!str_starts_with(trim($line), 'cpu MHz')) {
                continue;
            }

            [, $value] = array_pad(explode(':', $line, 2), 2, null);

            if ($value !== null && is_numeric(trim($value))) {
                $frequencies[] = (float) trim($value);
            }
        }

        if ($frequencies === []) {
            return null;
        }

        return round(array_sum($frequencies) / count($frequencies), 2);
    }

    private function readTemperatureCelsius(): ?float
    {
        $thermalOutput = $this->dataSource->runCommand('/bin/sh', '-c', 'cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null');

        if ($thermalOutput === null) {
            return null;
        }

        $temperatures = [];

        foreach (preg_split('/\R/', trim($thermalOutput)) ?: [] as $line) {
            if (!is_numeric(trim($line))) {
                continue;
            }

            $raw = (float) trim($line);
            $temperatures[] = $raw > 1000 ? $raw / 1000 : $raw;
        }

        if ($temperatures === []) {
            return null;
        }

        return round(max($temperatures), 2);
    }
}
