<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class DockerInventoryCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): DockerInventorySnapshot
    {
        $output = $this->dataSource->runCommand(
            '/usr/bin/docker',
            'ps',
            '--all',
            '--format',
            '{{.Names}}\t{{.ID}}\t{{.Image}}\t{{.Status}}\t{{.State}}'
        );
        $containers = $output === null ? [] : $this->parseDockerContainers($output);

        return new DockerInventorySnapshot(
            available: $output !== null,
            dockerAvailable: $output !== null,
            containers: $containers,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<array{name: string, id: string, image: string, status: string, state: string}>
     */
    private function parseDockerContainers(string $output): array
    {
        $containers = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 5) {
                continue;
            }

            $containers[] = [
                'name' => $parts[0],
                'id' => $parts[1],
                'image' => $parts[2],
                'status' => $parts[3],
                'state' => strtoupper($parts[4]),
            ];
        }

        return $containers;
    }
}
