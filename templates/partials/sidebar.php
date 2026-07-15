<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var string $activeNav
 * @var string $name
 * @var array{total: int, warning: int, critical: int, pages: array<string, array{warning: int, critical: int, total: int}>, alerts: list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>} $navigationAlerts
 */

$items = [
    ['key' => 'overview', 'href' => '/', 'label' => 'Visão Geral'],
    ['key' => 'cpu', 'href' => '/cpu', 'label' => 'CPU'],
    ['key' => 'memory', 'href' => '/memory', 'label' => 'Memória'],
    ['key' => 'disks', 'href' => '/disks', 'label' => 'Discos'],
    ['key' => 'network', 'href' => '/network', 'label' => 'Rede'],
];
?>
<aside class="sidebar" aria-label="Navegação principal">
    <a class="brand" href="/" aria-label="<?= $e($name) ?>">
        <img class="brand-logo" src="/assets/images/tpanel-logo.png" alt="Turing Panel">
    </a>
    <nav class="nav-list">
        <?php foreach ($items as $item): ?>
            <?php $isActive = $activeNav === $item['key']; ?>
            <a class="nav-item<?= $isActive ? ' is-active' : '' ?>" href="<?= $e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <span><?= $e($item['label']) ?></span>
                <?php $counts = $navigationAlerts['pages'][$item['key']] ?? ['warning' => 0, 'critical' => 0, 'total' => 0]; ?>
                <span class="nav-alerts" data-nav-alerts-for="<?= $e($item['key']) ?>"<?= (int) $counts['total'] === 0 ? ' hidden' : '' ?>>
                    <b class="nav-alert-count nav-alert-critical" data-nav-alert-critical<?= (int) $counts['critical'] === 0 ? ' hidden' : '' ?>><?= (int) $counts['critical'] ?></b>
                    <b class="nav-alert-count nav-alert-warning" data-nav-alert-warning<?= (int) $counts['warning'] === 0 ? ' hidden' : '' ?>><?= (int) $counts['warning'] ?></b>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
