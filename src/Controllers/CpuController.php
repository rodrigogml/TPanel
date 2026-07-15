<?php

declare(strict_types=1);

namespace TPanel\Controllers;

use JsonException;
use TPanel\Security\AuthenticatedUser;
use TPanel\Services\CpuLiveService;
use TPanel\Support\TemplateRenderer;

final class CpuController
{
    public function __construct(
        private readonly CpuLiveService $cpuLiveService = new CpuLiveService(),
        private readonly TemplateRenderer $templates = new TemplateRenderer(),
    ) {
    }

    /**
     * @throws JsonException
     */
    public function index(AuthenticatedUser $actor): string
    {
        $snapshot = $this->cpuLiveService->snapshot();
        $json = json_encode(
            $snapshot,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
        $modelName = $this->escape((string) $snapshot['identity']['modelName']);
        $vendor = $this->escape((string) $snapshot['identity']['vendor']);
        $logicalThreads = (int) $snapshot['identity']['logicalThreads'];
        $physicalCores = (int) $snapshot['identity']['physicalCores'];
        $sockets = (int) $snapshot['identity']['sockets'];
        $threadsPerCore = (int) $snapshot['identity']['threadsPerCore'];
        $features = $this->escape(implode(', ', $snapshot['identity']['features']));
        $governor = $this->escape((string) ($snapshot['governor'] ?? 'n/a'));
        $status = $this->escape((string) $snapshot['status']);

        $content = <<<HTML
            <section class="page-heading cpu-heading" data-cpu-page>
                <div>
                    <p class="eyebrow">Análise de CPU</p>
                    <h1>CPU em tempo real</h1>
                </div>
            </section>
            <section class="cpu-identity-panel">
                <div>
                    <span>Processador</span>
                    <strong>{$modelName}</strong>
                    <small>{$vendor} | {$sockets} socket(s) | {$physicalCores} core(s) fisicos | {$logicalThreads} threads | SMT {$threadsPerCore}:1</small>
                </div>
                <div>
                    <span>Recursos</span>
                    <strong>{$features}</strong>
                    <small>Governor: {$governor}</small>
                </div>
            </section>
            <section class="metric-grid cpu-kpis" aria-label="Resumo de CPU">
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Uso total</span><strong data-cpu-field="usage">0%</strong></div>
                    <span class="card-meta">user <b data-cpu-field="user">0%</b> | sys <b data-cpu-field="system">0%</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Load 1m</span><strong data-cpu-field="load">0.00</strong></div>
                    <span class="card-meta">normalizado <b data-cpu-field="normalizedLoad">0%</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Frequência</span><strong data-cpu-field="frequency">n/a</strong></div>
                    <span class="card-meta">min <b data-cpu-field="frequencyMin">n/a</b> | max <b data-cpu-field="frequencyMax">n/a</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Temperatura</span><strong data-cpu-field="temperature">n/a</strong></div>
                    <span class="card-meta">threads <b data-cpu-field="threads">{$logicalThreads}</b></span>
                </article>
            </section>
            <section class="cpu-live-layout">
                <article class="panel cpu-chart-panel">
                    <div class="panel-header">
                        <h2>Uso total</h2>
                        <span data-cpu-field="collectedAt">Aguardando coleta</span>
                    </div>
                    <canvas class="cpu-chart" id="cpu-total-chart" data-cpu-chart="usage" aria-label="Grafico live de uso total da CPU"></canvas>
                </article>
                <article class="panel cpu-chart-panel">
                    <div class="panel-header">
                        <h2>Load average</h2>
                        <span>1m / 5m / 15m</span>
                    </div>
                    <canvas class="cpu-chart" id="cpu-load-chart" data-cpu-chart="load" aria-label="Grafico live de load average"></canvas>
                </article>
            </section>
            <section class="split-grid cpu-analysis-grid">
                <article class="panel">
                    <div class="panel-header">
                        <h2>Núcleos e threads</h2>
                        <span>Uso instantaneo</span>
                    </div>
                    <div class="core-grid" data-cpu-cores></div>
                </article>
                <article class="panel">
                    <div class="panel-header">
                        <h2>Processos por CPU</h2>
                        <span>Top consumidores</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>PID</th><th>Usuário</th><th>Comando</th><th>CPU</th><th>RAM</th></tr>
                            </thead>
                            <tbody data-cpu-processes></tbody>
                        </table>
                    </div>
                </article>
            </section>
            <script type="application/json" id="cpu-initial-data">{$json}</script>
        HTML;

        return $this->templates->render('layouts/app.php', [
            'activeNav' => 'cpu',
            'content' => $content,
            'currentUser' => [
                'username' => $actor->externalUsername(),
                'role' => $actor->role()->roleName(),
            ],
            'name' => 'TPanel',
            'title' => 'CPU - Turin Panel',
        ]);
    }

    /**
     * @throws JsonException
     */
    public function live(): string
    {
        return json_encode($this->cpuLiveService->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
