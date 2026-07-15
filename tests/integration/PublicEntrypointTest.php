<?php

declare(strict_types=1);

namespace TPanel\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PublicEntrypointTest extends TestCase
{
    public function testEntrypointRendersBootstrapPageWhenDependenciesExist(): void
    {
        ob_start();
        require dirname(__DIR__, 2) . '/public/index.php';
        $output = (string) ob_get_clean();

        self::assertStringContainsString('Turin Panel', $output);
        self::assertStringContainsString('data-theme-toggle', $output);
        self::assertStringContainsString('class="metric-grid"', $output);
        self::assertStringContainsString('Saude geral', $output);
        self::assertStringContainsString('Uptime', $output);
        self::assertStringContainsString('RAID', $output);
        self::assertStringContainsString('Monitoramento detalhado', $output);
        self::assertStringContainsString('Agendamentos', $output);
        self::assertStringContainsString('Service status', $output);
        self::assertStringContainsString('Container status', $output);
        self::assertStringContainsString('TIMED_OUT', $output);
        self::assertStringContainsString('Auditoria', $output);
        self::assertStringContainsString('resultStatus', $output);
        self::assertStringContainsString('Reconhecer', $output);
        self::assertStringContainsString('Comentario operacional', $output);
        self::assertStringContainsString('AUDIT_RECORD', $output);
        self::assertStringContainsString('Bootstrap ready', $output);
    }
}
