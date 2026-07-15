<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use TPanel\Collectors\SystemDataSource;

final class FakeSystemDataSource implements SystemDataSource
{
    /**
     * @param array<string, string> $files
     * @param array<string, string> $commands
     */
    public function __construct(
        private readonly array $files = [],
        private readonly array $commands = [],
    ) {
    }

    public function readFile(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function runCommand(string $command, string ...$arguments): ?string
    {
        $key = implode(' ', array_merge([$command], $arguments));

        return $this->commands[$key] ?? null;
    }
}
