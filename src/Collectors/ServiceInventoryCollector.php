<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class ServiceInventoryCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): ServiceInventorySnapshot
    {
        $output = $this->dataSource->runCommand(
            '/usr/bin/systemctl',
            'list-units',
            '--type=service',
            '--all',
            '--no-legend',
            '--no-pager'
        );
        $services = $output === null ? [] : $this->parseSystemctlServices($output);

        return new ServiceInventorySnapshot(
            available: $output !== null,
            services: $services,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<array{name: string, loadState: string, activeState: string, subState: string, description: string}>
     */
    private function parseSystemctlServices(string $output): array
    {
        $services = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 5) ?: [];

            if (count($parts) < 4 || !str_ends_with($parts[0], '.service')) {
                continue;
            }

            $services[] = [
                'name' => $parts[0],
                'loadState' => strtoupper($parts[1]),
                'activeState' => strtoupper($parts[2]),
                'subState' => strtoupper($parts[3]),
                'description' => $parts[4] ?? '',
            ];
        }

        return $services;
    }
}
