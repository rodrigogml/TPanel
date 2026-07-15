<?php

declare(strict_types=1);

namespace TPanel\Controllers;

use JsonException;
use TPanel\Security\AuthenticatedUser;
use TPanel\Services\NetworkLiveService;
use TPanel\Support\TemplateRenderer;

final class NetworkController
{
    public function __construct(
        private readonly NetworkLiveService $networkLiveService = new NetworkLiveService(),
        private readonly TemplateRenderer $templates = new TemplateRenderer(),
    ) {
    }

    /**
     * @throws JsonException
     */
    public function index(AuthenticatedUser $actor): string
    {
        $snapshot = $this->networkLiveService->snapshot();
        $json = json_encode(
            $snapshot,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
        $firewall = is_array($snapshot['firewall']) ? $snapshot['firewall'] : [];
        $firewallNotice = $this->renderFirewallNotice($firewall);
        $capabilityNotice = $this->renderCapabilityNotice((array) ($snapshot['capabilities'] ?? []));

        $content = <<<HTML
            <section class="page-heading cpu-heading" data-network-page>
                <div>
                    <p class="eyebrow">Controle de rede</p>
                    <h1>Rede em tempo real</h1>
                </div>
            </section>
            {$firewallNotice}
            {$capabilityNotice}
            <section class="metric-grid cpu-kpis" aria-label="Resumo de rede">
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Entrada</span><strong data-network-field="rxRate">0 B/s</strong></div>
                    <span class="card-meta">interfaces ativas <b data-network-field="activeInterfaces">0</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Saída</span><strong data-network-field="txRate">0 B/s</strong></div>
                    <span class="card-meta">interfaces totais <b data-network-field="interfaceCount">0</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Listeners</span><strong data-network-field="listenerCount">0</strong></div>
                    <span class="card-meta">públicos <b data-network-field="publicListenerCount">0</b></span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Conexões</span><strong data-network-field="connectionCount">0</strong></div>
                    <span class="card-meta">firewall <b data-network-field="firewallState">n/a</b></span>
                </article>
            </section>
            <section class="network-status-panel panel" aria-label="Estado da rede">
                <div class="panel-header">
                    <h2>Estado operacional</h2>
                    <span data-network-field="collectedAt">Aguardando coleta</span>
                </div>
                <div class="memory-status-reasons" data-network-status-reasons></div>
            </section>
            <section class="cpu-live-layout">
                <article class="panel cpu-chart-panel">
                    <div class="panel-header"><h2>Tráfego de entrada</h2><span>B/s</span></div>
                    <canvas class="cpu-chart" data-network-chart="rx" aria-label="Gráfico live de bytes recebidos por segundo"></canvas>
                </article>
                <article class="panel cpu-chart-panel">
                    <div class="panel-header"><h2>Tráfego de saída</h2><span>B/s</span></div>
                    <canvas class="cpu-chart" data-network-chart="tx" aria-label="Gráfico live de bytes enviados por segundo"></canvas>
                </article>
            </section>
            <section class="panel network-interface-panel">
                <div class="panel-header">
                    <h2>Interfaces</h2>
                    <span>endereços, MTU, erros e taxa</span>
                </div>
                <div class="network-interface-grid" data-network-interfaces></div>
            </section>
            <section class="split-grid cpu-analysis-grid">
                <article class="panel">
                    <div class="panel-header">
                        <h2>Portas escutando</h2>
                        <span>ss -tunlp</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Proto</th><th>Porta</th><th>Bind</th><th>Escopo</th><th>Processo</th><th>Firewall</th></tr></thead>
                            <tbody data-network-listeners></tbody>
                        </table>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-header">
                        <h2>Aplicações e conexões</h2>
                        <span>filas e sockets ativos</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>PID</th><th>Processo</th><th>Listeners</th><th>Conexões</th><th>Fila</th><th>Portas</th></tr></thead>
                            <tbody data-network-applications></tbody>
                        </table>
                    </div>
                </article>
            </section>
            <section class="split-grid cpu-analysis-grid">
                <article class="panel">
                    <div class="panel-header">
                        <h2>Conexões estabelecidas</h2>
                        <span>maiores filas primeiro</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Proto</th><th>Local</th><th>Remoto</th><th>Fila RX</th><th>Fila TX</th><th>Processo</th></tr></thead>
                            <tbody data-network-connections></tbody>
                        </table>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-header">
                        <h2>Rotas e DNS</h2>
                        <span>gateway, redes e resolvers</span>
                    </div>
                    <div class="network-route-dns">
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>Destino</th><th>Gateway</th><th>Interface</th><th>Origem</th></tr></thead>
                                <tbody data-network-routes></tbody>
                            </table>
                        </div>
                        <div class="network-dns-list" data-network-dns></div>
                    </div>
                </article>
            </section>
            <script type="application/json" id="network-initial-data">{$json}</script>
        HTML;

        return $this->templates->render('layouts/app.php', [
            'activeNav' => 'network',
            'content' => $content,
            'currentUser' => [
                'username' => $actor->externalUsername(),
                'role' => $actor->role()->roleName(),
            ],
            'name' => 'TPanel',
            'title' => 'Rede - Turin Panel',
        ]);
    }

    /**
     * @throws JsonException
     */
    public function live(): string
    {
        return json_encode($this->networkLiveService->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $firewall
     */
    private function renderFirewallNotice(array $firewall): string
    {
        if ((bool) ($firewall['available'] ?? false)) {
            return '';
        }

        return sprintf(
            '<section class="disk-smart-panel panel severity-left-WARNING"><div><span class="severity-badge severity-WARNING">Firewall não verificado</span><h2>Correlação de firewall indisponível</h2><p>%s</p></div></section>',
            $this->escape((string) ($firewall['warning'] ?? 'Firewall local não detectado.')),
        );
    }

    /**
     * @param array<string, mixed> $capabilities
     */
    private function renderCapabilityNotice(array $capabilities): string
    {
        if ((bool) ($capabilities['perProcessBandwidth'] ?? false)) {
            return '';
        }

        return sprintf(
            '<section class="disk-smart-panel panel severity-left-OK"><div><span class="severity-badge severity-OK">Banda por processo</span><h2>Processos por conexões</h2><p>%s</p></div></section>',
            $this->escape((string) ($capabilities['perProcessBandwidthMessage'] ?? 'Medição de bytes por processo requer coletor dedicado.')),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
