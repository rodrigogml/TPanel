<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;
use TPanel\Monitoring\MetricReadingDraft;
use TPanel\Monitoring\SeverityThresholdPolicy;

final class MonitoringCollectorService
{
    public function __construct(
        private readonly SystemCollector $systemCollector = new SystemCollector(),
        private readonly CpuCollector $cpuCollector = new CpuCollector(),
        private readonly MemoryCollector $memoryCollector = new MemoryCollector(),
        private readonly StorageCollector $storageCollector = new StorageCollector(),
        private readonly DiskHealthCollector $diskHealthCollector = new DiskHealthCollector(),
        private readonly RaidCollector $raidCollector = new RaidCollector(),
        private readonly SensorCollector $sensorCollector = new SensorCollector(),
        private readonly NetworkCollector $networkCollector = new NetworkCollector(),
        private readonly ProcessCollector $processCollector = new ProcessCollector(),
        private readonly LogCollector $logCollector = new LogCollector(),
        private readonly SecurityCollector $securityCollector = new SecurityCollector(),
        private readonly ServiceInventoryCollector $serviceInventoryCollector = new ServiceInventoryCollector(),
        private readonly DockerInventoryCollector $dockerInventoryCollector = new DockerInventoryCollector(),
        private readonly ScheduleCollector $scheduleCollector = new ScheduleCollector(),
        private readonly SeverityThresholdPolicy $severityPolicy = new SeverityThresholdPolicy(),
    ) {
    }

    /**
     * @return list<MetricReadingDraft>
     */
    public function collectSystemCpuMemory(?DateTimeImmutable $collectedAt = null): array
    {
        $collectedAt ??= new DateTimeImmutable();
        $system = $this->systemCollector->collect($collectedAt);
        $cpu = $this->cpuCollector->collect($collectedAt);
        $memory = $this->memoryCollector->collect($collectedAt);

        return [
            new MetricReadingDraft(null, 'SYSTEM', 'identity', [
                'hostname' => $system->hostname,
                'debianVersion' => $system->debianVersion,
                'kernelRelease' => $system->kernelRelease,
                'uptimeSeconds' => $system->uptimeSeconds,
                'loadAverage' => $system->loadAverage,
                'dateTime' => $system->collectedAt->format('c'),
            ], null, 'NORMAL', 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'CPU', 'usage', [
                'totalPercent' => $cpu->totalUsagePercent,
                'perCorePercent' => $cpu->perCoreUsagePercent,
                'frequencyMhz' => $cpu->frequencyMhz,
                'temperatureCelsius' => $cpu->temperatureCelsius,
            ], 'percent', $this->severityPolicy->classifyCpu($cpu->totalUsagePercent), 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'MEMORY', 'usage', [
                'ramTotalBytes' => $memory->ramTotalBytes,
                'ramAvailableBytes' => $memory->ramAvailableBytes,
                'ramUsedBytes' => $memory->ramUsedBytes,
                'ramUsedPercent' => $memory->ramUsedPercent(),
                'swapTotalBytes' => $memory->swapTotalBytes,
                'swapFreeBytes' => $memory->swapFreeBytes,
                'swapUsedBytes' => $memory->swapUsedBytes,
                'swapUsedPercent' => $memory->swapUsedPercent(),
                'buffersBytes' => $memory->buffersBytes,
                'cachedBytes' => $memory->cachedBytes,
            ], 'bytes', $memory->ramTotalBytes <= 0 ? 'UNAVAILABLE' : $this->severityPolicy->classifyMemory($memory->ramUsedPercent()), 'system.collector', $collectedAt),
        ];
    }

    /**
     * @return list<MetricReadingDraft>
     */
    public function collectStorageDiskRaidSensors(?DateTimeImmutable $collectedAt = null): array
    {
        $collectedAt ??= new DateTimeImmutable();
        $storage = $this->storageCollector->collect($collectedAt);
        $diskHealth = $this->diskHealthCollector->collect($collectedAt);
        $raid = $this->raidCollector->collect($collectedAt);
        $sensors = $this->sensorCollector->collect($collectedAt);

        return [
            new MetricReadingDraft(null, 'STORAGE', 'filesystems', [
                'available' => $storage->available,
                'filesystems' => $storage->filesystems,
                'diskIo' => $storage->diskIo,
            ], null, $this->severityPolicy->classifyStorage($storage->available, $storage->filesystems), 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'DISK_HEALTH', 'smart', [
                'available' => $diskHealth->available,
                'disks' => $diskHealth->disks,
            ], null, $this->severityPolicy->classifyDiskHealth($diskHealth->available, $diskHealth->disks), 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'RAID', 'arrays', [
                'available' => $raid->available,
                'arrays' => $raid->arrays,
            ], null, $this->severityPolicy->classifyRaid($raid->available, $raid->arrays), 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'SENSOR', 'readings', [
                'available' => $sensors->available,
                'readings' => $sensors->readings,
            ], null, $sensors->available ? 'NORMAL' : 'UNAVAILABLE', 'system.collector', $collectedAt),
        ];
    }

    /**
     * @return list<MetricReadingDraft>
     */
    public function collectNetworkProcessesLogsSecurity(?DateTimeImmutable $collectedAt = null): array
    {
        $collectedAt ??= new DateTimeImmutable();
        $network = $this->networkCollector->collect($collectedAt);
        $processes = $this->processCollector->collect($collectedAt);
        $logs = $this->logCollector->collect($collectedAt);
        $security = $this->securityCollector->collect($collectedAt);

        return [
            new MetricReadingDraft(null, 'NETWORK', 'interfaces', [
                'available' => $network->available,
                'interfaces' => $network->interfaces,
                'gateway' => $network->gateway,
                'dnsServers' => $network->dnsServers,
                'latencyMs' => $network->latencyMs,
            ], null, $this->severityPolicy->classifyNetwork($network->available, $network->interfaces, $network->latencyMs), 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'PROCESS', 'top', [
                'available' => $processes->available,
                'topByCpu' => $processes->topByCpu,
                'topByMemory' => $processes->topByMemory,
            ], null, $processes->available ? 'NORMAL' : 'UNAVAILABLE', 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'LOG', 'recent-errors', [
                'available' => $logs->available,
                'journalErrors' => $logs->journalErrors,
                'syslogErrors' => $logs->syslogErrors,
            ], null, $logs->available ? 'NORMAL' : 'UNAVAILABLE', 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'SECURITY', 'ssh-firewall-updates', [
                'available' => $security->available,
                'recentSshLogins' => $security->recentSshLogins,
                'recentSshFailures' => $security->recentSshFailures,
                'firewallState' => $security->firewallState,
                'availableUpdates' => $security->availableUpdates,
            ], null, $security->available ? 'NORMAL' : 'UNAVAILABLE', 'system.collector', $collectedAt),
        ];
    }

    /**
     * @return list<MetricReadingDraft>
     */
    public function collectServicesAndContainers(?DateTimeImmutable $collectedAt = null): array
    {
        $collectedAt ??= new DateTimeImmutable();
        $services = $this->serviceInventoryCollector->collect($collectedAt);
        $docker = $this->dockerInventoryCollector->collect($collectedAt);

        return [
            new MetricReadingDraft(null, 'SERVICE', 'systemd-services', [
                'available' => $services->available,
                'services' => $services->services,
            ], null, $this->severityPolicy->classifyServices($services->available, $services->services), 'system.collector', $collectedAt),
            new MetricReadingDraft(null, 'SERVICE', 'docker-containers', [
                'available' => $docker->available,
                'dockerAvailable' => $docker->dockerAvailable,
                'containers' => $docker->containers,
            ], null, $this->severityPolicy->classifyDocker($docker->available, $docker->containers), 'system.collector', $collectedAt),
        ];
    }

    /**
     * @return list<MetricReadingDraft>
     */
    public function collectSchedules(?DateTimeImmutable $collectedAt = null): array
    {
        $collectedAt ??= new DateTimeImmutable();
        $schedule = $this->scheduleCollector->collect($collectedAt);

        return [
            new MetricReadingDraft(null, 'SCHEDULE', 'cron-and-timers', [
                'available' => $schedule->available,
                'cronAvailable' => $schedule->cronAvailable,
                'timerAvailable' => $schedule->timerAvailable,
                'cronJobs' => $schedule->cronJobs,
                'timers' => $schedule->timers,
            ], null, $schedule->available ? 'NORMAL' : 'UNAVAILABLE', 'system.collector', $collectedAt),
        ];
    }
}
