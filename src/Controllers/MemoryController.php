<?php

declare(strict_types=1);

namespace TPanel\Controllers;

use JsonException;
use TPanel\Security\AuthenticatedUser;
use TPanel\Services\MemoryLiveService;
use TPanel\Services\WebSubmissionResult;
use TPanel\Support\TemplateRenderer;

final class MemoryController
{
    public function __construct(
        private readonly MemoryLiveService $memoryLiveService = new MemoryLiveService(),
        private readonly TemplateRenderer $templates = new TemplateRenderer(),
    ) {
    }

    /**
     * @throws JsonException
     */
    public function index(AuthenticatedUser $actor, ?WebSubmissionResult $submissionResult = null): string
    {
        $snapshot = $this->memoryLiveService->snapshot();
        $json = json_encode(
            $snapshot,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
        $status = $this->escape((string) $snapshot['status']);
        $swapAction = $this->renderSwapAction($actor);
        $submissionFeedback = $this->renderSubmissionFeedback($submissionResult);

        $content = <<<HTML
            <section class="page-heading cpu-heading" data-memory-page>
                <div>
                    <p class="eyebrow">Análise de memória</p>
                    <h1>Memória em tempo real</h1>
                </div>
            </section>
            <section class="metric-grid cpu-kpis" aria-label="Resumo de memória">
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Uso RAM</span><strong data-memory-field="ramUsage">0%</strong></div>
                    <span class="card-meta">usado <b data-memory-field="ramUsed">0 B</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Disponível</span><strong data-memory-field="ramAvailable">0 B</strong></div>
                    <span class="card-meta">total <b data-memory-field="ramTotal">0 B</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Cache/Buffers</span><strong data-memory-field="cacheBuffers">0 B</strong></div>
                    <span class="card-meta">recuperavel <b data-memory-field="reclaimable">0 B</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Swap</span><strong data-memory-field="swapUsage">0%</strong></div>
                    <span class="card-meta">usado <b data-memory-field="swapUsed">0 B</b> | atividade <b data-memory-field="swapActivity">0 p/s</b></span>
                    {$swapAction}
                </article>
            </section>
            {$submissionFeedback}
            <section class="memory-status-panel panel" aria-label="Estado da memória">
                <div class="panel-header">
                    <h2>Estado operacional</h2>
                    <span>thresholds ativos</span>
                </div>
                <div class="memory-status-reasons" data-memory-status-reasons></div>
            </section>
            <section class="memory-composition panel" aria-label="Composição da memória">
                <div class="panel-header">
                    <h2>Composição da RAM</h2>
                    <span data-memory-field="collectedAt">Aguardando coleta</span>
                </div>
                <div class="memory-stack" data-memory-stack></div>
                <div class="memory-stack-legend">
                    <span><b class="legend-used"></b>Usada</span>
                    <span><b class="legend-cache"></b>Cache/Buffers</span>
                    <span><b class="legend-free"></b>Disponível</span>
                </div>
            </section>
            <section class="cpu-live-layout">
                <article class="panel cpu-chart-panel">
                    <div class="panel-header"><h2>Uso de RAM</h2><span>% usado</span></div>
                    <canvas class="cpu-chart" data-memory-chart="ram" aria-label="Gráfico live de uso de RAM"></canvas>
                </article>
                <article class="panel cpu-chart-panel">
                    <div class="panel-header"><h2>Swap</h2><span>% usado</span></div>
                    <canvas class="cpu-chart" data-memory-chart="swap" aria-label="Gráfico live de uso de swap"></canvas>
                </article>
                <article class="panel cpu-chart-panel">
                    <div class="panel-header"><h2>Pressão PSI</h2><span>some avg10</span></div>
                    <canvas class="cpu-chart" data-memory-chart="pressure" aria-label="Gráfico live de pressão de memória"></canvas>
                </article>
                <article class="panel cpu-chart-panel">
                    <div class="panel-header"><h2>Paginação</h2><span>page out MB/s</span></div>
                    <canvas class="cpu-chart" data-memory-chart="paging" aria-label="Gráfico live de paginação"></canvas>
                </article>
            </section>
            <section class="split-grid cpu-analysis-grid">
                <article class="panel">
                    <div class="panel-header">
                        <h2>Detalhes do kernel</h2>
                        <span>meminfo</span>
                    </div>
                    <div class="memory-detail-grid">
                        <div><span>Active</span><strong data-memory-field="active">0 B</strong></div>
                        <div><span>Inactive</span><strong data-memory-field="inactive">0 B</strong></div>
                        <div><span>AnonPages</span><strong data-memory-field="anon">0 B</strong></div>
                        <div><span>Mapped</span><strong data-memory-field="mapped">0 B</strong></div>
                        <div><span>Shmem</span><strong data-memory-field="shmem">0 B</strong></div>
                        <div><span>Slab</span><strong data-memory-field="slab">0 B</strong></div>
                        <div><span>Dirty</span><strong data-memory-field="dirty">0 B</strong></div>
                        <div><span>Writeback</span><strong data-memory-field="writeback">0 B</strong></div>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-header">
                        <h2>Processos por memória</h2>
                        <span>Top consumidores</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr><th>PID</th><th>Usuário</th><th>Comando</th><th>RAM</th><th>CPU</th></tr>
                            </thead>
                            <tbody data-memory-processes></tbody>
                        </table>
                    </div>
                </article>
            </section>
            <section class="panel memory-inventory-panel">
                <div class="panel-header">
                    <h2>Inventário físico</h2>
                    <span data-memory-field="inventorySummary">SMBIOS</span>
                </div>
                <div class="memory-inventory-summary">
                    <div><span>Capacidade máxima</span><strong data-memory-field="maximumCapacity">n/a</strong></div>
                    <div><span>Slots</span><strong data-memory-field="slotSummary">n/a</strong></div>
                    <div><span>ECC</span><strong data-memory-field="eccType">n/a</strong></div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Slot</th><th>Tamanho</th><th>Tipo</th><th>Velocidade</th><th>Configurada</th><th>Rank</th><th>Part number</th><th>Serial</th></tr>
                        </thead>
                        <tbody data-memory-inventory></tbody>
                    </table>
                </div>
            </section>
            <script type="application/json" id="memory-initial-data">{$json}</script>
        HTML;

        return $this->templates->render('layouts/app.php', [
            'activeNav' => 'memory',
            'content' => $content,
            'currentUser' => [
                'username' => $actor->externalUsername(),
                'role' => $actor->role()->roleName(),
            ],
            'name' => 'TPanel',
            'title' => 'Memória - Turin Panel',
        ]);
    }

    /**
     * @throws JsonException
     */
    public function live(): string
    {
        return json_encode($this->memoryLiveService->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function renderSwapAction(AuthenticatedUser $actor): string
    {
        if (!$actor->role()->canRunAdministrativeAction()) {
            return '<span class="memory-swap-action-muted">Somente administrador</span>';
        }

        return sprintf(
            '<form class="memory-swap-action" method="post" action="/memory"><input type="hidden" name="requestId" value="%s"><input type="hidden" name="actionKey" value="memory.swap.reload"><label><input type="checkbox" name="confirmationAccepted" value="1" required> Confirmar</label><button type="submit">Recarregar swap</button></form>',
            $this->escape($this->requestIdFor('memory.swap.reload', 'swap')),
        );
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
            $this->escape($submissionResult->requestId),
        );
    }

    private function requestIdFor(string $actionKey, string $targetKey): string
    {
        return hash('sha256', $actionKey . ':' . $targetKey . ':' . gmdate('YmdHi'));
    }
}
