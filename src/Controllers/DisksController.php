<?php

declare(strict_types=1);

namespace TPanel\Controllers;

use TPanel\Security\AuthenticatedUser;
use TPanel\Services\DiskOverviewService;
use TPanel\Support\TemplateRenderer;

final class DisksController
{
    public function __construct(
        private readonly DiskOverviewService $diskOverviewService = new DiskOverviewService(),
        private readonly TemplateRenderer $templates = new TemplateRenderer(),
    ) {
    }

    public function index(AuthenticatedUser $actor): string
    {
        $snapshot = $this->diskOverviewService->snapshot();
        $smartctl = is_array($snapshot['smartctl']) ? $snapshot['smartctl'] : [];
        $blockDevices = is_array($snapshot['blockDevices']) ? $snapshot['blockDevices'] : [];
        $mounts = is_array($snapshot['mounts']) ? $snapshot['mounts'] : [];
        $fstab = is_array($snapshot['fstab']) ? $snapshot['fstab'] : [];
        $smartctlAvailable = (bool) ($smartctl['available'] ?? false);
        $smartctlStatus = $smartctlAvailable ? 'Disponível' : 'Indisponível';
        $smartctlSeverity = $smartctlAvailable ? 'OK' : 'WARNING';
        $smartctlPanel = $smartctlAvailable ? '' : sprintf(
            <<<HTML
            <section class="disk-smart-panel panel severity-left-WARNING">
                <div>
                    <span class="severity-badge severity-WARNING">SMART Indisponível</span>
                    <h2>Dependência smartctl</h2>
                    <p>%s</p>
                </div>
            </section>
            HTML,
            $this->escape((string) ($smartctl['warning'] ?? 'smartctl não detectado.')),
        );

        $content = sprintf(
            <<<HTML
            <section class="page-heading cpu-heading" data-disks-page>
                <div>
                    <p class="eyebrow">Armazenamento</p>
                    <h1>Discos e armazenamento</h1>
                </div>
            </section>
            %s
            <section class="metric-grid cpu-kpis" aria-label="Resumo de armazenamento">
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Dispositivos</span><strong>%d</strong></div>
                    <span class="card-meta">blocos e partições detectados</span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">Montagens</span><strong>%d</strong></div>
                    <span class="card-meta">filesystems ativos via findmnt</span>
                </article>
                <article class="metric-card severity-left-OK">
                    <div><span class="card-title">fstab</span><strong>%d</strong></div>
                    <span class="card-meta">entradas de montagem automática</span>
                </article>
                <article class="metric-card severity-left-%s">
                    <div><span class="card-title">SMART</span><strong>%s</strong></div>
                    <span class="card-meta">coleta física de discos</span>
                </article>
            </section>
            <section class="panel disk-panel">
                <div class="panel-header">
                    <h2>Discos e partições</h2>
                    <span>lsblk</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Dispositivo</th><th>Tipo</th><th>Tamanho</th><th>Modelo</th><th>Transporte</th><th>Montagem</th><th>Filesystem</th></tr></thead>
                        <tbody>%s</tbody>
                    </table>
                </div>
            </section>
            <section class="panel disk-panel">
                <div class="panel-header">
                    <h2>Montagens ativas</h2>
                    <span>findmnt</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Destino</th><th>Origem</th><th>Tipo</th><th>Tamanho</th><th>Usado</th><th>Livre</th><th>Uso</th><th>Opções</th></tr></thead>
                        <tbody>%s</tbody>
                    </table>
                </div>
            </section>
            <section class="panel disk-panel">
                <div class="panel-header">
                    <h2>Montagens automáticas</h2>
                    <span>/etc/fstab</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Origem</th><th>Destino</th><th>Tipo</th><th>Opções</th><th>Dump</th><th>Pass</th></tr></thead>
                        <tbody>%s</tbody>
                    </table>
                </div>
            </section>
            HTML,
            $smartctlPanel,
            count($blockDevices),
            count($mounts),
            count($fstab),
            $smartctlSeverity,
            $this->escape($smartctlStatus),
            $this->blockDeviceRows($blockDevices),
            $this->mountRows($mounts),
            $this->fstabRows($fstab),
        );

        return $this->templates->render('layouts/app.php', [
            'activeNav' => 'disks',
            'content' => $content,
            'currentUser' => [
                'username' => $actor->externalUsername(),
                'role' => $actor->role()->roleName(),
            ],
            'name' => 'TPanel',
            'title' => 'Discos - Turin Panel',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $devices
     */
    private function blockDeviceRows(array $devices): string
    {
        if ($devices === []) {
            return '<tr><td colspan="7">Nenhum dispositivo retornado pelo lsblk.</td></tr>';
        }

        return implode('', array_map(function (array $device): string {
            $mountpoints = $device['mountpoints'] ?? [];
            $mountText = is_array($mountpoints) && $mountpoints !== [] ? implode(', ', $mountpoints) : 'n/a';
            $label = (string) ($device['path'] ?? $device['name'] ?? 'n/a');
            $depth = max(0, min(6, (int) ($device['depth'] ?? 0)));

            return sprintf(
                '<tr><td><span class="disk-device-label disk-device-depth-%d%s">%s</span></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $depth,
                $depth > 0 ? ' is-child' : '',
                $this->escape($label),
                $this->escape((string) ($device['type'] ?? 'n/a')),
                $this->escape($this->formatBytes($device['size'] ?? null)),
                $this->escape((string) ($device['model'] ?? 'n/a')),
                $this->escape((string) ($device['tran'] ?? 'n/a')),
                $this->escape($mountText),
                $this->escape((string) ($device['fstype'] ?? 'n/a')),
            );
        }, $devices));
    }

    /**
     * @param list<array<string, mixed>> $mounts
     */
    private function mountRows(array $mounts): string
    {
        if ($mounts === []) {
            return '<tr><td colspan="8">Nenhuma montagem retornada pelo findmnt.</td></tr>';
        }

        return implode('', array_map(fn (array $mount): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="disk-options-cell">%s</td></tr>',
            $this->escape((string) ($mount['target'] ?? 'n/a')),
            $this->escape((string) ($mount['source'] ?? 'n/a')),
            $this->escape((string) ($mount['fstype'] ?? 'n/a')),
            $this->escape($this->formatBytes($mount['size'] ?? null)),
            $this->escape($this->formatBytes($mount['used'] ?? null)),
            $this->escape($this->formatBytes($mount['avail'] ?? null)),
            $this->renderUsageGauge($mount['use%'] ?? null),
            $this->escape((string) ($mount['options'] ?? 'n/a')),
        ), $mounts));
    }

    /**
     * @param list<array<string, string>> $entries
     */
    private function fstabRows(array $entries): string
    {
        if ($entries === []) {
            return '<tr><td colspan="6">Nenhuma entrada ativa em /etc/fstab.</td></tr>';
        }

        return implode('', array_map(fn (array $entry): string => sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td class="disk-options-cell">%s</td><td>%s</td><td>%s</td></tr>',
            $this->escape($entry['source']),
            $this->escape($entry['target']),
            $this->escape($entry['fstype']),
            $this->escape($entry['options']),
            $this->escape($entry['dump']),
            $this->escape($entry['pass']),
        ), $entries));
    }

    private function formatBytes(mixed $bytes): string
    {
        if (!is_numeric($bytes)) {
            return 'n/a';
        }

        $value = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'PB') {
                return sprintf($value >= 10 || $unit === 'B' ? '%.0f %s' : '%.1f %s', $value, $unit);
            }

            $value /= 1024;
        }

        return 'n/a';
    }

    private function renderUsageGauge(mixed $usage): string
    {
        $percent = $this->parsePercent($usage);

        if ($percent === null) {
            return '<span class="disk-usage-cell"><span>n/a</span></span>';
        }

        $severity = $percent > 75.0 ? 'critical' : ($percent > 50.0 ? 'warning' : 'ok');

        return sprintf(
            '<span class="disk-usage-cell"><span>%s</span><span class="disk-usage-gauge disk-usage-%s" style="--disk-usage: %.1f%%" aria-label="Ocupação %.1f%%"></span></span>',
            $this->escape(sprintf('%.0f%%', $percent)),
            $severity,
            $percent,
            $percent,
        );
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
