<?php

declare(strict_types=1);

namespace TPanel\Collectors;

final class LocalSystemDataSource implements SystemDataSource
{
    public function readFile(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }

    public function runCommand(string $command, string ...$arguments): ?string
    {
        $process = proc_open(
            array_merge([$command], $arguments),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_string($stdout)) {
            return null;
        }

        return $stdout;
    }
}
