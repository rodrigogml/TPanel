<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Collectors\CpuCollector;
use TPanel\Collectors\MemoryCollector;
use TPanel\Collectors\MonitoringCollectorService;
use TPanel\Collectors\SystemCollector;

require_once __DIR__ . '/FakeSystemDataSource.php';

final class SystemCollectorsTest extends TestCase
{
    public function testSystemCollectorParsesHostOsKernelUptimeDateTimeAndLoadAverage(): void
    {
        $collector = new SystemCollector(new FakeSystemDataSource(
            files: [
                '/proc/sys/kernel/hostname' => "turin-host\n",
                '/etc/os-release' => "PRETTY_NAME=\"Debian GNU/Linux 13\"\nVERSION_ID=\"13\"\n",
                '/proc/uptime' => "12345.67 89012.34\n",
                '/proc/loadavg' => "0.10 0.20 0.30 1/234 5678\n",
            ],
            commands: [
                '/usr/bin/uname -r' => "6.12.0-amd64\n",
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 11:00:00'));

        self::assertSame('turin-host', $snapshot->hostname);
        self::assertSame('13', $snapshot->debianVersion);
        self::assertSame('6.12.0-amd64', $snapshot->kernelRelease);
        self::assertSame(12345, $snapshot->uptimeSeconds);
        self::assertSame(0.10, $snapshot->loadAverage['oneMinute']);
        self::assertSame('2026-07-15 11:00:00', $snapshot->collectedAt->format('Y-m-d H:i:s'));
    }

    public function testCpuCollectorParsesTotalPerCoreFrequencyAndTemperature(): void
    {
        $collector = new CpuCollector(new FakeSystemDataSource(
            files: [
                '/proc/stat' => implode("\n", [
                    'cpu  100 0 100 800 0 0 0 0 0 0',
                    'cpu0 50 0 50 400 0 0 0 0 0 0',
                    'cpu1 50 0 50 400 0 0 0 0 0 0',
                    '',
                ]),
                '/proc/cpuinfo' => implode("\n", [
                    'processor   : 0',
                    'cpu MHz     : 2400.000',
                    'processor   : 1',
                    'cpu MHz     : 2600.000',
                    '',
                ]),
            ],
            commands: [
                '/bin/sh -c cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null' => "42000\n39000\n",
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 11:01:00'));

        self::assertSame(20.0, $snapshot->totalUsagePercent);
        self::assertSame(['cpu0' => 20.0, 'cpu1' => 20.0], $snapshot->perCoreUsagePercent);
        self::assertSame(2500.0, $snapshot->frequencyMhz);
        self::assertSame(42.0, $snapshot->temperatureCelsius);
    }

    public function testMemoryCollectorParsesRamSwapCacheAndBuffers(): void
    {
        $collector = new MemoryCollector(new FakeSystemDataSource(files: [
            '/proc/meminfo' => implode("\n", [
                'MemTotal:       8000000 kB',
                'MemFree:        1000000 kB',
                'MemAvailable:   5000000 kB',
                'Buffers:         200000 kB',
                'Cached:         1500000 kB',
                'SReclaimable:    300000 kB',
                'SwapTotal:      2000000 kB',
                'SwapFree:       1500000 kB',
                '',
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 11:02:00'));

        self::assertSame(8192000000, $snapshot->ramTotalBytes);
        self::assertSame(5120000000, $snapshot->ramAvailableBytes);
        self::assertSame(3072000000, $snapshot->ramUsedBytes);
        self::assertSame(37.5, $snapshot->ramUsedPercent());
        self::assertSame(2048000000, $snapshot->swapTotalBytes);
        self::assertSame(512000000, $snapshot->swapUsedBytes);
        self::assertSame(25.0, $snapshot->swapUsedPercent());
        self::assertSame(204800000, $snapshot->buffersBytes);
        self::assertSame(1843200000, $snapshot->cachedBytes);
    }

    public function testMonitoringCollectorServiceBuildsMetricReadingDrafts(): void
    {
        $dataSource = new FakeSystemDataSource(
            files: [
                '/proc/sys/kernel/hostname' => "turin-host\n",
                '/etc/os-release' => "VERSION_ID=\"13\"\n",
                '/proc/uptime' => "12345.00 89012.00\n",
                '/proc/loadavg' => "0.10 0.20 0.30 1/234 5678\n",
                '/proc/stat' => "cpu  100 0 100 800 0 0 0 0 0 0\n",
                '/proc/cpuinfo' => "cpu MHz     : 2400.000\n",
                '/proc/meminfo' => "MemTotal: 1000 kB\nMemAvailable: 600 kB\nSwapTotal: 0 kB\nSwapFree: 0 kB\n",
            ],
            commands: [
                '/usr/bin/uname -r' => "6.12.0-amd64\n",
                '/bin/sh -c cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null' => '',
            ]
        );
        $service = new MonitoringCollectorService(
            new SystemCollector($dataSource),
            new CpuCollector($dataSource),
            new MemoryCollector($dataSource)
        );

        $drafts = $service->collectSystemCpuMemory(new DateTimeImmutable('2026-07-15 11:03:00'));

        self::assertCount(3, $drafts);
        self::assertSame('SYSTEM', $drafts[0]->metricCategory);
        self::assertSame('identity', $drafts[0]->metricName);
        self::assertSame('CPU', $drafts[1]->metricCategory);
        self::assertSame('MEMORY', $drafts[2]->metricCategory);
        self::assertSame(409600, $drafts[2]->metricValue['ramUsedBytes']);
    }
}
