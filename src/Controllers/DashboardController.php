<?php

declare(strict_types=1);

namespace TPanel\Controllers;

use TPanel\Services\DashboardService;
use TPanel\Services\WebSubmissionResult;
use TPanel\Security\AuthenticatedUser;

final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function index(?AuthenticatedUser $actor = null, ?WebSubmissionResult $submissionResult = null): string
    {
        $summary = $actor === null
            ? $this->dashboardService->summary()
            : $this->dashboardService->summaryForUser($actor);
        $name = $this->escape($summary['name']);
        $status = $this->escape($summary['status']);
        $healthStatus = $this->escape($summary['healthStatus']);
        $freshnessStatus = $this->escape($summary['freshnessStatus']);
        $collectedAt = $this->escape($summary['collectedAt']);
        $username = $this->escape($summary['currentUser']['username']);
        $role = $this->escape($summary['currentUser']['role']);
        $cards = $this->renderCards($summary['cards']);
        $monitoringSections = $this->renderMonitoringSections($summary['monitoringSections']);
        $services = $this->renderServiceRows($summary['services']);
        $containers = $this->renderContainerRows($summary['containers']);
        $actionResultStatuses = $this->renderActionResultStatuses($summary['actionResultStatuses']);
        $submissionFeedback = $this->renderSubmissionFeedback($submissionResult);
        $auditRows = $this->renderAuditRows($summary['auditRecords']);
        $operationalAlertRows = $this->renderOperationalAlertRows($summary['operationalAlerts']);
        $alerts = $this->renderAlertRows($summary['alerts']);

        return <<<HTML
        <!doctype html>
        <html lang="pt-BR" data-theme="dark">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$name} - Turin Panel</title>
            <link rel="stylesheet" href="/assets/css/tpanel.css">
            <script src="/assets/js/tpanel.js" defer></script>
        </head>
        <body>
            <div class="app-shell" data-shell>
                <aside class="sidebar" aria-label="Navegacao principal">
                    <div class="brand">
                        <span class="brand-mark" aria-hidden="true">TP</span>
                        <span class="brand-text">Turin Panel</span>
                    </div>
                    <nav class="nav-list">
                        <a class="nav-item is-active" href="#top"><span>Visao geral</span></a>
                        <a class="nav-item" href="#system"><span>Sistema</span></a>
                        <a class="nav-item" href="#cpu-memory"><span>Recursos</span></a>
                        <a class="nav-item" href="#process-logs"><span>Logs</span></a>
                        <a class="nav-item" href="#schedules"><span>Agendamentos</span></a>
                    </nav>
                </aside>
                <div class="workspace">
                    <header class="topbar">
                        <button class="icon-button menu-button" type="button" data-menu-toggle aria-label="Abrir menu">☰</button>
                        <label class="search-box">
                            <span class="search-icon" aria-hidden="true">⌕</span>
                            <input type="search" placeholder="Pesquisar" aria-label="Pesquisar">
                        </label>
                        <div class="topbar-actions">
                            <button class="icon-button" type="button" data-theme-toggle aria-label="Alternar tema">◐</button>
                            <button class="alert-button" type="button" aria-label="Alertas">Alertas <strong>1</strong></button>
                            <div class="user-chip">
                                <span>{$username}</span>
                                <strong>{$role}</strong>
                            </div>
                        </div>
                    </header>
                    <main class="content" id="top">
                        <section class="page-heading">
                            <div>
                                <p class="eyebrow">{$name}</p>
                                <h1>Operacao do servidor</h1>
                            </div>
                            <div class="status-strip">
                                <span class="severity-badge severity-{$healthStatus}">{$healthStatus}</span>
                                <span>{$freshnessStatus}</span>
                                <span>{$collectedAt}</span>
                                <span>{$status}</span>
                            </div>
                        </section>
                        <section class="metric-grid" aria-label="Resumo de saude">
                            {$cards}
                        </section>
                        <section class="detail-grid" aria-label="Monitoramento detalhado">
                            {$monitoringSections}
                        </section>
                        <section class="action-results" aria-label="Resultados de acoes administrativas">
                            {$actionResultStatuses}
                        </section>
                        {$submissionFeedback}
                        <section class="split-grid services-grid">
                            <div class="panel">
                                <div class="panel-header">
                                    <h2>Servicos</h2>
                                    <span>Systemd</span>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Unidade</th>
                                                <th>Estado</th>
                                                <th>Severidade</th>
                                                <th>Acoes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$services}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="panel">
                                <div class="panel-header">
                                    <h2>Containers</h2>
                                    <span>Docker</span>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Container</th>
                                                <th>Imagem</th>
                                                <th>Estado</th>
                                                <th>Severidade</th>
                                                <th>Acoes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$containers}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <section class="split-grid">
                            <div class="panel">
                                <div class="panel-header">
                                    <h2>Auditoria</h2>
                                    <span>Ultimas acoes</span>
                                </div>
                                <form class="filter-bar" method="get" action="/">
                                    <select name="resultStatus" aria-label="Filtrar por resultado">
                                        <option value="">Resultado</option>
                                        <option value="SUCCESS">SUCCESS</option>
                                        <option value="DENIED">DENIED</option>
                                        <option value="FAILED">FAILED</option>
                                        <option value="TIMED_OUT">TIMED_OUT</option>
                                    </select>
                                    <input type="search" name="actor" placeholder="Ator" aria-label="Filtrar por ator">
                                    <input type="date" name="dateFrom" aria-label="Data inicial">
                                    <button type="submit">Filtrar</button>
                                </form>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Ator</th>
                                                <th>Acao</th>
                                                <th>Resultado</th>
                                                <th>Horario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$auditRows}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="panel">
                                <div class="panel-header">
                                    <h2>Alertas</h2>
                                    <span>Reconhecimento</span>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Fonte</th>
                                                <th>Alerta</th>
                                                <th>Severidade</th>
                                                <th>Status</th>
                                                <th>Acoes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$operationalAlertRows}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <section class="panel comment-panel">
                            <div class="panel-header">
                                <h2>Comentario operacional</h2>
                                <span>Alertas e auditoria</span>
                            </div>
                            <form class="comment-form" method="post" action="/">
                                <input type="hidden" name="requestId" value="{$this->escape($this->requestIdFor('event.comment', 'panel'))}">
                                <select name="targetType" aria-label="Tipo do alvo" required>
                                    <option value="ALERT">ALERT</option>
                                    <option value="AUDIT_RECORD">AUDIT_RECORD</option>
                                </select>
                                <input type="number" name="targetId" min="1" placeholder="ID" aria-label="ID do alvo" required>
                                <input type="text" name="commentText" placeholder="Comentario" aria-label="Comentario" required>
                                <button type="submit">Comentar</button>
                            </form>
                        </section>
                    </main>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * @param list<array{key: string, title: string, severity: string, primaryValue: string, secondaryValue: string}> $cards
     */
    private function renderCards(array $cards): string
    {
        $html = '';

        foreach ($cards as $card) {
            $severity = $this->escape($card['severity']);
            $html .= sprintf(
                '<article class="metric-card severity-left-%s"><div><span class="card-title">%s</span><strong>%s</strong></div><span class="card-meta">%s</span></article>',
                $severity,
                $this->escape($card['title']),
                $this->escape($card['primaryValue']),
                $this->escape($card['secondaryValue'])
            );
        }

        return $html;
    }

    /**
     * @param list<array{name: string, state: string, severity: string, allowedActions: list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>}> $services
     */
    private function renderServiceRows(array $services): string
    {
        $html = '';

        foreach ($services as $service) {
            $severity = $this->escape($service['severity']);
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td><span class="severity-badge severity-%s">%s</span></td><td>%s</td></tr>',
                $this->escape($service['name']),
                $this->escape($service['state']),
                $severity,
                $severity,
                $this->renderActionControls($service['allowedActions'], $service['name'], 'serviceName')
            );
        }

        return $html;
    }

    /**
     * @param list<array{name: string, image: string, state: string, severity: string, allowedActions: list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}>}> $containers
     */
    private function renderContainerRows(array $containers): string
    {
        $html = '';

        foreach ($containers as $container) {
            $severity = $this->escape($container['severity']);
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><span class="severity-badge severity-%s">%s</span></td><td>%s</td></tr>',
                $this->escape($container['name']),
                $this->escape($container['image']),
                $this->escape($container['state']),
                $severity,
                $severity,
                $this->renderActionControls($container['allowedActions'], $container['name'], 'containerName')
            );
        }

        return $html;
    }

    /**
     * @param list<array{actionKey: string, targetType: string, displayName: string, requiresConfirmation: bool, timeoutSeconds: int|null}> $actions
     */
    private function renderActionControls(array $actions, string $targetKey, string $parameterName): string
    {
        if ($actions === []) {
            return '<span class="muted-action">Sem acoes permitidas</span>';
        }

        $html = '<div class="action-stack">';

        foreach ($actions as $action) {
            $confirmation = $action['requiresConfirmation']
                ? '<label class="confirm-check"><input type="checkbox" name="confirmationAccepted" value="1" required> Confirmar</label>'
                : '<input type="hidden" name="confirmationAccepted" value="1">';
            $html .= sprintf(
                '<form class="action-form" method="post" action="/"><input type="hidden" name="requestId" value="%s"><input type="hidden" name="actionKey" value="%s"><input type="hidden" name="parameters[%s]" value="%s">%s<button type="submit">%s</button></form>',
                $this->escape($this->requestIdFor($action['actionKey'], $targetKey)),
                $this->escape($action['actionKey']),
                $this->escape($parameterName),
                $this->escape($targetKey),
                $confirmation,
                $this->escape($action['displayName'])
            );
        }

        return $html . '</div>';
    }

    /**
     * @param list<array{key: string, title: string, severity: string, items: list<array{label: string, value: string}>}> $sections
     */
    private function renderMonitoringSections(array $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            $severity = $this->escape($section['severity']);
            $items = '';

            foreach ($section['items'] as $item) {
                $items .= sprintf(
                    '<li><span>%s</span><strong>%s</strong></li>',
                    $this->escape($item['label']),
                    $this->escape($item['value'])
                );
            }

            $html .= sprintf(
                '<article class="detail-panel" id="%s"><div class="detail-panel-header"><h2>%s</h2><span class="severity-badge severity-%s">%s</span></div><ul>%s</ul></article>',
                $this->escape($section['key']),
                $this->escape($section['title']),
                $severity,
                $severity,
                $items
            );
        }

        return $html;
    }

    /**
     * @param list<array{source: string, title: string, severity: string}> $alerts
     */
    private function renderAlertRows(array $alerts): string
    {
        $html = '';

        foreach ($alerts as $alert) {
            $severity = $this->escape($alert['severity']);
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td><span class="severity-badge severity-%s">%s</span></td></tr>',
                $this->escape($alert['source']),
                $this->escape($alert['title']),
                $severity,
                $severity
            );
        }

        return $html;
    }

    /**
     * @param list<array{id: int, actor: string, actionKey: string, resultStatus: string, occurredAt: string}> $records
     */
    private function renderAuditRows(array $records): string
    {
        $html = '';

        foreach ($records as $record) {
            $status = $this->escape($record['resultStatus']);
            $html .= sprintf(
                '<tr><td>#%d</td><td>%s</td><td>%s</td><td><span class="result-badge result-%s">%s</span></td><td>%s</td></tr>',
                $record['id'],
                $this->escape($record['actor']),
                $this->escape($record['actionKey']),
                $status,
                $status,
                $this->escape($record['occurredAt'])
            );
        }

        return $html;
    }

    /**
     * @param list<array{id: int, source: string, title: string, severity: string, status: string}> $alerts
     */
    private function renderOperationalAlertRows(array $alerts): string
    {
        $html = '';

        foreach ($alerts as $alert) {
            $severity = $this->escape($alert['severity']);
            $html .= sprintf(
                '<tr><td>#%d</td><td>%s</td><td>%s</td><td><span class="severity-badge severity-%s">%s</span></td><td>%s</td><td>%s</td></tr>',
                $alert['id'],
                $this->escape($alert['source']),
                $this->escape($alert['title']),
                $severity,
                $severity,
                $this->escape($alert['status']),
                $this->renderAlertActions($alert['id'])
            );
        }

        return $html;
    }

    private function renderAlertActions(int $alertId): string
    {
        return sprintf(
            '<form class="action-form" method="post" action="/"><input type="hidden" name="requestId" value="%s"><input type="hidden" name="alertId" value="%d"><input type="text" name="acknowledgementNote" placeholder="Nota" aria-label="Nota de reconhecimento"><button type="submit">Reconhecer</button></form>',
            $this->escape($this->requestIdFor('alert.acknowledge', (string) $alertId)),
            $alertId
        );
    }

    /**
     * @param list<array{status: string, label: string}> $statuses
     */
    private function renderActionResultStatuses(array $statuses): string
    {
        $html = '';

        foreach ($statuses as $status) {
            $html .= sprintf(
                '<span class="result-chip result-%s"><strong>%s</strong> %s</span>',
                $this->escape($status['status']),
                $this->escape($status['status']),
                $this->escape($status['label'])
            );
        }

        return $html;
    }

    private function renderSubmissionFeedback(?WebSubmissionResult $submissionResult): string
    {
        if ($submissionResult === null) {
            return '';
        }

        $status = $this->escape($submissionResult->resultStatus);
        $audit = $submissionResult->auditRecordId === null
            ? 'sem auditoria'
            : sprintf('auditoria #%d', $submissionResult->auditRecordId);
        $exitCode = $submissionResult->exitCode === null
            ? 'exitCode n/a'
            : sprintf('exitCode %d', $submissionResult->exitCode);

        return sprintf(
            '<section class="submission-feedback result-%s" role="status" data-result-status="%s" data-request-id="%s"><strong>%s</strong><span>%s</span><small>%s | %s | requestId %s</small></section>',
            $status,
            $status,
            $this->escape($submissionResult->requestId),
            $status,
            $this->escape($submissionResult->message),
            $this->escape($audit),
            $this->escape($exitCode),
            $this->escape($submissionResult->requestId)
        );
    }

    private function requestIdFor(string $actionKey, string $targetKey): string
    {
        return hash('sha256', $actionKey . ':' . $targetKey . ':' . gmdate('YmdHi'));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
