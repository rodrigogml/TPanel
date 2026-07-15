<?php

declare(strict_types=1);

namespace TPanel\Services;

use TPanel\Security\AuthenticatedUser;
use TPanel\Security\UserRole;

final class DashboardService
{
    /**
     * @param array<string, mixed>|null $commandCatalog
     */
    public function __construct(
        private readonly AdministrativeActionAvailabilityService $actionAvailabilityService = new AdministrativeActionAvailabilityService(),
        private readonly ?array $commandCatalog = null,
    ) {
    }

    /**
     * @return array{
     *     name: string,
     *     status: string,
     *     healthStatus: string,
     *     freshnessStatus: string,
     *     collectedAt: string,
     *     currentUser: array{username: string, role: string},
     *     cards: list<array{key: string, title: string, severity: string, primaryValue: string, secondaryValue: string}>,
     *     monitoringSections: list<array{key: string, title: string, severity: string, items: list<array{label: string, value: string}>}>,
     *     services: list<array{name: string, state: string, severity: string, allowedActions: list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>}>,
     *     containers: list<array{name: string, image: string, state: string, severity: string, allowedActions: list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>}>,
     *     actionResultStatuses: list<array{status: string, label: string}>,
     *     auditRecords: list<array{id: int, actor: string, actionKey: string, resultStatus: string, occurredAt: string}>,
     *     operationalAlerts: list<array{id: int, source: string, title: string, severity: string, status: string}>,
     *     alerts: list<array{source: string, title: string, severity: string}>
     * }
     */
    public function summary(): array
    {
        $actor = $this->defaultActor($_SERVER['REMOTE_USER'] ?? 'apache-user', UserRole::ADMINISTRATOR);

        return $this->summaryForUser($actor);
    }

    /**
     * @return array{
     *     name: string,
     *     status: string,
     *     healthStatus: string,
     *     freshnessStatus: string,
     *     collectedAt: string,
     *     currentUser: array{username: string, role: string},
     *     cards: list<array{key: string, title: string, severity: string, primaryValue: string, secondaryValue: string}>,
     *     monitoringSections: list<array{key: string, title: string, severity: string, items: list<array{label: string, value: string}>}>,
     *     services: list<array{name: string, state: string, severity: string, allowedActions: list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>}>,
     *     containers: list<array{name: string, image: string, state: string, severity: string, allowedActions: list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>}>,
     *     actionResultStatuses: list<array{status: string, label: string}>,
     *     auditRecords: list<array{id: int, actor: string, actionKey: string, resultStatus: string, occurredAt: string}>,
     *     operationalAlerts: list<array{id: int, source: string, title: string, severity: string, status: string}>,
     *     alerts: list<array{source: string, title: string, severity: string}>
     * }
     */
    public function summaryForUser(AuthenticatedUser $actor): array
    {
        $catalog = $this->commandCatalog();
        $serviceActions = $this->actionAvailabilityService->allowedForTarget($actor, $catalog, 'SERVICE');
        $containerActions = $this->actionAvailabilityService->allowedForTarget($actor, $catalog, 'CONTAINER');

        return [
            'name' => 'TPanel',
            'status' => 'Bootstrap ready',
            'healthStatus' => 'NORMAL',
            'freshnessStatus' => 'FRESH',
            'collectedAt' => gmdate('c'),
            'currentUser' => [
                'username' => $actor->externalUsername(),
                'role' => $actor->role()->roleName(),
            ],
            'cards' => [
                [
                    'key' => 'overall',
                    'title' => 'Saude geral',
                    'severity' => 'NORMAL',
                    'primaryValue' => 'Normal',
                    'secondaryValue' => 'sem incidentes criticos',
                ],
                [
                    'key' => 'uptime',
                    'title' => 'Uptime',
                    'severity' => 'NORMAL',
                    'primaryValue' => '3d 04h',
                    'secondaryValue' => 'coleta atual',
                ],
                [
                    'key' => 'cpu',
                    'title' => 'CPU',
                    'severity' => 'NORMAL',
                    'primaryValue' => '18%',
                    'secondaryValue' => '4 cores online',
                ],
                [
                    'key' => 'memory',
                    'title' => 'Memoria',
                    'severity' => 'NORMAL',
                    'primaryValue' => '42%',
                    'secondaryValue' => 'RAM disponivel',
                ],
                [
                    'key' => 'storage',
                    'title' => 'Disco',
                    'severity' => 'WARNING',
                    'primaryValue' => '78%',
                    'secondaryValue' => '/ quase no limite',
                ],
                [
                    'key' => 'raid',
                    'title' => 'RAID',
                    'severity' => 'UNAVAILABLE',
                    'primaryValue' => 'Ausente',
                    'secondaryValue' => 'nenhum array detectado',
                ],
                [
                    'key' => 'network',
                    'title' => 'Rede',
                    'severity' => 'NORMAL',
                    'primaryValue' => '12 ms',
                    'secondaryValue' => 'gateway respondendo',
                ],
                [
                    'key' => 'docker',
                    'title' => 'Docker',
                    'severity' => 'UNAVAILABLE',
                    'primaryValue' => 'Ausente',
                    'secondaryValue' => 'capacidade opcional',
                ],
                [
                    'key' => 'alerts',
                    'title' => 'Alertas',
                    'severity' => 'NORMAL',
                    'primaryValue' => '0',
                    'secondaryValue' => 'abertos',
                ],
            ],
            'monitoringSections' => [
                [
                    'key' => 'system',
                    'title' => 'Sistema',
                    'severity' => 'NORMAL',
                    'items' => [
                        ['label' => 'Hostname', 'value' => 'tpanel-local'],
                        ['label' => 'Debian', 'value' => '13'],
                        ['label' => 'Kernel', 'value' => '6.x'],
                        ['label' => 'Load average', 'value' => '0.18 0.21 0.24'],
                    ],
                ],
                [
                    'key' => 'cpu-memory',
                    'title' => 'CPU e Memoria',
                    'severity' => 'NORMAL',
                    'items' => [
                        ['label' => 'CPU total', 'value' => '18%'],
                        ['label' => 'Frequencia', 'value' => '2400 MHz'],
                        ['label' => 'RAM usada', 'value' => '42%'],
                        ['label' => 'Swap', 'value' => '0%'],
                    ],
                ],
                [
                    'key' => 'storage-disks',
                    'title' => 'Armazenamento e Discos',
                    'severity' => 'WARNING',
                    'items' => [
                        ['label' => 'Filesystem /', 'value' => '78% usado'],
                        ['label' => 'Inodes', 'value' => '12% usado'],
                        ['label' => 'SMART', 'value' => 'sem erros criticos'],
                        ['label' => 'Temperatura', 'value' => 'indisponivel'],
                    ],
                ],
                [
                    'key' => 'raid-network',
                    'title' => 'RAID e Rede',
                    'severity' => 'UNAVAILABLE',
                    'items' => [
                        ['label' => 'RAID', 'value' => 'nenhum array detectado'],
                        ['label' => 'Gateway', 'value' => 'respondendo'],
                        ['label' => 'DNS', 'value' => 'configurado'],
                        ['label' => 'Latencia', 'value' => '12 ms'],
                    ],
                ],
                [
                    'key' => 'process-logs',
                    'title' => 'Processos e Logs',
                    'severity' => 'NORMAL',
                    'items' => [
                        ['label' => 'Top CPU', 'value' => 'apache2'],
                        ['label' => 'Top RAM', 'value' => 'mysqld'],
                        ['label' => 'Journal', 'value' => '0 erros recentes'],
                        ['label' => 'Syslog', 'value' => '0 erros recentes'],
                    ],
                ],
                [
                    'key' => 'security-sensors',
                    'title' => 'Seguranca e Sensores',
                    'severity' => 'UNAVAILABLE',
                    'items' => [
                        ['label' => 'SSH falhas', 'value' => '0 recentes'],
                        ['label' => 'Firewall', 'value' => 'ativo'],
                        ['label' => 'Atualizacoes', 'value' => '0 pendentes'],
                        ['label' => 'Sensores', 'value' => 'capacidade ausente'],
                    ],
                ],
                [
                    'key' => 'schedules',
                    'title' => 'Agendamentos',
                    'severity' => 'NORMAL',
                    'items' => [
                        ['label' => 'Cron', 'value' => '3 jobs conhecidos'],
                        ['label' => 'Timers', 'value' => '1 timer ativo'],
                        ['label' => 'Ultima execucao', 'value' => 'coletado quando disponivel'],
                        ['label' => 'Proxima execucao', 'value' => 'coletado quando disponivel'],
                    ],
                ],
            ],
            'services' => [
                ['name' => 'apache2.service', 'state' => 'ACTIVE', 'severity' => 'NORMAL', 'allowedActions' => $serviceActions],
                ['name' => 'mysql.service', 'state' => 'ACTIVE', 'severity' => 'NORMAL', 'allowedActions' => $serviceActions],
                ['name' => 'docker.service', 'state' => 'UNAVAILABLE', 'severity' => 'UNAVAILABLE', 'allowedActions' => $serviceActions],
            ],
            'containers' => [
                [
                    'name' => 'web',
                    'image' => 'nginx:latest',
                    'state' => 'RUNNING',
                    'severity' => 'NORMAL',
                    'allowedActions' => $containerActions,
                ],
                [
                    'name' => 'worker',
                    'image' => 'php:8.4',
                    'state' => 'EXITED',
                    'severity' => 'WARNING',
                    'allowedActions' => $containerActions,
                ],
            ],
            'actionResultStatuses' => [
                ['status' => 'SUCCESS', 'label' => 'Executado com sucesso'],
                ['status' => 'DENIED', 'label' => 'Negado antes da execucao'],
                ['status' => 'FAILED', 'label' => 'Falha reportada'],
                ['status' => 'TIMED_OUT', 'label' => 'Tempo limite excedido'],
            ],
            'auditRecords' => [
                [
                    'id' => 1024,
                    'actor' => 'admin.local',
                    'actionKey' => 'service.status',
                    'resultStatus' => 'SUCCESS',
                    'occurredAt' => '2026-07-15T00:20:00Z',
                ],
                [
                    'id' => 1023,
                    'actor' => 'monitor.local',
                    'actionKey' => 'service.restart',
                    'resultStatus' => 'DENIED',
                    'occurredAt' => '2026-07-15T00:18:00Z',
                ],
                [
                    'id' => 1022,
                    'actor' => 'admin.local',
                    'actionKey' => 'docker.container.status',
                    'resultStatus' => 'FAILED',
                    'occurredAt' => '2026-07-15T00:14:00Z',
                ],
            ],
            'operationalAlerts' => [
                [
                    'id' => 41,
                    'source' => 'storage',
                    'title' => 'Uso de disco acima de 75%',
                    'severity' => 'WARNING',
                    'status' => 'OPEN',
                ],
                [
                    'id' => 40,
                    'source' => 'service',
                    'title' => 'Container worker encerrado',
                    'severity' => 'WARNING',
                    'status' => 'ACKNOWLEDGED',
                ],
            ],
            'alerts' => [
                ['source' => 'storage', 'title' => 'Uso de disco acima de 75%', 'severity' => 'WARNING'],
            ],
        ];
    }

    private function defaultActor(string $username, string $roleName): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id: 1,
            externalUsername: $username,
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

    /**
     * @return array<string, mixed>
     */
    private function commandCatalog(): array
    {
        if ($this->commandCatalog !== null) {
            return $this->commandCatalog;
        }

        return require dirname(__DIR__, 2) . '/config/commands.php.model';
    }
}
