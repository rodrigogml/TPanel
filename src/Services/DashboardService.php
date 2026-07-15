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
        $diskSummary = $this->diskSummary();
        $operationalAlerts = $this->operationalAlerts($diskSummary);
        $healthStatus = $this->worstSeverity([$diskSummary['severity'], ...array_column($operationalAlerts, 'severity')]);

        return [
            'name' => 'TPanel',
            'status' => 'Bootstrap ready',
            'healthStatus' => $healthStatus,
            'freshnessStatus' => 'FRESH',
            'collectedAt' => gmdate('c'),
            'currentUser' => [
                'username' => $actor->externalUsername(),
                'role' => $actor->role()->roleName(),
            ],
            'cards' => [
                [
                    'key' => 'overall',
                    'title' => 'Saúde geral',
                    'severity' => $healthStatus,
                    'primaryValue' => $healthStatus === 'NORMAL' ? 'Normal' : $healthStatus,
                    'secondaryValue' => $healthStatus === 'NORMAL' ? 'sem incidentes críticos' : 'há alertas ativos',
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
                    'title' => 'Memória',
                    'severity' => 'NORMAL',
                    'primaryValue' => '42%',
                    'secondaryValue' => 'RAM disponível',
                ],
                [
                    'key' => 'storage',
                    'title' => 'Disco',
                    'severity' => $diskSummary['severity'],
                    'primaryValue' => $diskSummary['primaryValue'],
                    'secondaryValue' => $diskSummary['secondaryValue'],
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
                    'severity' => $operationalAlerts === [] ? 'NORMAL' : $this->worstSeverity(array_column($operationalAlerts, 'severity')),
                    'primaryValue' => (string) count($operationalAlerts),
                    'secondaryValue' => 'ativos',
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
                    'title' => 'CPU e Memória',
                    'severity' => 'NORMAL',
                    'items' => [
                        ['label' => 'CPU total', 'value' => '18%'],
                        ['label' => 'Frequência', 'value' => '2400 MHz'],
                        ['label' => 'RAM usada', 'value' => '42%'],
                        ['label' => 'Swap', 'value' => '0%'],
                    ],
                ],
                [
                    'key' => 'storage-disks',
                    'title' => 'Armazenamento e Discos',
                    'severity' => $diskSummary['severity'],
                    'items' => [
                        ['label' => 'Maior uso', 'value' => $diskSummary['largestMount']],
                        ['label' => 'Montagens', 'value' => $diskSummary['mountCount']],
                        ['label' => 'SMART', 'value' => $diskSummary['smart']],
                        ['label' => 'Temperatura', 'value' => 'não coletada nesta visão'],
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
                        ['label' => 'Latência', 'value' => '12 ms'],
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
                    'title' => 'Segurança e Sensores',
                    'severity' => 'UNAVAILABLE',
                    'items' => [
                        ['label' => 'SSH falhas', 'value' => '0 recentes'],
                        ['label' => 'Firewall', 'value' => 'ativo'],
                        ['label' => 'Atualizações', 'value' => '0 pendentes'],
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
                        ['label' => 'Última execução', 'value' => 'coletado quando disponível'],
                        ['label' => 'Próxima execução', 'value' => 'coletado quando disponível'],
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
                ['status' => 'DENIED', 'label' => 'Negado antes da execução'],
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
            'operationalAlerts' => $operationalAlerts,
            'alerts' => array_map(
                static fn (array $alert): array => [
                    'source' => $alert['source'],
                    'title' => $alert['title'],
                    'severity' => $alert['severity'],
                ],
                $operationalAlerts,
            ),
        ];
    }

    /**
     * @return array{severity: string, primaryValue: string, secondaryValue: string, largestMount: string, mountCount: string, smart: string, maxPercent: float|null, maxTarget: string|null}
     */
    private function diskSummary(): array
    {
        $snapshot = (new DiskOverviewService())->snapshot();
        $mounts = is_array($snapshot['mounts'] ?? null) ? $snapshot['mounts'] : [];
        $maxPercent = null;
        $maxTarget = null;

        foreach ($mounts as $mount) {
            if (!is_array($mount)) {
                continue;
            }

            $percent = $this->parsePercent($mount['use%'] ?? null);

            if ($percent === null) {
                continue;
            }

            if ($maxPercent === null || $percent > $maxPercent) {
                $maxPercent = $percent;
                $maxTarget = (string) ($mount['target'] ?? 'n/a');
            }
        }

        $smart = is_array($snapshot['smartctl'] ?? null) && (bool) ($snapshot['smartctl']['available'] ?? false)
            ? 'smartctl disponível'
            : 'smartctl indisponível';
        $severity = $maxPercent === null ? 'UNAVAILABLE' : ($maxPercent > 75.0 ? 'CRITICAL' : ($maxPercent > 50.0 ? 'WARNING' : 'NORMAL'));

        return [
            'severity' => $severity,
            'primaryValue' => $maxPercent === null ? 'n/a' : sprintf('%.0f%%', $maxPercent),
            'secondaryValue' => $maxTarget === null ? 'sem montagens mensuráveis' : sprintf('%s é a maior ocupação', $maxTarget),
            'largestMount' => $maxTarget === null ? 'n/a' : sprintf('%s com %.0f%% usado', $maxTarget, $maxPercent),
            'mountCount' => sprintf('%d ativas', count($mounts)),
            'smart' => $smart,
            'maxPercent' => $maxPercent,
            'maxTarget' => $maxTarget,
        ];
    }

    /**
     * @param array{severity: string, primaryValue: string, secondaryValue: string, largestMount: string, mountCount: string, smart: string, maxPercent: float|null, maxTarget: string|null} $diskSummary
     * @return list<array{id: int, source: string, title: string, severity: string, status: string}>
     */
    private function operationalAlerts(array $diskSummary): array
    {
        $alerts = [];

        if ($diskSummary['maxPercent'] !== null && $diskSummary['maxPercent'] > 50.0) {
            $alerts[] = [
                'id' => 41,
                'source' => 'storage',
                'title' => $diskSummary['maxPercent'] > 75.0 ? 'Uso de montagem acima de 75%' : 'Uso de montagem acima de 50%',
                'severity' => $diskSummary['maxPercent'] > 75.0 ? 'CRITICAL' : 'WARNING',
                'status' => 'OPEN',
            ];
        }

        return $alerts;
    }

    /**
     * @param list<string> $severities
     */
    private function worstSeverity(array $severities): string
    {
        if (in_array('CRITICAL', $severities, true)) {
            return 'CRITICAL';
        }

        if (in_array('WARNING', $severities, true)) {
            return 'WARNING';
        }

        if (in_array('UNAVAILABLE', $severities, true)) {
            return 'UNAVAILABLE';
        }

        return 'NORMAL';
    }

    private function parsePercent(mixed $usage): ?float
    {
        if (is_numeric($usage)) {
            return (float) $usage;
        }

        if (is_string($usage) && preg_match('/(\d+(?:\.\d+)?)%/', $usage, $matches)) {
            return (float) $matches[1];
        }

        return null;
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
