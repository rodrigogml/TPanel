<?php

declare(strict_types=1);

namespace TPanel\Services;

use TPanel\Collectors\LocalSystemDataSource;
use TPanel\Collectors\ProcessCollector;
use TPanel\Collectors\SystemDataSource;

final class MemoryLiveService
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $deltaMicroseconds = 180000): array
    {
        $firstVmstat = $this->readVmstat();

        if ($deltaMicroseconds > 0) {
            usleep($deltaMicroseconds);
        }

        $secondVmstat = $this->readVmstat();
        $meminfo = $this->readMeminfo();
        $total = $meminfo['MemTotal'] ?? 0;
        $available = $meminfo['MemAvailable'] ?? (($meminfo['MemFree'] ?? 0) + ($meminfo['Buffers'] ?? 0) + ($meminfo['Cached'] ?? 0));
        $used = max(0, $total - $available);
        $swapTotal = $meminfo['SwapTotal'] ?? 0;
        $swapFree = $meminfo['SwapFree'] ?? 0;
        $swapUsed = max(0, $swapTotal - $swapFree);
        $pressure = $this->readPressure();
        $paging = $this->calculatePaging($firstVmstat, $secondVmstat, max(0.001, $deltaMicroseconds / 1_000_000));
        $topProcesses = (new ProcessCollector($this->dataSource, 8))->collect()->topByMemory;
        $usedPercent = $this->percent($used, $total);
        $availablePercent = $this->percent($available, $total);
        $swapUsedPercent = $this->percent($swapUsed, $swapTotal);
        $statusReasons = $this->statusReasons($usedPercent, $availablePercent, $swapUsedPercent, $pressure, $paging);
        $swapActivity = round($paging['swapInPagesPerSecond'] + $paging['swapOutPagesPerSecond'], 2);

        return [
            'collectedAt' => gmdate('c'),
            'status' => $this->statusFor($statusReasons),
            'statusReasons' => $statusReasons,
            'ram' => [
                'totalBytes' => $total,
                'usedBytes' => $used,
                'availableBytes' => $available,
                'freeBytes' => $meminfo['MemFree'] ?? 0,
                'usedPercent' => $usedPercent,
                'availablePercent' => $availablePercent,
                'buffersBytes' => $meminfo['Buffers'] ?? 0,
                'cachedBytes' => ($meminfo['Cached'] ?? 0) + ($meminfo['SReclaimable'] ?? 0),
                'activeBytes' => $meminfo['Active'] ?? 0,
                'inactiveBytes' => $meminfo['Inactive'] ?? 0,
                'anonBytes' => $meminfo['AnonPages'] ?? 0,
                'mappedBytes' => $meminfo['Mapped'] ?? 0,
                'shmemBytes' => $meminfo['Shmem'] ?? 0,
            ],
            'kernel' => [
                'slabBytes' => $meminfo['Slab'] ?? 0,
                'sReclaimableBytes' => $meminfo['SReclaimable'] ?? 0,
                'sUnreclaimBytes' => $meminfo['SUnreclaim'] ?? 0,
                'pageTablesBytes' => $meminfo['PageTables'] ?? 0,
                'kernelStackBytes' => $meminfo['KernelStack'] ?? 0,
                'dirtyBytes' => $meminfo['Dirty'] ?? 0,
                'writebackBytes' => $meminfo['Writeback'] ?? 0,
            ],
            'swap' => [
                'totalBytes' => $swapTotal,
                'usedBytes' => $swapUsed,
                'freeBytes' => $swapFree,
                'cachedBytes' => $meminfo['SwapCached'] ?? 0,
                'usedPercent' => $swapUsedPercent,
                'activityPagesPerSecond' => $swapActivity,
                'activePressure' => $this->hasActiveSwapPressure($swapUsedPercent, $pressure, $paging),
            ],
            'paging' => $paging,
            'pressure' => $pressure,
            'inventory' => $this->readInventory(),
            'topProcesses' => $topProcesses,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function readMeminfo(): array
    {
        $values = [];

        foreach (preg_split('/\R/', $this->dataSource->readFile('/proc/meminfo') ?? '') ?: [] as $line) {
            if (preg_match('/^([A-Za-z_()]+):\s+([0-9]+)\s+kB$/', trim($line), $matches)) {
                $values[$matches[1]] = (int) $matches[2] * 1024;
            }
        }

        return $values;
    }

    /**
     * @return array<string, int>
     */
    private function readVmstat(): array
    {
        $values = [];

        foreach (preg_split('/\R/', $this->dataSource->readFile('/proc/vmstat') ?? '') ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (count($parts) === 2 && is_numeric($parts[1])) {
                $values[$parts[0]] = (int) $parts[1];
            }
        }

        return $values;
    }

    /**
     * @param array<string, int> $first
     * @param array<string, int> $second
     * @return array{pageInKbPerSecond: float, pageOutKbPerSecond: float, swapInPagesPerSecond: float, swapOutPagesPerSecond: float}
     */
    private function calculatePaging(array $first, array $second, float $seconds): array
    {
        return [
            'pageInKbPerSecond' => $this->rate($first, $second, 'pgpgin', $seconds),
            'pageOutKbPerSecond' => $this->rate($first, $second, 'pgpgout', $seconds),
            'swapInPagesPerSecond' => $this->rate($first, $second, 'pswpin', $seconds),
            'swapOutPagesPerSecond' => $this->rate($first, $second, 'pswpout', $seconds),
        ];
    }

    /**
     * @return array{available: bool, some: array{avg10: float, avg60: float, avg300: float}, full: array{avg10: float, avg60: float, avg300: float}}
     */
    private function readPressure(): array
    {
        $content = $this->dataSource->readFile('/proc/pressure/memory') ?? '';
        $empty = ['avg10' => 0.0, 'avg60' => 0.0, 'avg300' => 0.0];
        $pressure = ['available' => false, 'some' => $empty, 'full' => $empty];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            $type = $parts[0] ?? '';

            if (!in_array($type, ['some', 'full'], true)) {
                continue;
            }

            $pressure['available'] = true;

            foreach (array_slice($parts, 1) as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                if (in_array($key, ['avg10', 'avg60', 'avg300'], true) && is_numeric($value)) {
                    $pressure[$type][$key] = round((float) $value, 2);
                }
            }
        }

        return $pressure;
    }

    /**
     * @param array<string, int> $first
     * @param array<string, int> $second
     */
    private function rate(array $first, array $second, string $key, float $seconds): float
    {
        return round(max(0, ($second[$key] ?? 0) - ($first[$key] ?? 0)) / $seconds, 2);
    }

    /**
     * @return array{available: bool, maximumCapacity: string|null, deviceCount: int|null, occupiedSlots: int, emptySlots: int|null, errorCorrectionType: string|null, devices: list<array<string, string>>}
     */
    private function readInventory(): array
    {
        $output = $this->dataSource->runCommand('/usr/bin/sudo', '-n', '/usr/sbin/dmidecode', '-t', 'memory')
            ?? $this->dataSource->runCommand('/usr/sbin/dmidecode', '-t', 'memory')
            ?? '';

        $inventory = [
            'available' => trim($output) !== '',
            'maximumCapacity' => null,
            'deviceCount' => null,
            'occupiedSlots' => 0,
            'emptySlots' => null,
            'errorCorrectionType' => null,
            'devices' => [],
        ];

        if (!$inventory['available']) {
            return $inventory;
        }

        $sections = preg_split('/\n(?=Handle\s)/', trim($output)) ?: [];

        foreach ($sections as $section) {
            if (str_contains($section, 'Physical Memory Array')) {
                $inventory['maximumCapacity'] = $this->fieldFromDmiSection($section, 'Maximum Capacity');
                $inventory['deviceCount'] = $this->intFieldFromDmiSection($section, 'Number Of Devices');
                $inventory['errorCorrectionType'] = $this->fieldFromDmiSection($section, 'Error Correction Type');
                continue;
            }

            if (!str_contains($section, 'Memory Device')) {
                continue;
            }

            $size = $this->fieldFromDmiSection($section, 'Size') ?? 'Unknown';

            if (str_starts_with($size, 'No Module Installed')) {
                continue;
            }

            $inventory['devices'][] = [
                'locator' => $this->fieldFromDmiSection($section, 'Locator') ?? 'n/a',
                'bankLocator' => $this->fieldFromDmiSection($section, 'Bank Locator') ?? 'n/a',
                'size' => $size,
                'formFactor' => $this->fieldFromDmiSection($section, 'Form Factor') ?? 'n/a',
                'type' => $this->fieldFromDmiSection($section, 'Type') ?? 'n/a',
                'speed' => $this->fieldFromDmiSection($section, 'Speed') ?? 'n/a',
                'configuredSpeed' => $this->fieldFromDmiSection($section, 'Configured Memory Speed') ?? 'n/a',
                'rank' => $this->fieldFromDmiSection($section, 'Rank') ?? 'n/a',
                'configuredVoltage' => $this->fieldFromDmiSection($section, 'Configured Voltage') ?? 'n/a',
                'manufacturer' => $this->fieldFromDmiSection($section, 'Manufacturer') ?? 'n/a',
                'partNumber' => $this->fieldFromDmiSection($section, 'Part Number') ?? 'n/a',
                'serialNumber' => $this->maskSerial($this->fieldFromDmiSection($section, 'Serial Number') ?? ''),
            ];
        }

        $inventory['occupiedSlots'] = count($inventory['devices']);
        $inventory['emptySlots'] = $inventory['deviceCount'] === null
            ? null
            : max(0, $inventory['deviceCount'] - $inventory['occupiedSlots']);

        return $inventory;
    }

    private function fieldFromDmiSection(string $section, string $field): ?string
    {
        if (!preg_match('/^\s*' . preg_quote($field, '/') . ':\s*(.+)$/m', $section, $matches)) {
            return null;
        }

        $value = trim($matches[1]);

        return $value === '' || $value === 'Not Specified' ? null : $value;
    }

    private function intFieldFromDmiSection(string $section, string $field): ?int
    {
        $value = $this->fieldFromDmiSection($section, $field);

        if ($value === null || !preg_match('/\d+/', $value, $matches)) {
            return null;
        }

        return (int) $matches[0];
    }

    private function maskSerial(string $serial): string
    {
        $trimmed = trim($serial);

        if ($trimmed === '' || strlen($trimmed) <= 4) {
            return 'n/a';
        }

        return str_repeat('*', max(4, strlen($trimmed) - 4)) . substr($trimmed, -4);
    }

    /**
     * @param array<string, mixed> $pressure
     * @return list<array{severity: string, label: string, value: string}>
     */
    private function statusReasons(float $usedPercent, float $availablePercent, float $swapUsedPercent, array $pressure, array $paging): array
    {
        $somePressure = (float) ($pressure['some']['avg10'] ?? 0.0);
        $fullPressure = (float) ($pressure['full']['avg10'] ?? 0.0);
        $reasons = [];

        if ($usedPercent >= 92.0) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'RAM usada >= 92%', 'value' => sprintf('%.1f%%', $usedPercent)];
        } elseif ($usedPercent >= 80.0) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'RAM usada >= 80%', 'value' => sprintf('%.1f%%', $usedPercent)];
        }

        if ($availablePercent <= 5.0) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'RAM disponível <= 5%', 'value' => sprintf('%.1f%%', $availablePercent)];
        } elseif ($availablePercent <= 10.0) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'RAM disponível <= 10%', 'value' => sprintf('%.1f%%', $availablePercent)];
        }

        $swapActivity = (float) ($paging['swapInPagesPerSecond'] ?? 0.0) + (float) ($paging['swapOutPagesPerSecond'] ?? 0.0);

        if ($swapUsedPercent >= 60.0 && ($swapActivity > 0.0 || $fullPressure >= 1.0 || $somePressure >= 5.0)) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'Swap sob pressão ativa', 'value' => sprintf('%.1f%% usado, %.2f páginas/s', $swapUsedPercent, $swapActivity)];
        } elseif ($swapUsedPercent >= 20.0 && ($swapActivity > 0.0 || $fullPressure >= 1.0 || $somePressure >= 5.0)) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'Swap com atividade recente', 'value' => sprintf('%.1f%% usado, %.2f páginas/s', $swapUsedPercent, $swapActivity)];
        }

        if ($fullPressure >= 5.0) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'Pressão PSI full avg10 >= 5%', 'value' => sprintf('%.2f%%', $fullPressure)];
        } elseif ($somePressure >= 5.0) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'Pressão PSI some avg10 >= 5%', 'value' => sprintf('%.2f%%', $somePressure)];
        }

        return $reasons;
    }

    /**
     * @param array<string, mixed> $pressure
     * @param array{pageInKbPerSecond: float, pageOutKbPerSecond: float, swapInPagesPerSecond: float, swapOutPagesPerSecond: float} $paging
     */
    private function hasActiveSwapPressure(float $swapUsedPercent, array $pressure, array $paging): bool
    {
        if ($swapUsedPercent <= 0.0) {
            return false;
        }

        $swapActivity = (float) $paging['swapInPagesPerSecond'] + (float) $paging['swapOutPagesPerSecond'];
        $somePressure = (float) ($pressure['some']['avg10'] ?? 0.0);
        $fullPressure = (float) ($pressure['full']['avg10'] ?? 0.0);

        return $swapActivity > 0.0 || $fullPressure >= 1.0 || $somePressure >= 5.0;
    }

    /**
     * @param list<array{severity: string, label: string, value: string}> $reasons
     */
    private function statusFor(array $reasons): string
    {
        foreach ($reasons as $reason) {
            if ($reason['severity'] === 'CRITICAL') {
                return 'CRITICAL';
            }
        }

        if ($reasons !== []) {
            return 'WARNING';
        }

        return 'OK';
    }

    private function percent(int|float $value, int|float $total): float
    {
        if ((float) $total <= 0.0) {
            return 0.0;
        }

        return round(((float) $value / (float) $total) * 100, 1);
    }
}
