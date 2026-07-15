<?php

declare(strict_types=1);

namespace TPanel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPanel\Support\Application;

final class CpuEntrypointTest extends TestCase
{
    public function testCpuPageRendersLiveMonitoringSurface(): void
    {
        $html = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/cpu',
            'REMOTE_USER' => 'rodrigo',
        ]);

        self::assertStringContainsString('CPU em tempo real', $html);
        self::assertStringContainsString('data-cpu-page', $html);
        self::assertStringContainsString('id="cpu-total-chart"', $html);
        self::assertStringContainsString('data-cpu-cores', $html);
        self::assertStringContainsString('data-live-countdown', $html);
        self::assertStringContainsString('data-live-pause', $html);
        self::assertStringContainsString('data-alerts-button', $html);
        self::assertStringContainsString('navigation-alerts-data', $html);
        self::assertStringContainsString('data-nav-alerts-for="cpu"', $html);
        self::assertStringNotContainsString('data-live-status', $html);
        self::assertStringContainsString('Processos por CPU', $html);
        self::assertStringContainsString('<th>Usuário</th>', $html);
        self::assertStringContainsString('href="/cpu"', $html);
    }

    public function testCpuLiveRouteReturnsJsonSnapshot(): void
    {
        $json = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/cpu/live',
            'REMOTE_USER' => 'rodrigo',
        ]);

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('cores', $payload);
        self::assertArrayHasKey('load', $payload);
        self::assertArrayHasKey('topProcesses', $payload);
        self::assertArrayHasKey('statusReasons', $payload);
    }
}
