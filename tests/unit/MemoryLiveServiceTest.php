<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Services\MemoryLiveService;

final class MemoryLiveServiceTest extends TestCase
{
    public function testSnapshotIncludesStatusReasonsAndPhysicalInventory(): void
    {
        $service = new MemoryLiveService(new FakeSystemDataSource(
            files: [
                '/proc/meminfo' => implode("\n", [
                    'MemTotal:       1048576 kB',
                    'MemFree:         102400 kB',
                    'MemAvailable:    716800 kB',
                    'Buffers:          10240 kB',
                    'Cached:          204800 kB',
                    'SReclaimable:     20480 kB',
                    'SwapTotal:       102400 kB',
                    'SwapFree:         71680 kB',
                    'Active:          100000 kB',
                    'Inactive:        200000 kB',
                    'AnonPages:       300000 kB',
                    'Mapped:           10000 kB',
                    'Shmem:             5000 kB',
                    'Slab:             30000 kB',
                    'Dirty:              100 kB',
                    'Writeback:            0 kB',
                ]),
                '/proc/vmstat' => "pgpgin 100\npgpgout 200\npswpin 0\npswpout 0\n",
                '/proc/pressure/memory' => "some avg10=0.00 avg60=0.00 avg300=0.00 total=0\nfull avg10=0.00 avg60=0.00 avg300=0.00 total=0\n",
            ],
            commands: [
                '/bin/ps -eo pid,user,pcpu,pmem,comm --no-headers' => " 101 mysql 2.0 30.0 mysqld\n",
                '/usr/bin/sudo -n /usr/sbin/dmidecode -t memory' => implode("\n", [
                    'Physical Memory Array',
                    "\tError Correction Type: None",
                    "\tMaximum Capacity: 64 GB",
                    "\tNumber Of Devices: 2",
                    '',
                    'Handle 0x1102, DMI type 17, 92 bytes',
                    'Memory Device',
                    "\tSize: 16 GB",
                    "\tForm Factor: SODIMM",
                    "\tLocator: DIMM1",
                    "\tBank Locator: BANK 0",
                    "\tType: DDR5",
                    "\tSpeed: 5600 MT/s",
                    "\tManufacturer: Example",
                    "\tSerial Number: ABCD1234",
                    "\tPart Number: MOD-16G",
                    "\tRank: 1",
                    "\tConfigured Memory Speed: 4800 MT/s",
                    "\tConfigured Voltage: 1.1 V",
                ]),
            ],
        ));

        $snapshot = $service->snapshot(deltaMicroseconds: 0);

        self::assertSame('OK', $snapshot['status']);
        self::assertSame([], $snapshot['statusReasons']);
        self::assertSame(30.0, $snapshot['swap']['usedPercent']);
        self::assertFalse($snapshot['swap']['activePressure']);
        self::assertSame(0.0, $snapshot['swap']['activityPagesPerSecond']);
        self::assertTrue($snapshot['inventory']['available']);
        self::assertSame('64 GB', $snapshot['inventory']['maximumCapacity']);
        self::assertSame(1, $snapshot['inventory']['occupiedSlots']);
        self::assertSame('DIMM1', $snapshot['inventory']['devices'][0]['locator']);
        self::assertSame('****1234', $snapshot['inventory']['devices'][0]['serialNumber']);
    }

    public function testSnapshotWarnsWhenSwapHasActivePressure(): void
    {
        $service = new MemoryLiveService(new FakeSystemDataSource(
            files: [
                '/proc/meminfo' => implode("\n", [
                    'MemTotal:       1048576 kB',
                    'MemFree:         102400 kB',
                    'MemAvailable:    716800 kB',
                    'Buffers:          10240 kB',
                    'Cached:          204800 kB',
                    'SReclaimable:     20480 kB',
                    'SwapTotal:       102400 kB',
                    'SwapFree:         71680 kB',
                ]),
                '/proc/vmstat' => "pgpgin 100\npgpgout 200\npswpin 0\npswpout 0\n",
                '/proc/pressure/memory' => "some avg10=6.00 avg60=2.00 avg300=1.00 total=0\nfull avg10=0.00 avg60=0.00 avg300=0.00 total=0\n",
            ],
            commands: [
                '/bin/ps -eo pid,user,pcpu,pmem,comm --no-headers' => '',
            ],
        ));

        $snapshot = $service->snapshot(deltaMicroseconds: 0);

        self::assertSame('WARNING', $snapshot['status']);
        self::assertTrue($snapshot['swap']['activePressure']);
        self::assertSame('Swap com atividade recente', $snapshot['statusReasons'][0]['label']);
        self::assertSame('Pressão PSI some avg10 >= 5%', $snapshot['statusReasons'][1]['label']);
    }
}
