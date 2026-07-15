<?php

declare(strict_types=1);

/**
 * @var callable $e
 * @var callable $render
 * @var string $activeNav
 * @var string $content
 * @var array{username: string, role: string} $currentUser
 * @var string $name
 * @var array{total: int, warning: int, critical: int, pages: array<string, array{warning: int, critical: int, total: int}>, alerts: list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>} $navigationAlerts
 * @var string $title
 */
?>
<!doctype html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?></title>
    <link rel="stylesheet" href="/assets/css/tpanel.css">
    <script src="/assets/js/tpanel.js" defer></script>
</head>
<body>
    <div class="app-shell" data-shell>
        <?= $render('partials/sidebar.php', ['activeNav' => $activeNav, 'name' => $name, 'navigationAlerts' => $navigationAlerts]) ?>
        <div class="workspace">
            <?= $render('partials/topbar.php', ['currentUser' => $currentUser, 'navigationAlerts' => $navigationAlerts]) ?>
            <main class="content" id="top">
                <?= $content ?>
            </main>
        </div>
    </div>
</body>
</html>
