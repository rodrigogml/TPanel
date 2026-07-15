<?php

declare(strict_types=1);

namespace TPanel\Support;

use RuntimeException;
use Throwable;
use TPanel\Services\NavigationAlertService;

final class TemplateRenderer
{
    public function __construct(
        private readonly string $basePath = __DIR__ . '/../../templates',
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $template, array $variables = []): string
    {
        if ($template === 'layouts/app.php' && !array_key_exists('navigationAlerts', $variables)) {
            $variables['navigationAlerts'] = $this->navigationAlerts();
        }

        $path = $this->pathFor($template);

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template not found: %s', $template));
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $render = fn (string $partial, array $partialVariables = []): string => $this->render(
            $partial,
            array_replace($variables, $partialVariables),
        );

        extract($variables, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    private function pathFor(string $template): string
    {
        $normalized = ltrim(str_replace('\\', '/', $template), '/');

        return rtrim($this->basePath, '/') . '/' . $normalized;
    }

    /**
     * @return array{total: int, warning: int, critical: int, pages: array<string, array{warning: int, critical: int, total: int}>, alerts: list<array{pageKey: string, pageLabel: string, href: string, severity: string, title: string, detail: string}>}
     */
    private function navigationAlerts(): array
    {
        try {
            return (new NavigationAlertService())->summary();
        } catch (Throwable) {
            return [
                'total' => 0,
                'warning' => 0,
                'critical' => 0,
                'pages' => [],
                'alerts' => [],
            ];
        }
    }
}
