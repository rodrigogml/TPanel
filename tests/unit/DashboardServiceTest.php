<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\UserRole;
use TPanel\Services\DashboardService;

final class DashboardServiceTest extends TestCase
{
    public function testSummaryReturnsBootstrapState(): void
    {
        $summary = (new DashboardService())->summary();

        self::assertSame('TPanel', $summary['name']);
        self::assertSame('Bootstrap ready', $summary['status']);
        self::assertSame('NORMAL', $summary['healthStatus']);
        self::assertNotEmpty($summary['collectedAt']);
        self::assertCount(9, $summary['cards']);
        self::assertSame(
            ['overall', 'uptime', 'cpu', 'memory', 'storage', 'raid', 'network', 'docker', 'alerts'],
            array_column($summary['cards'], 'key')
        );
        self::assertSame(
            ['system', 'cpu-memory', 'storage-disks', 'raid-network', 'process-logs', 'security-sensors', 'schedules'],
            array_column($summary['monitoringSections'], 'key')
        );
        self::assertSame('ADMINISTRATOR', $summary['currentUser']['role']);
    }

    public function testAdministratorReceivesAllowedServiceAndContainerActions(): void
    {
        $summary = (new DashboardService())->summaryForUser($this->user(UserRole::ADMINISTRATOR));

        self::assertSame(['service.status'], array_column($summary['services'][0]['allowedActions'], 'actionKey'));
        self::assertSame(['docker.container.status'], array_column($summary['containers'][0]['allowedActions'], 'actionKey'));
        self::assertSame(
            ['SUCCESS', 'DENIED', 'FAILED', 'TIMED_OUT'],
            array_column($summary['actionResultStatuses'], 'status')
        );
        self::assertNotEmpty($summary['auditRecords']);
        self::assertIsArray($summary['operationalAlerts']);
    }

    public function testMonitorReceivesNoAdministrativeActionControls(): void
    {
        $summary = (new DashboardService())->summaryForUser($this->user(UserRole::MONITOR));

        self::assertSame('MONITOR', $summary['currentUser']['role']);
        self::assertSame([], $summary['services'][0]['allowedActions']);
        self::assertSame([], $summary['containers'][0]['allowedActions']);
    }

    public function testLatestAuditActionIsExposedFirstForFastLookup(): void
    {
        $summary = (new DashboardService())->summaryForUser($this->user(UserRole::ADMINISTRATOR));

        self::assertSame(1024, $summary['auditRecords'][0]['id']);
        self::assertSame('service.status', $summary['auditRecords'][0]['actionKey']);
        self::assertSame('SUCCESS', $summary['auditRecords'][0]['resultStatus']);
    }

    public function testConfirmationMetadataIsExposedForEnabledConfirmableCatalogActions(): void
    {
        $catalog = require __DIR__ . '/../../config/commands.php.model';
        $catalog['commands']['service.restart']['enabled'] = true;
        $summary = (new DashboardService(commandCatalog: $catalog))->summaryForUser($this->user(UserRole::ADMINISTRATOR));
        $actionsByKey = array_column($summary['services'][0]['allowedActions'], null, 'actionKey');

        self::assertArrayHasKey('service.restart', $actionsByKey);
        self::assertTrue($actionsByKey['service.restart']['requiresConfirmation']);
    }

    private function user(string $roleName): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: 1,
            externalUsername: strtolower($roleName) . '.local',
            displayName: null,
            isActive: true,
            role: new UserRole(
                id: 1,
                roleName: $roleName,
                description: $roleName,
                canRunAdministrativeAction: $roleName === UserRole::ADMINISTRATOR,
                canAcknowledgeAlert: true,
                canCommentEvent: true,
            ),
        );
    }
}
