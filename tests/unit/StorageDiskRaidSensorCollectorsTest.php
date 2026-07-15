<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Collectors\DiskHealthCollector;
use TPanel\Collectors\MemoryCollector;
use TPanel\Collectors\MonitoringCollectorService;
use TPanel\Collectors\RaidCollector;
use TPanel\Collectors\SensorCollector;
use TPanel\Collectors\StorageCollector;
use TPanel\Collectors\SystemCollector;
use TPanel\Collectors\CpuCollector;

require_once __DIR__ . '/FakeSystemDataSource.php';

final class StorageDiskRaidSensorCollectorsTest extends TestCase
{
    public function testStorageCollectorParsesFilesystemsInodesAndDiskIo(): void
    {
        $collector = new StorageCollector(new FakeSystemDataSource(
            files: [
                '/proc/diskstats' => "   8       0 sda 100 0 2000 0 50 0 3000 0 0 0 0 0 0 0 0 0\n",
            ],
            commands: [
                '/bin/df -P -B1' => "Filesystem 1-blocks Used Available Use% Mounted on\n/dev/sda1 1000000 400000 600000 40% /\n",
                '/bin/df -P -i' => "Filesystem Inodes IUsed IFree IUse% Mounted on\n/dev/sda1 1000 100 900 10% /\n",
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 12:00:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('/dev/sda1', $snapshot->filesystems[0]['filesystem']);
        self::assertSame('/', $snapshot->filesystems[0]['mountPoint']);
        self::assertSame(600000, $snapshot->filesystems[0]['availableBytes']);
        self::assertSame(10.0, $snapshot->filesystems[0]['inodeUsedPercent']);
        self::assertSame('sda', $snapshot->diskIo[0]['device']);
        self::assertSame(100, $snapshot->diskIo[0]['readsCompleted']);
        self::assertSame(50, $snapshot->diskIo[0]['writesCompleted']);
    }

    public function testDiskHealthCollectorParsesSmartData(): void
    {
        $collector = new DiskHealthCollector(new FakeSystemDataSource(commands: [
            '/usr/bin/lsblk -dn -o NAME,TYPE' => "sda disk\nsda1 part\n",
            '/usr/sbin/smartctl -A -H /dev/sda' => implode("\n", [
                'SMART overall-health self-assessment test result: PASSED',
                '  5 Reallocated_Sector_Ct   0x0033   100   100   010    Pre-fail  Always       -       2',
                '  9 Power_On_Hours          0x0032   090   090   000    Old_age   Always       -       1234',
                '194 Temperature_Celsius     0x0022   066   050   000    Old_age   Always       -       34',
                '197 Offline_Uncorrectable   0x0030   100   100   000    Old_age   Offline      -       1',
                '',
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 12:01:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('/dev/sda', $snapshot->disks[0]['device']);
        self::assertTrue($snapshot->disks[0]['smartAvailable']);
        self::assertSame('PASSED', $snapshot->disks[0]['healthStatus']);
        self::assertSame(34, $snapshot->disks[0]['temperatureCelsius']);
        self::assertSame(1234, $snapshot->disks[0]['powerOnHours']);
        self::assertSame(2, $snapshot->disks[0]['reallocatedSectors']);
        self::assertSame(1, $snapshot->disks[0]['criticalErrors']);
    }

    public function testRaidCollectorParsesMdstatHealthyAndSyncingArrays(): void
    {
        $collector = new RaidCollector(new FakeSystemDataSource(files: [
            '/proc/mdstat' => implode("\n", [
                'Personalities : [raid1]',
                'md0 : active raid1 sdb1[1] sda1[0]',
                '      976630336 blocks super 1.2 [2/2] [UU]',
                'md1 : active raid1 sdd1[1] sdc1[0]',
                '      1000 blocks super 1.2 [2/1] [U_]',
                '      [====>................]  recovery = 25.5% (255/1000) finish=1.0min speed=10K/sec',
                '',
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 12:02:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('md0', $snapshot->arrays[0]['name']);
        self::assertSame(0, $snapshot->arrays[0]['degradedDisks']);
        self::assertSame('SYNCING', $snapshot->arrays[1]['state']);
        self::assertSame(1, $snapshot->arrays[1]['degradedDisks']);
        self::assertSame(25.5, $snapshot->arrays[1]['syncPercent']);
    }

    public function testSensorCollectorParsesTemperatureFanVoltageAndPower(): void
    {
        $collector = new SensorCollector(new FakeSystemDataSource(commands: [
            '/usr/bin/sensors' => implode("\n", [
                'coretemp-isa-0000',
                'Package id 0:  +45.0°C  (high = +80.0°C, crit = +100.0°C)',
                'fan1:        1200 RPM',
                'in0:          +1.05 V',
                'power1:       20.50 W',
                '',
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 12:03:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('Package id 0', $snapshot->readings[0]['label']);
        self::assertSame(45.0, $snapshot->readings[0]['value']);
        self::assertSame('°C', $snapshot->readings[0]['unit']);
        self::assertSame('RPM', $snapshot->readings[1]['unit']);
        self::assertSame('V', $snapshot->readings[2]['unit']);
        self::assertSame('W', $snapshot->readings[3]['unit']);
    }

    public function testMonitoringCollectorServiceMarksMissingOptionalCapabilitiesUnavailable(): void
    {
        $dataSource = new FakeSystemDataSource();
        $service = new MonitoringCollectorService(
            new SystemCollector($dataSource),
            new CpuCollector($dataSource),
            new MemoryCollector($dataSource),
            new StorageCollector($dataSource),
            new DiskHealthCollector($dataSource),
            new RaidCollector($dataSource),
            new SensorCollector($dataSource),
        );

        $drafts = $service->collectStorageDiskRaidSensors(new DateTimeImmutable('2026-07-15 12:04:00'));

        self::assertCount(4, $drafts);
        self::assertSame('STORAGE', $drafts[0]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[0]->severity);
        self::assertSame('DISK_HEALTH', $drafts[1]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[1]->severity);
        self::assertSame('RAID', $drafts[2]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[2]->severity);
        self::assertSame('SENSOR', $drafts[3]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[3]->severity);
    }
}
