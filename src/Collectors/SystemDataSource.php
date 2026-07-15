<?php

declare(strict_types=1);

namespace TPanel\Collectors;

interface SystemDataSource
{
    public function readFile(string $path): ?string;

    public function runCommand(string $command, string ...$arguments): ?string;
}
