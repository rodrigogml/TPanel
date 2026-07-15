<?php

declare(strict_types=1);

use TPanel\Support\Application;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'TPanel dependencies are not installed.';
    exit;
}

require $autoloadPath;

$application = new Application();

echo $application->handle();
