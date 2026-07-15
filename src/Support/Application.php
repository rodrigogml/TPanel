<?php

declare(strict_types=1);

namespace TPanel\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use TPanel\Alerts\Alert;
use TPanel\Command\AuthorizedCommandExecutor;
use TPanel\Controllers\CpuController;
use TPanel\Controllers\DashboardController;
use TPanel\Controllers\DisksController;
use TPanel\Controllers\MemoryController;
use TPanel\Controllers\NetworkController;
use TPanel\Repositories\InMemoryAlertRepository;
use TPanel\Repositories\InMemoryAuditRecordRepository;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\UserRole;
use TPanel\Services\AlertService;
use TPanel\Services\AuditService;
use TPanel\Services\DashboardService;
use TPanel\Services\WebSubmissionResult;
use TPanel\Services\WebSubmissionService;

final class Application
{
    /**
     * @param array<string, mixed>|null $commandCatalog
     */
    public function __construct(
        private readonly ?array $commandCatalog = null,
        private readonly ?AuthorizedCommandExecutor $commandExecutor = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $server
     */
    public function handle(?string $method = null, ?array $post = null, ?array $server = null): string
    {
        $method = strtoupper($method ?? (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $post = $post ?? $_POST;
        $server = $server ?? $_SERVER;
        $path = $this->pathFromServer($server);
        $actor = $this->actorFromServer($server);
        $auditRepository = new InMemoryAuditRecordRepository();
        $alertRepository = $this->seededAlertRepository();
        $auditService = new AuditService($auditRepository);
        $submissionResult = null;

        if ($method === 'GET' && $path === '/cpu/live') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            return (new CpuController())->live();
        }

        if ($method === 'GET' && $path === '/memory/live') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            return (new MemoryController())->live();
        }

        if ($method === 'GET' && $path === '/network/live') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            return (new NetworkController())->live();
        }

        if ($method === 'POST') {
            try {
                $submissionResult = (new WebSubmissionService(
                    alertService: new AlertService($alertRepository, $auditService),
                    auditService: $auditService,
                    commandExecutor: $this->commandExecutor ?? new AuthorizedCommandExecutor(),
                    commandCatalog: $this->commandCatalog,
                ))->handle($actor, $post);
            } catch (InvalidArgumentException $exception) {
                $submissionResult = new WebSubmissionResult(
                    requestId: '',
                    resultStatus: 'DENIED',
                    message: $exception->getMessage(),
                    auditRecordId: null,
                    exitCode: null,
                );
            }
        }

        if ($method === 'GET' && $path === '/cpu') {
            return (new CpuController())->index($actor);
        }

        if (($method === 'GET' || $method === 'POST') && $path === '/memory') {
            return (new MemoryController())->index($actor, $submissionResult);
        }

        if ($method === 'GET' && $path === '/disks') {
            return (new DisksController())->index($actor);
        }

        if ($method === 'GET' && $path === '/network') {
            return (new NetworkController())->index($actor);
        }

        $controller = new DashboardController(new DashboardService(commandCatalog: $this->commandCatalog));

        return $controller->index($actor, $submissionResult);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function pathFromServer(array $server): string
    {
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rtrim($path, '/') ?: '/' : '/';
    }

    /**
     * @param array<string, mixed> $server
     */
    private function actorFromServer(array $server): AuthenticatedUser
    {
        $username = trim((string) ($server['REMOTE_USER'] ?? 'apache-user')) ?: 'apache-user';
        $roleName = strtoupper(trim((string) ($server['TPANEL_ROLE'] ?? '')));

        if (!in_array($roleName, [UserRole::ADMINISTRATOR, UserRole::MONITOR], true)) {
            $roleName = $this->roleForUsername($username);
        }

        return new AuthenticatedUser(
            id: $roleName === UserRole::ADMINISTRATOR ? 1 : 2,
            externalUsername: $username,
            displayName: null,
            isActive: true,
            role: new UserRole(
                id: $roleName === UserRole::ADMINISTRATOR ? 1 : 2,
                roleName: $roleName,
                description: $roleName,
                canRunAdministrativeAction: $roleName === UserRole::ADMINISTRATOR,
                canAcknowledgeAlert: true,
                canCommentEvent: true,
            ),
        );
    }

    private function roleForUsername(string $username): string
    {
        $normalized = strtolower($username);

        if (in_array($normalized, ['monitor', 'monitor.local', 'tpanel-monitor'], true)) {
            return UserRole::MONITOR;
        }

        return UserRole::ADMINISTRATOR;
    }

    private function seededAlertRepository(): InMemoryAlertRepository
    {
        $repository = new InMemoryAlertRepository();
        $repository->seed(new Alert(
            id: 41,
            idMetricReading: null,
            alertSource: 'storage',
            severity: 'WARNING',
            title: 'Uso de montagem acima de 75%',
            message: 'Montagem acima do threshold operacional.',
            status: 'OPEN',
            openedAt: new DateTimeImmutable('2026-07-15 00:00:00'),
            resolvedAt: null,
        ));
        $repository->seed(new Alert(
            id: 40,
            idMetricReading: null,
            alertSource: 'service',
            severity: 'WARNING',
            title: 'Container worker encerrado',
            message: 'Container worker saiu antes da última coleta.',
            status: 'ACKNOWLEDGED',
            openedAt: new DateTimeImmutable('2026-07-14 23:40:00'),
            resolvedAt: null,
        ));

        return $repository;
    }
}
