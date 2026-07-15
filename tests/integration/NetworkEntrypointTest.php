<?php

declare(strict_types=1);

namespace TPanel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TPanel\Support\Application;

final class NetworkEntrypointTest extends TestCase
{
    public function testNetworkPageRendersLiveMonitoringSurface(): void
    {
        $html = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/network',
            'REMOTE_USER' => 'rodrigo',
        ]);

        self::assertStringContainsString('Rede em tempo real', $html);
        self::assertStringContainsString('data-network-page', $html);
        self::assertStringContainsString('data-network-chart="rx"', $html);
        self::assertStringContainsString('data-network-chart="tx"', $html);
        self::assertStringContainsString('data-live-countdown', $html);
        self::assertStringContainsString('data-live-pause', $html);
        self::assertStringContainsString('Portas escutando', $html);
        self::assertStringContainsString('Aplicações e conexões', $html);
        self::assertStringContainsString('Conexões estabelecidas', $html);
        self::assertStringContainsString('Rotas e DNS', $html);
        self::assertStringContainsString('network-initial-data', $html);
        self::assertStringContainsString('href="/network"', $html);
        self::assertStringContainsString('data-nav-alerts-for="network"', $html);
    }

    public function testNetworkLiveRouteReturnsJsonSnapshot(): void
    {
        $json = (new Application())->handle('GET', [], [
            'REQUEST_URI' => '/network/live',
            'REMOTE_USER' => 'rodrigo',
        ]);

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('summary', $payload);
        self::assertArrayHasKey('interfaces', $payload);
        self::assertArrayHasKey('listeners', $payload);
        self::assertArrayHasKey('connections', $payload);
        self::assertArrayHasKey('topApplications', $payload);
        self::assertArrayHasKey('firewall', $payload);
        self::assertArrayHasKey('statusReasons', $payload);
    }
}
