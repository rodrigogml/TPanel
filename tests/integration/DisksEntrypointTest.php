<?php

declare(strict_types=1);

namespace TPanel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPanel\Services\DiskOverviewService;
use TPanel\Support\Application;

final class DisksEntrypointTest extends TestCase
{
    public function testDisksPageRendersStorageSurfaceAndSmartctlNoticeOnlyWhenUnavailable(): void
    {
        $html = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/disks',
            'REMOTE_USER' => 'rodrigo',
        ]);

        self::assertStringContainsString('Discos e armazenamento', $html);
        self::assertStringContainsString('data-disks-page', $html);
        self::assertStringContainsString('SMART', $html);
        self::assertStringContainsString('Discos e partições', $html);
        self::assertStringContainsString('disk-device-depth-1 is-child', $html);
        self::assertStringContainsString('Montagens ativas', $html);
        self::assertStringContainsString('disk-usage-gauge', $html);
        self::assertStringContainsString('Ocupação', $html);
        self::assertStringContainsString('/etc/fstab', $html);
        self::assertStringContainsString('href="/disks"', $html);
        self::assertStringContainsString('data-alerts-button', $html);
        self::assertStringContainsString('data-nav-alerts-for="disks"', $html);
        self::assertStringNotContainsString('data-live-status', $html);

        $smartctl = (new DiskOverviewService())->snapshot()['smartctl'];

        if ((bool) ($smartctl['available'] ?? false)) {
            self::assertStringNotContainsString('Dependência smartctl', $html);
            self::assertStringNotContainsString('SMART Indisponível', $html);
        } else {
            self::assertStringContainsString('Dependência smartctl', $html);
            self::assertStringContainsString('SMART Indisponível', $html);
        }
    }
}
