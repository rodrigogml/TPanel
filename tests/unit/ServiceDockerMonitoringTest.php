<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Collectors\DockerInventoryCollector as DockerCollector;
use TPanel\Collectors\ServiceInventoryCollector as ServiceCollector;
use TPanel\Collectors\MonitoringCollectorService;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\UserRole;
use TPanel\Services\AdministrativeActionAvailabilityService;

require_once __DIR__ . '/FakeSystemDataSource.php';

final class ServiceDockerMonitoringTest extends TestCase
{
    public function testServiceInventoryCollectorParsesSystemdServices(): void
    {
        $collector = new ServiceCollector(new FakeSystemDataSource(commands: [
            '/usr/bin/systemctl list-units --type=service --all --no-legend --no-pager' => implode("\n", [
                'apache2.service loaded active running The Apache HTTP Server',
                'mysql.service loaded inactive dead MySQL Community Server',
                'malformed line',
                '',
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:00:00'));

        self::assertTrue($snapshot->available);
        self::assertCount(2, $snapshot->services);
        self::assertSame('apache2.service', $snapshot->services[0]['name']);
        self::assertSame('ACTIVE', $snapshot->services[0]['activeState']);
        self::assertSame('RUNNING', $snapshot->services[0]['subState']);
        self::assertSame('The Apache HTTP Server', $snapshot->services[0]['description']);
        self::assertSame('INACTIVE', $snapshot->services[1]['activeState']);
    }

    public function testDockerInventoryCollectorParsesContainersWhenDockerIsAvailable(): void
    {
        $collector = new DockerCollector(new FakeSystemDataSource(commands: [
            '/usr/bin/docker ps --all --format {{.Names}}\t{{.ID}}\t{{.Image}}\t{{.Status}}\t{{.State}}' => implode("\n", [
                "web\tabc123\tnginx:latest\tUp 5 minutes\trunning",
                "worker\tdef456\tphp:8.4\tExited (0) 1 hour ago\texited",
            ]),
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:01:00'));

        self::assertTrue($snapshot->available);
        self::assertTrue($snapshot->dockerAvailable);
        self::assertCount(2, $snapshot->containers);
        self::assertSame('web', $snapshot->containers[0]['name']);
        self::assertSame('RUNNING', $snapshot->containers[0]['state']);
        self::assertSame('EXITED', $snapshot->containers[1]['state']);
    }

    public function testDockerInventoryCollectorMarksDockerUnavailableWhenCommandFails(): void
    {
        $collector = new DockerCollector(new FakeSystemDataSource());

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:02:00'));

        self::assertFalse($snapshot->available);
        self::assertFalse($snapshot->dockerAvailable);
        self::assertSame([], $snapshot->containers);
    }

    public function testDockerInventoryCollectorHandlesAvailableDockerWithoutContainers(): void
    {
        $collector = new DockerCollector(new FakeSystemDataSource(commands: [
            '/usr/bin/docker ps --all --format {{.Names}}\t{{.ID}}\t{{.Image}}\t{{.Status}}\t{{.State}}' => '',
        ]));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 14:03:00'));

        self::assertTrue($snapshot->available);
        self::assertTrue($snapshot->dockerAvailable);
        self::assertSame([], $snapshot->containers);
    }

    public function testMonitoringCollectorServiceBuildsServiceAndDockerDrafts(): void
    {
        $dataSource = new FakeSystemDataSource(commands: [
            '/usr/bin/systemctl list-units --type=service --all --no-legend --no-pager' => 'apache2.service loaded active running The Apache HTTP Server',
            '/usr/bin/docker ps --all --format {{.Names}}\t{{.ID}}\t{{.Image}}\t{{.Status}}\t{{.State}}' => "web\tabc123\tnginx:latest\tUp 5 minutes\trunning",
        ]);
        $service = new MonitoringCollectorService(
            serviceInventoryCollector: new ServiceCollector($dataSource),
            dockerInventoryCollector: new DockerCollector($dataSource),
        );

        $drafts = $service->collectServicesAndContainers(new DateTimeImmutable('2026-07-15 14:04:00'));

        self::assertCount(2, $drafts);
        self::assertSame('SERVICE', $drafts[0]->metricCategory);
        self::assertSame('systemd-services', $drafts[0]->metricName);
        self::assertSame('NORMAL', $drafts[0]->severity);
        self::assertSame('docker-containers', $drafts[1]->metricName);
        self::assertSame('web', $drafts[1]->metricValue['containers'][0]['name']);
    }

    public function testMonitoringCollectorServiceMarksDockerDraftUnavailableWithoutDocker(): void
    {
        $dataSource = new FakeSystemDataSource(commands: [
            '/usr/bin/systemctl list-units --type=service --all --no-legend --no-pager' => 'apache2.service loaded active running The Apache HTTP Server',
        ]);
        $service = new MonitoringCollectorService(
            serviceInventoryCollector: new ServiceCollector($dataSource),
            dockerInventoryCollector: new DockerCollector($dataSource),
        );

        $drafts = $service->collectServicesAndContainers(new DateTimeImmutable('2026-07-15 14:05:00'));

        self::assertSame('NORMAL', $drafts[0]->severity);
        self::assertSame('UNAVAILABLE', $drafts[1]->severity);
        self::assertFalse($drafts[1]->metricValue['dockerAvailable']);
    }

    public function testAdministrativeActionAvailabilityFollowsRoleAndAuthorizedCatalog(): void
    {
        $service = new AdministrativeActionAvailabilityService();
        $catalog = require __DIR__ . '/../../config/commands.php.model';

        $administratorServiceActions = $service->allowedForTarget(
            $this->user(UserRole::ADMINISTRATOR),
            $catalog,
            'SERVICE'
        );
        $administratorContainerActions = $service->allowedForTarget(
            $this->user(UserRole::ADMINISTRATOR),
            $catalog,
            'CONTAINER'
        );
        $monitorServiceActions = $service->allowedForTarget(
            $this->user(UserRole::MONITOR),
            $catalog,
            'SERVICE'
        );

        self::assertSame(['service.status'], array_column($administratorServiceActions, 'actionKey'));
        self::assertSame(['docker.container.status'], array_column($administratorContainerActions, 'actionKey'));
        self::assertSame([], $monitorServiceActions);
        self::assertFalse($administratorServiceActions[0]['requiresConfirmation']);
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
