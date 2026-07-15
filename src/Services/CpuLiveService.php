<?php

declare(strict_types=1);

namespace TPanel\Services;

use TPanel\Collectors\LocalSystemDataSource;
use TPanel\Collectors\ProcessCollector;
use TPanel\Collectors\SystemDataSource;

final class CpuLiveService
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
        $first = $this->readCpuTicks();

        if ($deltaMicroseconds > 0) {
            usleep($deltaMicroseconds);
        }

        $second = $this->readCpuTicks();
        $usage = $this->calculateUsage($first, $second);
        $identity = $this->readIdentity();
        $load = $this->readLoad((int) ($identity['logicalThreads'] ?? max(1, count($usage['cores']))));
        $temperature = $this->readTemperatureCelsius();
        $frequency = $this->readFrequency();
        $processes = (new ProcessCollector($this->dataSource, 8))->collect()->topByCpu;
        $totalUsage = (float) ($usage['total']['usagePercent'] ?? 0.0);

        return [
            'collectedAt' => gmdate('c'),
            'status' => $this->statusFor($totalUsage, $load['normalizedOne']),
            'statusReasons' => $this->statusReasons($totalUsage, $load['normalizedOne']),
            'identity' => $identity,
            'total' => $usage['total'],
            'cores' => $usage['cores'],
            'load' => $load,
            'frequency' => $frequency,
            'temperatureCelsius' => $temperature,
            'governor' => $this->readGovernor(),
            'topProcesses' => $processes,
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function readCpuTicks(): array
    {
        $content = $this->dataSource->readFile('/proc/stat') ?? '';
        $ticks = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            if (!preg_match('/^(cpu\d*)\s+(.+)$/', trim($line), $matches)) {
                continue;
            }

            $fields = array_map('intval', preg_split('/\s+/', trim($matches[2])) ?: []);
            $ticks[$matches[1]] = [
                'user' => $fields[0] ?? 0,
                'nice' => $fields[1] ?? 0,
                'system' => $fields[2] ?? 0,
                'idle' => $fields[3] ?? 0,
                'iowait' => $fields[4] ?? 0,
                'irq' => $fields[5] ?? 0,
                'softirq' => $fields[6] ?? 0,
                'steal' => $fields[7] ?? 0,
            ];
        }

        return $ticks;
    }

    /**
     * @param array<string, array<string, int>> $first
     * @param array<string, array<string, int>> $second
     * @return array{total: array<string, float>, cores: list<array{id: string, label: string, usagePercent: float, systemPercent: float, iowaitPercent: float}>}
     */
    private function calculateUsage(array $first, array $second): array
    {
        $total = $this->usageFor($first['cpu'] ?? [], $second['cpu'] ?? []);
        $cores = [];

        foreach ($second as $id => $ticks) {
            if ($id === 'cpu' || !isset($first[$id])) {
                continue;
            }

            $coreUsage = $this->usageFor($first[$id], $ticks);
            $cores[] = [
                'id' => $id,
                'label' => strtoupper($id),
                'usagePercent' => $coreUsage['usagePercent'],
                'systemPercent' => $coreUsage['systemPercent'],
                'iowaitPercent' => $coreUsage['iowaitPercent'],
            ];
        }

        return [
            'total' => $total,
            'cores' => $cores,
        ];
    }

    /**
     * @param array<string, int> $first
     * @param array<string, int> $second
     * @return array{usagePercent: float, userPercent: float, systemPercent: float, iowaitPercent: float, stealPercent: float, idlePercent: float}
     */
    private function usageFor(array $first, array $second): array
    {
        $delta = [];

        foreach (['user', 'nice', 'system', 'idle', 'iowait', 'irq', 'softirq', 'steal'] as $key) {
            $delta[$key] = max(0, ($second[$key] ?? 0) - ($first[$key] ?? 0));
        }

        $deltaTotal = array_sum($delta);

        if ($deltaTotal <= 0) {
            return [
                'usagePercent' => 0.0,
                'userPercent' => 0.0,
                'systemPercent' => 0.0,
                'iowaitPercent' => 0.0,
                'stealPercent' => 0.0,
                'idlePercent' => 0.0,
            ];
        }

        $total = max(1, $deltaTotal);
        $idle = $delta['idle'] + $delta['iowait'];
        $active = max(0, $total - $idle);

        return [
            'usagePercent' => $this->percent($active, $total),
            'userPercent' => $this->percent($delta['user'] + $delta['nice'], $total),
            'systemPercent' => $this->percent($delta['system'] + $delta['irq'] + $delta['softirq'], $total),
            'iowaitPercent' => $this->percent($delta['iowait'], $total),
            'stealPercent' => $this->percent($delta['steal'], $total),
            'idlePercent' => $this->percent($idle, $total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readIdentity(): array
    {
        $content = $this->dataSource->readFile('/proc/cpuinfo') ?? '';
        $processors = 0;
        $modelName = 'CPU não identificada';
        $vendor = 'n/a';
        $physicalIds = [];
        $coreIds = [];
        $flags = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            [$key, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');

            if ($key === 'processor') {
                $processors++;
                continue;
            }

            if ($key === 'model name' && $value !== '') {
                $modelName = $value;
            } elseif ($key === 'vendor_id' && $value !== '') {
                $vendor = $value;
            } elseif ($key === 'physical id' && $value !== '') {
                $physicalIds[$value] = true;
            } elseif ($key === 'core id' && $value !== '') {
                $coreIds[$value] = true;
            } elseif ($key === 'flags' && $value !== '') {
                $flags = array_values(array_unique(array_merge($flags, preg_split('/\s+/', $value) ?: [])));
            }
        }

        $logicalThreads = max(1, $processors);
        $sockets = max(1, count($physicalIds));
        $physicalCores = count($coreIds) > 0 ? count($coreIds) * $sockets : $logicalThreads;
        $threadsPerCore = max(1, (int) ceil($logicalThreads / max(1, $physicalCores)));
        $selectedFlags = array_values(array_intersect(['aes', 'avx', 'avx2', 'avx512f', 'vmx', 'svm', 'sse4_2'], $flags));

        return [
            'modelName' => $modelName,
            'vendor' => $vendor,
            'logicalThreads' => $logicalThreads,
            'physicalCores' => $physicalCores,
            'sockets' => $sockets,
            'threadsPerCore' => $threadsPerCore,
            'features' => $selectedFlags,
        ];
    }

    /**
     * @return array{one: float, five: float, fifteen: float, normalizedOne: float}
     */
    private function readLoad(int $logicalThreads): array
    {
        $parts = preg_split('/\s+/', trim($this->dataSource->readFile('/proc/loadavg') ?? '')) ?: [];
        $one = (float) ($parts[0] ?? 0);
        $five = (float) ($parts[1] ?? 0);
        $fifteen = (float) ($parts[2] ?? 0);

        return [
            'one' => round($one, 2),
            'five' => round($five, 2),
            'fifteen' => round($fifteen, 2),
            'normalizedOne' => round(($one / max(1, $logicalThreads)) * 100, 1),
        ];
    }

    /**
     * @return array{averageMhz: float|null, minMhz: float|null, maxMhz: float|null}
     */
    private function readFrequency(): array
    {
        $content = $this->dataSource->readFile('/proc/cpuinfo') ?? '';
        $values = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (preg_match('/^cpu MHz\s*:\s*(\d+(?:\.\d+)?)/', trim($line), $matches)) {
                $values[] = (float) $matches[1];
            }
        }

        if ($values === []) {
            return ['averageMhz' => null, 'minMhz' => null, 'maxMhz' => null];
        }

        return [
            'averageMhz' => round(array_sum($values) / count($values), 1),
            'minMhz' => round(min($values), 1),
            'maxMhz' => round(max($values), 1),
        ];
    }

    private function readTemperatureCelsius(): ?float
    {
        $output = $this->dataSource->runCommand('/bin/sh', '-c', 'cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null') ?? '';
        $temperatures = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (is_numeric(trim($line))) {
                $temperatures[] = ((float) trim($line)) / 1000;
            }
        }

        return $temperatures === [] ? null : round(max($temperatures), 1);
    }

    private function readGovernor(): ?string
    {
        $value = trim($this->dataSource->readFile('/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor') ?? '');

        return $value === '' ? null : $value;
    }

    private function statusFor(float $usagePercent, float $normalizedLoad): string
    {
        $reasons = $this->statusReasons($usagePercent, $normalizedLoad);

        foreach ($reasons as $reason) {
            if ($reason['severity'] === 'CRITICAL') {
                return 'CRITICAL';
            }
        }

        return $reasons === [] ? 'OK' : 'WARNING';
    }

    /**
     * @return list<array{severity: string, label: string, value: string}>
     */
    private function statusReasons(float $usagePercent, float $normalizedLoad): array
    {
        $reasons = [];

        if ($usagePercent >= 90.0) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'CPU usada >= 90%', 'value' => sprintf('%.1f%%', $usagePercent)];
        } elseif ($usagePercent >= 75.0) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'CPU usada >= 75%', 'value' => sprintf('%.1f%%', $usagePercent)];
        }

        if ($normalizedLoad >= 120.0) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'Load normalizado >= 120%', 'value' => sprintf('%.1f%%', $normalizedLoad)];
        } elseif ($normalizedLoad >= 85.0) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'Load normalizado >= 85%', 'value' => sprintf('%.1f%%', $normalizedLoad)];
        }

        return $reasons;
    }

    private function percent(int|float $value, int|float $total): float
    {
        return round(((float) $value / max(1.0, (float) $total)) * 100, 1);
    }
}
