<?php

declare(strict_types=1);

namespace TPanel\Monitoring;

final class SeverityThresholdPolicy
{
    public const NORMAL = 'NORMAL';
    public const WARNING = 'WARNING';
    public const CRITICAL = 'CRITICAL';
    public const UNAVAILABLE = 'UNAVAILABLE';

    /** @var array<string, mixed> */
    private readonly array $thresholds;

    /**
     * @param array<string, mixed> $thresholds
     */
    public function __construct(array $thresholds = [])
    {
        $this->thresholds = array_replace_recursive($this->defaults(), $thresholds);
    }

    public function unavailableUnless(bool $available): ?string
    {
        return $available ? null : self::UNAVAILABLE;
    }

    public function classifyCpu(?float $usagePercent): string
    {
        return $this->classifyPercent(
            $usagePercent,
            (float) $this->threshold('cpu.usagePercentWarning'),
            (float) $this->threshold('cpu.usagePercentCritical')
        );
    }

    public function classifyMemory(?float $ramUsedPercent): string
    {
        return $this->classifyPercent(
            $ramUsedPercent,
            (float) $this->threshold('memory.ramUsedPercentWarning'),
            (float) $this->threshold('memory.ramUsedPercentCritical')
        );
    }

    /**
     * @param list<array{usedPercent?: float|null, inodeUsedPercent?: float|null}> $filesystems
     */
    public function classifyStorage(bool $available, array $filesystems): string
    {
        if (!$available) {
            return self::UNAVAILABLE;
        }

        $severities = [self::NORMAL];

        foreach ($filesystems as $filesystem) {
            $severities[] = $this->classifyNumber(
                $filesystem['usedPercent'] ?? null,
                (float) $this->threshold('storage.filesystemUsedPercentWarning'),
                (float) $this->threshold('storage.filesystemUsedPercentCritical')
            );
            $severities[] = $this->classifyNumber(
                $filesystem['inodeUsedPercent'] ?? null,
                (float) $this->threshold('storage.inodeUsedPercentWarning'),
                (float) $this->threshold('storage.inodeUsedPercentCritical')
            );
        }

        return $this->worst($severities);
    }

    /**
     * @param list<array{temperatureCelsius?: float|null, reallocatedSectors?: int|null, criticalErrors?: int|null}> $disks
     */
    public function classifyDiskHealth(bool $available, array $disks): string
    {
        if (!$available) {
            return self::UNAVAILABLE;
        }

        $severities = [self::NORMAL];

        foreach ($disks as $disk) {
            if (($disk['healthStatus'] ?? 'PASSED') !== 'PASSED') {
                $severities[] = self::CRITICAL;
            }

            $severities[] = $this->classifyNumber(
                isset($disk['temperatureCelsius']) ? (float) $disk['temperatureCelsius'] : null,
                (float) $this->threshold('diskHealth.temperatureCelsiusWarning'),
                (float) $this->threshold('diskHealth.temperatureCelsiusCritical')
            );
            $severities[] = $this->classifyNumber(
                isset($disk['reallocatedSectors']) ? (float) $disk['reallocatedSectors'] : null,
                (float) $this->threshold('diskHealth.reallocatedSectorsWarning'),
                (float) $this->threshold('diskHealth.reallocatedSectorsCritical')
            );

            if (($disk['criticalErrors'] ?? 0) >= (int) $this->threshold('diskHealth.criticalErrorsCritical')) {
                $severities[] = self::CRITICAL;
            }
        }

        return $this->worst($severities);
    }

    /**
     * @param list<array{state: string, degradedDisks: int, syncPercent?: float|null}> $arrays
     */
    public function classifyRaid(bool $available, array $arrays): string
    {
        if (!$available) {
            return self::UNAVAILABLE;
        }

        $severities = [self::NORMAL];

        foreach ($arrays as $array) {
            if (($array['degradedDisks'] ?? 0) > 0 || ($array['state'] ?? '') === 'DEGRADED') {
                $severities[] = self::CRITICAL;
                continue;
            }

            if (($array['state'] ?? '') === 'SYNCING') {
                $severities[] = self::WARNING;
            }
        }

        return $this->worst($severities);
    }

    /**
     * @param list<array{rxErrors?: int, txErrors?: int}> $interfaces
     */
    public function classifyNetwork(bool $available, array $interfaces, ?float $latencyMs): string
    {
        if (!$available) {
            return self::UNAVAILABLE;
        }

        $severities = [
            $this->classifyNumber(
                $latencyMs,
                (float) $this->threshold('network.latencyMsWarning'),
                (float) $this->threshold('network.latencyMsCritical')
            ),
        ];

        foreach ($interfaces as $interface) {
            $errors = max((int) ($interface['rxErrors'] ?? 0), (int) ($interface['txErrors'] ?? 0));
            $severities[] = $this->classifyNumber(
                (float) $errors,
                (float) $this->threshold('network.interfaceErrorsWarning'),
                (float) $this->threshold('network.interfaceErrorsCritical')
            );
        }

        return $this->worst($severities);
    }

    /**
     * @param list<array{activeState?: string, subState?: string}> $services
     */
    public function classifyServices(bool $available, array $services): string
    {
        if (!$available) {
            return self::UNAVAILABLE;
        }

        $severities = [self::NORMAL];
        $failedStates = $this->threshold('service.failedStates');
        $warningStates = $this->threshold('service.warningStates');
        $failedStates = is_array($failedStates) ? $failedStates : [];
        $warningStates = is_array($warningStates) ? $warningStates : [];

        foreach ($services as $service) {
            $activeState = strtoupper((string) ($service['activeState'] ?? ''));
            $subState = strtoupper((string) ($service['subState'] ?? ''));

            if (in_array($activeState, $failedStates, true) || in_array($subState, $failedStates, true)) {
                $severities[] = self::CRITICAL;
                continue;
            }

            if (in_array($activeState, $warningStates, true) || in_array($subState, $warningStates, true)) {
                $severities[] = self::WARNING;
            }
        }

        return $this->worst($severities);
    }

    /**
     * @param list<array{state?: string}> $containers
     */
    public function classifyDocker(bool $available, array $containers): string
    {
        if (!$available) {
            return self::UNAVAILABLE;
        }

        foreach ($containers as $container) {
            if (strtoupper((string) ($container['state'] ?? '')) === 'EXITED') {
                return self::WARNING;
            }
        }

        return self::NORMAL;
    }

    /**
     * @param list<string> $severities
     */
    public function worst(array $severities): string
    {
        $rank = [
            self::NORMAL => 0,
            self::WARNING => 1,
            self::CRITICAL => 2,
            self::UNAVAILABLE => 3,
        ];
        $worst = self::NORMAL;

        foreach ($severities as $severity) {
            if (($rank[$severity] ?? -1) > $rank[$worst]) {
                $worst = $severity;
            }
        }

        return $worst;
    }

    private function classifyPercent(?float $value, float $warning, float $critical): string
    {
        if ($value === null) {
            return self::UNAVAILABLE;
        }

        return $this->classifyNumber($value, $warning, $critical);
    }

    private function classifyNumber(?float $value, float $warning, float $critical): string
    {
        if ($value === null) {
            return self::NORMAL;
        }

        if ($value >= $critical) {
            return self::CRITICAL;
        }

        if ($value >= $warning) {
            return self::WARNING;
        }

        return self::NORMAL;
    }

    private function threshold(string $path): mixed
    {
        $value = $this->thresholds;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'cpu' => [
                'usagePercentWarning' => 75.0,
                'usagePercentCritical' => 90.0,
            ],
            'memory' => [
                'ramUsedPercentWarning' => 80.0,
                'ramUsedPercentCritical' => 90.0,
            ],
            'storage' => [
                'filesystemUsedPercentWarning' => 80.0,
                'filesystemUsedPercentCritical' => 90.0,
                'inodeUsedPercentWarning' => 80.0,
                'inodeUsedPercentCritical' => 90.0,
            ],
            'diskHealth' => [
                'temperatureCelsiusWarning' => 55.0,
                'temperatureCelsiusCritical' => 65.0,
                'reallocatedSectorsWarning' => 1,
                'reallocatedSectorsCritical' => 50,
                'criticalErrorsCritical' => 1,
            ],
            'network' => [
                'latencyMsWarning' => 100.0,
                'latencyMsCritical' => 250.0,
                'interfaceErrorsWarning' => 1,
                'interfaceErrorsCritical' => 100,
            ],
            'service' => [
                'failedStates' => ['FAILED'],
                'warningStates' => ['INACTIVE', 'ACTIVATING', 'DEACTIVATING'],
            ],
        ];
    }
}
