<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class NetworkCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): NetworkSnapshot
    {
        $traffic = $this->parseNetDev($this->dataSource->readFile('/proc/net/dev') ?? '');
        $ips = $this->parseIpAddress($this->dataSource->runCommand('/usr/sbin/ip', '-o', 'addr', 'show') ?? '');
        $interfaces = [];

        foreach (array_unique(array_merge(array_keys($traffic), array_keys($ips))) as $name) {
            if ($name === 'lo') {
                continue;
            }

            $interfaces[] = [
                'name' => $name,
                'ips' => $ips[$name] ?? [],
                'rxBytes' => $traffic[$name]['rxBytes'] ?? null,
                'txBytes' => $traffic[$name]['txBytes'] ?? null,
                'rxErrors' => $traffic[$name]['rxErrors'] ?? null,
                'txErrors' => $traffic[$name]['txErrors'] ?? null,
            ];
        }

        return new NetworkSnapshot(
            available: $interfaces !== [],
            interfaces: $interfaces,
            gateway: $this->parseGateway($this->dataSource->runCommand('/usr/sbin/ip', 'route', 'show', 'default') ?? ''),
            dnsServers: $this->parseDnsServers($this->dataSource->readFile('/etc/resolv.conf') ?? ''),
            latencyMs: $this->parsePingLatency($this->dataSource->runCommand('/bin/ping', '-c', '1', '-W', '1', '1.1.1.1')),
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, array{rxBytes: int, rxErrors: int, txBytes: int, txErrors: int}>
     */
    private function parseNetDev(string $content): array
    {
        $interfaces = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $stats] = explode(':', $line, 2);
            $parts = preg_split('/\s+/', trim($stats)) ?: [];

            if (count($parts) < 16) {
                continue;
            }

            $interfaces[trim($name)] = [
                'rxBytes' => (int) $parts[0],
                'rxErrors' => (int) $parts[2],
                'txBytes' => (int) $parts[8],
                'txErrors' => (int) $parts[10],
            ];
        }

        return $interfaces;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseIpAddress(string $output): array
    {
        $addresses = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (preg_match('/^\d+:\s+([^ ]+)\s+(inet6?)\s+([^ ]+)/', trim($line), $matches) !== 1) {
                continue;
            }

            $addresses[$matches[1]][] = $matches[3];
        }

        return $addresses;
    }

    private function parseGateway(string $output): ?string
    {
        return preg_match('/default\s+via\s+([^ ]+)/', $output, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @return list<string>
     */
    private function parseDnsServers(string $content): array
    {
        $servers = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (preg_match('/^\s*nameserver\s+([^#\s]+)/', $line, $matches) === 1) {
                $servers[] = $matches[1];
            }
        }

        return $servers;
    }

    private function parsePingLatency(?string $output): ?float
    {
        if ($output === null) {
            return null;
        }

        return preg_match('/time=([0-9.]+)\s*ms/', $output, $matches) === 1 ? (float) $matches[1] : null;
    }
}
