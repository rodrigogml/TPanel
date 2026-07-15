<?php

declare(strict_types=1);

namespace TPanel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPanel\Support\Application;

final class MemoryEntrypointTest extends TestCase
{
    public function testMemoryPageRendersLiveMonitoringSurface(): void
    {
        $html = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/memory',
            'REMOTE_USER' => 'rodrigo',
        ]);

        self::assertStringContainsString('Memória em tempo real', $html);
        self::assertStringContainsString('data-memory-page', $html);
        self::assertStringContainsString('data-live-countdown', $html);
        self::assertStringContainsString('data-live-pause', $html);
        self::assertStringContainsString('data-alerts-button', $html);
        self::assertStringContainsString('navigation-alerts-data', $html);
        self::assertStringContainsString('data-nav-alerts-for="memory"', $html);
        self::assertStringNotContainsString('data-live-status', $html);
        self::assertStringContainsString('data-memory-chart="ram"', $html);
        self::assertStringContainsString('Composição da RAM', $html);
        self::assertStringContainsString('Estado operacional', $html);
        self::assertStringContainsString('Inventário físico', $html);
        self::assertStringContainsString('Processos por memória', $html);
        self::assertStringContainsString('memory.swap.reload', $html);
        self::assertStringContainsString('Recarregar swap', $html);
        self::assertStringContainsString('data-memory-field="swapActivity"', $html);
        self::assertStringContainsString('<th>Usuário</th>', $html);
        self::assertStringContainsString('href="/memory"', $html);
    }

    public function testMemoryLiveRouteReturnsJsonSnapshot(): void
    {
        $json = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/memory/live',
            'REMOTE_USER' => 'rodrigo',
        ]);

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('ram', $payload);
        self::assertArrayHasKey('swap', $payload);
        self::assertArrayHasKey('activityPagesPerSecond', $payload['swap']);
        self::assertArrayHasKey('activePressure', $payload['swap']);
        self::assertArrayHasKey('paging', $payload);
        self::assertArrayHasKey('pressure', $payload);
        self::assertArrayHasKey('inventory', $payload);
        self::assertArrayHasKey('statusReasons', $payload);
        self::assertArrayHasKey('topProcesses', $payload);
    }
}
