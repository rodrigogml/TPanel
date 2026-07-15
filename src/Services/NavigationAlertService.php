<?php

declare(strict_types=1);

namespace TPanel\Services;

use Throwable;

final class NavigationAlertService
{
    /**
     * @return array{
     *     total: int,
     *     warning: int,
     *     critical: int,
     *     pages: array<string, array{warning: int, critical: int, total: int}>,
     *     alerts: list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     * }
     */
    public function summary(): array
    {
        $alerts = [];
        array_push($alerts, ...$this->overviewAlerts());
        array_push($alerts, ...$this->cpuAlerts());
        array_push($alerts, ...$this->memoryAlerts());
        array_push($alerts, ...$this->diskAlerts());

        return $this->shape($alerts);
    }

    /**
     * @return list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     */
    private function overviewAlerts(): array
    {
        try {
            $summary = (new DashboardService())->summary();
        } catch (Throwable) {
            return [];
        }

        $alerts = [];

        foreach ($summary['operationalAlerts'] ?? [] as $alert) {
            if (!is_array($alert) || ($alert['status'] ?? '') === 'RESOLVED') {
                continue;
            }

            $alerts[] = [
                'pageKey' => 'overview',
                'pageLabel' => 'Visão Geral',
                'href' => '/#alerts',
                'severity' => $this->normalizeSeverity((string) ($alert['severity'] ?? 'WARNING')),
                'title' => (string) ($alert['title'] ?? 'Alerta operacional'),
                'detail' => sprintf('%s | %s', (string) ($alert['source'] ?? 'sistema'), (string) ($alert['status'] ?? 'OPEN')),
            ];
        }

        return $alerts;
    }

    /**
     * @return list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     */
    private function cpuAlerts(): array
    {
        try {
            $snapshot = (new CpuLiveService())->snapshot(deltaMicroseconds: 0);
        } catch (Throwable) {
            return [[
                'pageKey' => 'cpu',
                'pageLabel' => 'CPU',
                'href' => '/cpu',
                'severity' => 'WARNING',
                'title' => 'Coleta de CPU indisponível',
                'detail' => 'Não foi possível avaliar os thresholds de CPU.',
            ]];
        }

        return $this->thresholdAlerts('cpu', 'CPU', '/cpu', $snapshot['statusReasons'] ?? []);
    }

    /**
     * @return list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     */
    private function memoryAlerts(): array
    {
        try {
            $snapshot = (new MemoryLiveService())->snapshot(deltaMicroseconds: 0);
        } catch (Throwable) {
            return [[
                'pageKey' => 'memory',
                'pageLabel' => 'Memória',
                'href' => '/memory',
                'severity' => 'WARNING',
                'title' => 'Coleta de memória indisponível',
                'detail' => 'Não foi possível avaliar os thresholds de memória.',
            ]];
        }

        return $this->thresholdAlerts('memory', 'Memória', '/memory', $snapshot['statusReasons'] ?? []);
    }

    /**
     * @return list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     */
    private function diskAlerts(): array
    {
        try {
            $snapshot = (new DiskOverviewService())->snapshot();
        } catch (Throwable) {
            return [[
                'pageKey' => 'disks',
                'pageLabel' => 'Discos',
                'href' => '/disks',
                'severity' => 'WARNING',
                'title' => 'Coleta de discos indisponível',
                'detail' => 'Não foi possível avaliar o armazenamento.',
            ]];
        }

        $smartctl = is_array($snapshot['smartctl'] ?? null) ? $snapshot['smartctl'] : [];

        if ((bool) ($smartctl['available'] ?? false)) {
            return [];
        }

        return [[
            'pageKey' => 'disks',
            'pageLabel' => 'Discos',
            'href' => '/disks',
            'severity' => 'WARNING',
            'title' => 'smartctl indisponível',
            'detail' => (string) ($smartctl['warning'] ?? 'Instale smartmontools para liberar a coleta SMART.'),
        ]];
    }

    /**
     * @param mixed $reasons
     * @return list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     */
    private function thresholdAlerts(string $pageKey, string $pageLabel, string $href, mixed $reasons): array
    {
        if (!is_array($reasons)) {
            return [];
        }

        $alerts = [];

        foreach ($reasons as $reason) {
            if (!is_array($reason)) {
                continue;
            }

            $alerts[] = [
                'pageKey' => $pageKey,
                'pageLabel' => $pageLabel,
                'href' => $href,
                'severity' => $this->normalizeSeverity((string) ($reason['severity'] ?? 'WARNING')),
                'title' => (string) ($reason['label'] ?? 'Threshold ativo'),
                'detail' => (string) ($reason['value'] ?? ''),
            ];
        }

        return $alerts;
    }

    /**
     * @param list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}> $alerts
     * @return array{
     *     total: int,
     *     warning: int,
     *     critical: int,
     *     pages: array<string, array{warning: int, critical: int, total: int}>,
     *     alerts: list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>
     * }
     */
    private function shape(array $alerts): array
    {
        $summary = [
            'total' => count($alerts),
            'warning' => 0,
            'critical' => 0,
            'pages' => [],
            'alerts' => $alerts,
        ];

        foreach ($alerts as $alert) {
            $pageKey = $alert['pageKey'];
            $severity = $this->normalizeSeverity($alert['severity']);
            $summary['pages'][$pageKey] ??= ['warning' => 0, 'critical' => 0, 'total' => 0];
            $summary['pages'][$pageKey]['total']++;

            if ($severity === 'CRITICAL') {
                $summary['critical']++;
                $summary['pages'][$pageKey]['critical']++;
            } else {
                $summary['warning']++;
                $summary['pages'][$pageKey]['warning']++;
            }
        }

        return $summary;
    }

    private function normalizeSeverity(string $severity): string
    {
        return strtoupper($severity) === 'CRITICAL' ? 'CRITICAL' : 'WARNING';
    }
}
