<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class SensorCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): SensorSnapshot
    {
        $output = $this->dataSource->runCommand('/usr/bin/sensors');
        $readings = $output === null ? [] : $this->parseSensors($output);

        return new SensorSnapshot(
            available: $readings !== [],
            readings: $readings,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<array{label: string, value: float, unit: string}>
     */
    private function parseSensors(string $output): array
    {
        $readings = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^\s*([^:]+):\s*\+?([0-9.]+)\s*(°C|C|RPM|V|W)/u', $line, $matches) !== 1) {
                continue;
            }

            $unit = $matches[3] === 'C' ? '°C' : $matches[3];
            $readings[] = [
                'label' => trim($matches[1]),
                'value' => (float) $matches[2],
                'unit' => $unit,
            ];
        }

        return $readings;
    }
}
