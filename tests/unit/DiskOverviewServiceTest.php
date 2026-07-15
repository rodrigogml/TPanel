<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Services\DiskOverviewService;

final class DiskOverviewServiceTest extends TestCase
{
    public function testSnapshotWarnsWhenSmartctlIsMissingAndParsesDiskSources(): void
    {
        $service = new DiskOverviewService(new FakeSystemDataSource(
            files: [
                '/etc/fstab' => "UUID=root / ext4 defaults 0 1\n# comment\n/dev/sdb1 /data xfs nofail 0 2\n",
            ],
            commands: [
                '/bin/lsblk -J -b -o NAME,PATH,TYPE,SIZE,MODEL,SERIAL,TRAN,ROTA,RM,MOUNTPOINTS,FSTYPE,UUID,PARTUUID' => json_encode([
                    'blockdevices' => [[
                        'name' => 'sda',
                        'path' => '/dev/sda',
                        'type' => 'disk',
                        'size' => 1073741824,
                        'model' => 'FastDisk',
                        'tran' => 'sata',
                        'mountpoints' => [],
                        'children' => [[
                            'name' => 'sda1',
                            'path' => '/dev/sda1',
                            'type' => 'part',
                            'size' => 536870912,
                            'mountpoints' => ['/'],
                            'fstype' => 'ext4',
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR),
                '/bin/findmnt -J -b -o TARGET,SOURCE,FSTYPE,OPTIONS,SIZE,USED,AVAIL,USE%' => json_encode([
                    'filesystems' => [[
                        'target' => '/',
                        'source' => '/dev/sda1',
                        'fstype' => 'ext4',
                        'options' => 'rw,relatime',
                        'size' => 536870912,
                        'used' => 134217728,
                        'avail' => 402653184,
                        'use%' => '25%',
                    ]],
                ], JSON_THROW_ON_ERROR),
            ],
        ));

        $snapshot = $service->snapshot();

        self::assertFalse($snapshot['smartctl']['available']);
        self::assertStringContainsString('smartctl instalado', $snapshot['smartctl']['warning']);
        self::assertCount(2, $snapshot['blockDevices']);
        self::assertSame('/dev/sda', $snapshot['blockDevices'][0]['path']);
        self::assertSame(0, $snapshot['blockDevices'][0]['depth']);
        self::assertSame(['/'], $snapshot['blockDevices'][1]['mountpoints']);
        self::assertSame(1, $snapshot['blockDevices'][1]['depth']);
        self::assertCount(1, $snapshot['mounts']);
        self::assertCount(2, $snapshot['fstab']);
    }

    public function testSnapshotDetectsSmartctlByAbsolutePath(): void
    {
        $service = new DiskOverviewService(new FakeSystemDataSource(commands: [
            '/usr/sbin/smartctl --version' => "smartctl 7.4 2023-08-01\n",
        ]));

        $snapshot = $service->snapshot();

        self::assertTrue($snapshot['smartctl']['available']);
        self::assertSame('/usr/sbin/smartctl', $snapshot['smartctl']['path']);
        self::assertSame('smartctl 7.4 2023-08-01', $snapshot['smartctl']['version']);
        self::assertNull($snapshot['smartctl']['warning']);
    }
}
