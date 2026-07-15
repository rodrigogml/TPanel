<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class ProcessCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
        private readonly int $limit = 5,
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): ProcessSnapshot
    {
        $processes = $this->parsePs($this->dataSource->runCommand('/bin/ps', '-eo', 'pid,user,pcpu,pmem,comm', '--no-headers') ?? '');
        $topByCpu = $this->sortProcesses($processes, 'cpuPercent');
        $topByMemory = $this->sortProcesses($processes, 'memoryPercent');

        return new ProcessSnapshot(
            available: $processes !== [],
            topByCpu: array_slice($topByCpu, 0, $this->limit),
            topByMemory: array_slice($topByMemory, 0, $this->limit),
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<array{pid: int, user: string, command: string, cpuPercent: float, memoryPercent: float}>
     */
    private function parsePs(string $output): array
    {
        $processes = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim($line), 5) ?: [];

            if (count($parts) < 5 || !is_numeric($parts[0])) {
                continue;
            }

            $processes[] = [
                'pid' => (int) $parts[0],
                'user' => $parts[1],
                'cpuPercent' => (float) $parts[2],
                'memoryPercent' => (float) $parts[3],
                'command' => $parts[4],
            ];
        }

        return $processes;
    }

    /**
     * @param list<array{pid: int, user: string, command: string, cpuPercent: float, memoryPercent: float}> $processes
     * @return list<array{pid: int, user: string, command: string, cpuPercent: float, memoryPercent: float}>
     */
    private function sortProcesses(array $processes, string $field): array
    {
        usort(
            $processes,
            static fn (array $left, array $right): int => $right[$field] <=> $left[$field]
                ?: $left['pid'] <=> $right['pid']
        );

        return $processes;
    }
}
