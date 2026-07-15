<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var array{username: string, role: string} $currentUser
 * @var array{total: int, warning: int, critical: int, pages: array<string, array{warning: int, critical: int, total: int}>, alerts: list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>} $navigationAlerts
 */
$alertsJson = json_encode($navigationAlerts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<header class="topbar">
    <button class="icon-button menu-button" type="button" data-menu-toggle aria-label="Abrir menu">☰</button>
    <label class="search-box">
        <span class="search-icon" aria-hidden="true">⌕</span>
        <input type="search" placeholder="Pesquisar" aria-label="Pesquisar">
    </label>
    <div class="topbar-actions">
        <div class="live-controls" data-live-controls hidden>
            <label class="live-refresh-control">
                <span>Atualização</span>
                <select data-live-refresh aria-label="Intervalo de atualização live">
                    <option value="2000">2s</option>
                    <option value="5000">5s</option>
                    <option value="10000">10s</option>
                    <option value="30000">30s</option>
                </select>
            </label>
            <button class="icon-button live-pause-button" type="button" data-live-pause aria-label="Pausar atualização">Ⅱ</button>
            <span class="refresh-clock" data-live-countdown aria-label="Tempo até a próxima atualização">
                <span>5s</span>
            </span>
        </div>
        <button class="icon-button" type="button" data-theme-toggle aria-label="Alternar tema">◐</button>
        <button class="alert-button" type="button" data-alerts-button aria-label="Alertas">Alertas <strong data-alerts-count><?= (int) $navigationAlerts['total'] ?></strong></button>
        <div class="user-chip">
            <span><?= $e($currentUser['username']) ?></span>
            <strong><?= $e($currentUser['role']) ?></strong>
        </div>
    </div>
    <dialog class="alerts-dialog" data-alerts-dialog>
        <div class="dialog-panel">
            <div class="panel-header">
                <h2>Alertas</h2>
                <form method="dialog"><button class="icon-button" type="submit" aria-label="Fechar">×</button></form>
            </div>
            <div class="alerts-dialog-body" data-alerts-dialog-body></div>
        </div>
    </dialog>
    <script type="application/json" id="navigation-alerts-data"><?= $alertsJson ?></script>
</header>
