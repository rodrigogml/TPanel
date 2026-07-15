<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class DiskHealthCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): DiskHealthSnapshot
    {
        $devices = $this->listDiskDevices();
        $disks = [];

        foreach ($devices as $device) {
            $smartOutput = $this->dataSource->runCommand('/usr/sbin/smartctl', '-A', '-H', '/dev/' . $device);
            $disks[] = $this->parseSmartctl('/dev/' . $device, $smartOutput);
        }

        return new DiskHealthSnapshot(
            available: $devices !== [],
            disks: $disks,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<string>
     */
    private function listDiskDevices(): array
    {
        $output = $this->dataSource->runCommand('/usr/bin/lsblk', '-dn', '-o', 'NAME,TYPE');

        if ($output === null) {
            return [];
        }

        $devices = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (($parts[1] ?? null) === 'disk' && isset($parts[0])) {
                $devices[] = $parts[0];
            }
        }

        return $devices;
    }

    /**
     * @return array{device: string, smartAvailable: bool, healthStatus: string|null, temperatureCelsius: int|null, powerOnHours: int|null, reallocatedSectors: int|null, criticalErrors: int|null}
     */
    private function parseSmartctl(string $device, ?string $output): array
    {
        if ($output === null || trim($output) === '') {
            return [
                'device' => $device,
                'smartAvailable' => false,
                'healthStatus' => null,
                'temperatureCelsius' => null,
                'powerOnHours' => null,
                'reallocatedSectors' => null,
                'criticalErrors' => null,
            ];
        }

        return [
            'device' => $device,
            'smartAvailable' => true,
            'healthStatus' => $this->matchString('/SMART overall-health self-assessment test result:\s*([A-Z]+)/i', $output),
            'temperatureCelsius' => $this->smartAttributeRawValue($output, [
                'Temperature_Celsius',
                'Airflow_Temperature_Cel',
                'Temperature_Internal',
            ]),
            'powerOnHours' => $this->smartAttributeRawValue($output, ['Power_On_Hours']),
            'reallocatedSectors' => $this->smartAttributeRawValue($output, ['Reallocated_Sector_Ct']),
            'criticalErrors' => $this->smartAttributeRawValue($output, [
                'Reported_Uncorrect',
                'UDMA_CRC_Error_Count',
                'Offline_Uncorrectable',
            ]),
        ];
    }

    private function matchString(string $pattern, string $content): ?string
    {
        return preg_match($pattern, $content, $matches) === 1 ? strtoupper($matches[1]) : null;
    }

    /**
     * @param list<string> $attributeNames
     */
    private function smartAttributeRawValue(string $content, array $attributeNames): ?int
    {
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            foreach ($attributeNames as $attributeName) {
                if (!str_contains($line, $attributeName)) {
                    continue;
                }

                $parts = preg_split('/\s+/', trim($line)) ?: [];
                $rawValue = end($parts);

                return is_numeric($rawValue) ? (int) $rawValue : null;
            }
        }

        return null;
    }
}
