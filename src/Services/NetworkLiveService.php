<?php

declare(strict_types=1);

namespace TPanel\Services;

use JsonException;
use TPanel\Collectors\LocalSystemDataSource;
use TPanel\Collectors\SystemDataSource;

final class NetworkLiveService
{
    private const IP_COMMAND = '/usr/bin/ip';
    private const SS_COMMAND = '/usr/bin/ss';
    private const SUDO_COMMAND = '/usr/bin/sudo';

    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $deltaMicroseconds = 180000): array
    {
        $firstStats = $this->readInterfaceStats();

        if ($deltaMicroseconds > 0) {
            usleep($deltaMicroseconds);
        }

        $secondStats = $this->readInterfaceStats();
        $interfaces = $this->interfaces($firstStats, $secondStats, max(0.001, $deltaMicroseconds / 1_000_000));
        $listeners = $this->listeners();
        $connections = $this->connections();
        $firewall = $this->firewallSnapshot();
        $routes = $this->routes();
        $dnsServers = $this->dnsServers();
        $statusReasons = $this->statusReasons($interfaces, $listeners, $firewall);

        return [
            'collectedAt' => gmdate('c'),
            'status' => $this->statusFor($statusReasons),
            'statusReasons' => $statusReasons,
            'summary' => $this->summary($interfaces, $listeners, $connections, $firewall),
            'interfaces' => $interfaces,
            'routes' => $routes,
            'dnsServers' => $dnsServers,
            'listeners' => $this->annotateListeners($listeners, $firewall),
            'connections' => $connections,
            'topApplications' => $this->topApplications($listeners, $connections),
            'firewall' => $firewall,
            'capabilities' => [
                'perProcessBandwidth' => false,
                'perProcessBandwidthMessage' => 'Medição de bytes por processo requer coletor dedicado como nethogs, eBPF ou conntrack enriquecido. O painel atual mostra conexões, filas e listeners por processo.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function readInterfaceStats(): array
    {
        $interfaces = [];

        foreach (preg_split('/\R/', trim($this->dataSource->readFile('/proc/net/dev') ?? '')) ?: [] as $line) {
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
                'rxPackets' => (int) $parts[1],
                'rxErrors' => (int) $parts[2],
                'rxDropped' => (int) $parts[3],
                'txBytes' => (int) $parts[8],
                'txPackets' => (int) $parts[9],
                'txErrors' => (int) $parts[10],
                'txDropped' => (int) $parts[11],
            ];
        }

        return $interfaces;
    }

    /**
     * @param array<string, array<string, int>> $firstStats
     * @param array<string, array<string, int>> $secondStats
     * @return list<array<string, mixed>>
     */
    private function interfaces(array $firstStats, array $secondStats, float $seconds): array
    {
        $metadata = $this->interfaceMetadata();
        $interfaces = [];

        foreach (array_unique(array_merge(array_keys($secondStats), array_keys($metadata))) as $name) {
            if ($name === 'lo') {
                continue;
            }

            $first = $firstStats[$name] ?? [];
            $second = $secondStats[$name] ?? [];
            $meta = $metadata[$name] ?? [];
            $rxRate = $this->rate($first, $second, 'rxBytes', $seconds);
            $txRate = $this->rate($first, $second, 'txBytes', $seconds);
            $rxErrorRate = $this->rate($first, $second, 'rxErrors', $seconds);
            $txErrorRate = $this->rate($first, $second, 'txErrors', $seconds);

            $interfaces[] = [
                'name' => $name,
                'state' => (string) ($meta['state'] ?? 'UNKNOWN'),
                'mac' => $meta['mac'] ?? null,
                'mtu' => $meta['mtu'] ?? null,
                'addresses' => $meta['addresses'] ?? [],
                'rxBytes' => $second['rxBytes'] ?? 0,
                'txBytes' => $second['txBytes'] ?? 0,
                'rxBytesPerSecond' => $rxRate,
                'txBytesPerSecond' => $txRate,
                'rxPacketsPerSecond' => $this->rate($first, $second, 'rxPackets', $seconds),
                'txPacketsPerSecond' => $this->rate($first, $second, 'txPackets', $seconds),
                'rxErrors' => $second['rxErrors'] ?? 0,
                'txErrors' => $second['txErrors'] ?? 0,
                'rxDropped' => $second['rxDropped'] ?? 0,
                'txDropped' => $second['txDropped'] ?? 0,
                'errorRate' => round($rxErrorRate + $txErrorRate, 2),
                'totalBytesPerSecond' => round($rxRate + $txRate, 2),
            ];
        }

        usort($interfaces, fn (array $left, array $right): int => (float) $right['totalBytesPerSecond'] <=> (float) $left['totalBytesPerSecond']);

        return $interfaces;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function interfaceMetadata(): array
    {
        $output = $this->dataSource->runCommand(self::IP_COMMAND, '-j', 'addr', 'show');
        $items = $this->decodeJsonList($output);
        $metadata = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = (string) ($item['ifname'] ?? '');

            if ($name === '') {
                continue;
            }

            $addresses = [];

            foreach (($item['addr_info'] ?? []) as $address) {
                if (!is_array($address)) {
                    continue;
                }

                $local = (string) ($address['local'] ?? '');
                $prefix = $address['prefixlen'] ?? null;

                if ($local !== '') {
                    $addresses[] = is_numeric($prefix) ? sprintf('%s/%d', $local, (int) $prefix) : $local;
                }
            }

            $metadata[$name] = [
                'state' => (string) ($item['operstate'] ?? 'UNKNOWN'),
                'mac' => $item['address'] ?? null,
                'mtu' => is_numeric($item['mtu'] ?? null) ? (int) $item['mtu'] : null,
                'addresses' => $addresses,
            ];
        }

        return $metadata;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(): array
    {
        $routes = [];

        foreach ($this->decodeJsonList($this->dataSource->runCommand(self::IP_COMMAND, '-j', 'route', 'show')) as $route) {
            if (!is_array($route)) {
                continue;
            }

            $routes[] = [
                'destination' => (string) ($route['dst'] ?? 'default'),
                'gateway' => $route['gateway'] ?? null,
                'device' => $route['dev'] ?? null,
                'source' => $route['prefsrc'] ?? null,
                'protocol' => $route['protocol'] ?? null,
                'metric' => $route['metric'] ?? null,
            ];
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function dnsServers(): array
    {
        $servers = [];

        foreach (preg_split('/\R/', $this->dataSource->readFile('/etc/resolv.conf') ?? '') ?: [] as $line) {
            if (preg_match('/^\s*nameserver\s+([^#\s]+)/', $line, $matches) === 1) {
                $servers[] = $matches[1];
            }
        }

        return $servers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listeners(): array
    {
        $rows = [];
        $output = $this->dataSource->runCommand(self::SUDO_COMMAND, '-n', self::SS_COMMAND, '-H', '-tunlp')
            ?? $this->dataSource->runCommand(self::SS_COMMAND, '-H', '-tunlp')
            ?? '';

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $socket = $this->parseSocketLine($line, true);

            if ($socket !== null) {
                $rows[] = $socket;
            }
        }

        usort($rows, fn (array $left, array $right): int => [$left['port'], $left['protocol']] <=> [$right['port'], $right['protocol']]);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function connections(): array
    {
        $rows = [];
        $output = $this->dataSource->runCommand(self::SUDO_COMMAND, '-n', self::SS_COMMAND, '-H', '-tunp', 'state', 'established')
            ?? $this->dataSource->runCommand(self::SS_COMMAND, '-H', '-tunp', 'state', 'established')
            ?? '';

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $socket = $this->parseSocketLine($line, false);

            if ($socket !== null) {
                $rows[] = $socket;
            }
        }

        usort($rows, fn (array $left, array $right): int => ((int) $right['sendQueue'] + (int) $right['receiveQueue']) <=> ((int) $left['sendQueue'] + (int) $left['receiveQueue']));

        return array_slice($rows, 0, 40);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSocketLine(string $line, bool $listener): ?array
    {
        $parts = preg_split('/\s+/', trim($line), $listener ? 6 : 5);

        if ($parts === false || count($parts) < ($listener ? 5 : 4)) {
            return null;
        }

        $protocol = strtolower((string) $parts[0]);

        if ($listener) {
            $state = (string) $parts[1];
            $receiveQueue = is_numeric($parts[2]) ? (int) $parts[2] : 0;
            $sendQueue = is_numeric($parts[3]) ? (int) $parts[3] : 0;
            $local = (string) $parts[4];
            $rest = (string) ($parts[5] ?? '');
        } else {
            $state = 'ESTAB';
            $receiveQueue = is_numeric($parts[1]) ? (int) $parts[1] : 0;
            $sendQueue = is_numeric($parts[2]) ? (int) $parts[2] : 0;
            $local = (string) $parts[3];
            $rest = (string) ($parts[4] ?? '');
        }

        $restParts = preg_split('/\s+/', trim($rest), 2) ?: [];
        $peer = (string) ($restParts[0] ?? '');
        $processText = (string) ($restParts[1] ?? '');
        $process = $this->parseProcess($processText);
        $localEndpoint = $this->endpoint($local);
        $peerEndpoint = $this->endpoint($peer);

        return [
            'protocol' => $protocol,
            'state' => $state,
            'receiveQueue' => $receiveQueue,
            'sendQueue' => $sendQueue,
            'localAddress' => $localEndpoint['address'],
            'localPort' => $localEndpoint['port'],
            'peerAddress' => $peerEndpoint['address'],
            'peerPort' => $peerEndpoint['port'],
            'scope' => $this->listenerScope((string) $localEndpoint['address']),
            'port' => $localEndpoint['port'],
            'process' => $process['command'],
            'pid' => $process['pid'],
            'user' => null,
            'listener' => $listener,
        ];
    }

    /**
     * @return array{command: string|null, pid: int|null}
     */
    private function parseProcess(string $text): array
    {
        if (preg_match('/users:\(\("([^"]+)",pid=(\d+)/', $text, $matches) === 1) {
            return ['command' => $matches[1], 'pid' => (int) $matches[2]];
        }

        return ['command' => null, 'pid' => null];
    }

    /**
     * @return array{address: string, port: int|null}
     */
    private function endpoint(string $value): array
    {
        $value = trim($value);
        $port = null;

        if (preg_match('/^\[(.+)]:(\d+|\*)$/', $value, $matches) === 1) {
            return ['address' => $matches[1], 'port' => $matches[2] === '*' ? null : (int) $matches[2]];
        }

        if (preg_match('/^(.+):(\d+|\*)$/', $value, $matches) === 1) {
            $port = $matches[2] === '*' ? null : (int) $matches[2];

            return ['address' => $matches[1], 'port' => $port];
        }

        return ['address' => $value, 'port' => null];
    }

    private function listenerScope(string $address): string
    {
        if (in_array($address, ['127.0.0.1', '::1', '[::ffff:127.0.0.1]'], true) || str_contains($address, '127.0.0.1')) {
            return 'LOCAL';
        }

        if (in_array($address, ['0.0.0.0', '*', '::', '[::]'], true) || str_starts_with($address, '::')) {
            return 'ANY';
        }

        if (str_starts_with($address, '10.') || str_starts_with($address, '192.168.') || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $address) === 1) {
            return 'LAN';
        }

        if (str_starts_with($address, 'fe80:')) {
            return 'LINK';
        }

        return 'BOUND';
    }

    /**
     * @return array<string, mixed>
     */
    private function firewallSnapshot(): array
    {
        $providers = [];
        $openPorts = [];

        $nft = $this->runFirstAvailable(['/usr/sbin/nft', '/usr/bin/nft'], 'list', 'ruleset');

        if (is_string($nft) && trim($nft) !== '') {
            $providers[] = 'nftables';
            $openPorts = array_merge($openPorts, $this->portsFromFirewallText($nft));
        }

        $iptables = $this->runFirstAvailable(['/usr/sbin/iptables', '/usr/bin/iptables'], '-S');

        if (is_string($iptables) && trim($iptables) !== '') {
            $providers[] = 'iptables';
            $openPorts = array_merge($openPorts, $this->portsFromFirewallText($iptables));
        }

        $ufw = $this->runFirstAvailable(['/usr/sbin/ufw', '/usr/bin/ufw'], 'status', 'numbered');

        if (is_string($ufw) && trim($ufw) !== '') {
            $providers[] = 'ufw';
            $openPorts = array_merge($openPorts, $this->portsFromFirewallText($ufw));
        }

        $openPorts = array_values(array_unique(array_filter(array_map('intval', $openPorts))));
        sort($openPorts);

        return [
            'available' => $providers !== [],
            'providers' => array_values(array_unique($providers)),
            'openPorts' => $openPorts,
            'warning' => $providers === [] ? 'Nenhum firewall local suportado foi detectado no PATH do painel. Instale nftables, iptables ou ufw para correlacionar listeners com regras.' : null,
        ];
    }

    /**
     * @return list<int>
     */
    private function portsFromFirewallText(string $text): array
    {
        $ports = [];

        if (preg_match_all('/(?:dport|--dport|ALLOW IN)\s+(\d{1,5})/i', $text, $matches) > 0) {
            foreach ($matches[1] as $port) {
                $ports[] = (int) $port;
            }
        }

        if (preg_match_all('/\b(\d{1,5})\/(?:tcp|udp)\b/i', $text, $matches) > 0) {
            foreach ($matches[1] as $port) {
                $ports[] = (int) $port;
            }
        }

        return array_values(array_filter($ports, fn (int $port): bool => $port > 0 && $port <= 65535));
    }

    /**
     * @param list<array<string, mixed>> $listeners
     * @param array<string, mixed> $firewall
     * @return list<array<string, mixed>>
     */
    private function annotateListeners(array $listeners, array $firewall): array
    {
        $openPorts = array_map('intval', is_array($firewall['openPorts'] ?? null) ? $firewall['openPorts'] : []);

        return array_map(function (array $listener) use ($firewall, $openPorts): array {
            $port = is_numeric($listener['port'] ?? null) ? (int) $listener['port'] : null;
            $scope = (string) ($listener['scope'] ?? 'BOUND');

            if ($scope === 'LOCAL') {
                $listener['firewallStatus'] = 'local';
            } elseif (!(bool) ($firewall['available'] ?? false)) {
                $listener['firewallStatus'] = 'não verificado';
            } elseif ($port !== null && in_array($port, $openPorts, true)) {
                $listener['firewallStatus'] = 'aparentemente aberto';
            } else {
                $listener['firewallStatus'] = 'não confirmado';
            }

            return $listener;
        }, $listeners);
    }

    /**
     * @param list<array<string, mixed>> $listeners
     * @param list<array<string, mixed>> $connections
     * @return list<array<string, mixed>>
     */
    private function topApplications(array $listeners, array $connections): array
    {
        $apps = [];

        foreach (array_merge($listeners, $connections) as $socket) {
            $key = (string) ($socket['pid'] ?? '') !== '' ? (string) $socket['pid'] : (string) ($socket['process'] ?? 'desconhecido');
            $apps[$key] ??= [
                'pid' => $socket['pid'] ?? null,
                'process' => $socket['process'] ?? 'desconhecido',
                'listeners' => 0,
                'connections' => 0,
                'queuedBytes' => 0,
                'ports' => [],
            ];

            if ((bool) ($socket['listener'] ?? false)) {
                $apps[$key]['listeners']++;
            } else {
                $apps[$key]['connections']++;
            }

            $apps[$key]['queuedBytes'] += (int) ($socket['receiveQueue'] ?? 0) + (int) ($socket['sendQueue'] ?? 0);

            if (is_numeric($socket['port'] ?? null)) {
                $apps[$key]['ports'][(int) $socket['port']] = true;
            }
        }

        $rows = array_map(function (array $app): array {
            $ports = array_keys($app['ports']);
            sort($ports);
            $app['ports'] = $ports;

            return $app;
        }, array_values($apps));

        usort($rows, fn (array $left, array $right): int => [$right['queuedBytes'], $right['connections'], $right['listeners']] <=> [$left['queuedBytes'], $left['connections'], $left['listeners']]);

        return array_slice($rows, 0, 12);
    }

    /**
     * @param list<array<string, mixed>> $interfaces
     * @param list<array<string, mixed>> $listeners
     * @param list<array<string, mixed>> $connections
     * @param array<string, mixed> $firewall
     * @return array<string, mixed>
     */
    private function summary(array $interfaces, array $listeners, array $connections, array $firewall): array
    {
        return [
            'interfaceCount' => count($interfaces),
            'activeInterfaceCount' => count(array_filter($interfaces, fn (array $interface): bool => strtoupper((string) $interface['state']) === 'UP')),
            'rxBytesPerSecond' => round(array_sum(array_column($interfaces, 'rxBytesPerSecond')), 2),
            'txBytesPerSecond' => round(array_sum(array_column($interfaces, 'txBytesPerSecond')), 2),
            'listenerCount' => count($listeners),
            'publicListenerCount' => count(array_filter($listeners, fn (array $listener): bool => ($listener['scope'] ?? '') === 'ANY')),
            'connectionCount' => count($connections),
            'firewallAvailable' => (bool) ($firewall['available'] ?? false),
        ];
    }

    /**
     * @param list<array<string, mixed>> $interfaces
     * @param list<array<string, mixed>> $listeners
     * @param array<string, mixed> $firewall
     * @return list<array{severity: string, label: string, value: string}>
     */
    private function statusReasons(array $interfaces, array $listeners, array $firewall): array
    {
        $reasons = [];
        $activeInterfaces = count(array_filter($interfaces, fn (array $interface): bool => strtoupper((string) $interface['state']) === 'UP'));
        $errorInterfaces = array_values(array_filter($interfaces, fn (array $interface): bool => (float) ($interface['errorRate'] ?? 0.0) > 0.0));
        $publicListeners = array_values(array_filter($listeners, fn (array $listener): bool => ($listener['scope'] ?? '') === 'ANY'));

        if ($interfaces === [] || $activeInterfaces === 0) {
            $reasons[] = ['severity' => 'CRITICAL', 'label' => 'Nenhuma interface ativa detectada', 'value' => sprintf('%d interface(s)', count($interfaces))];
        }

        if ($errorInterfaces !== []) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'Erros de rede em interfaces', 'value' => implode(', ', array_map(fn (array $interface): string => (string) $interface['name'], $errorInterfaces))];
        }

        if (!(bool) ($firewall['available'] ?? false) && $publicListeners !== []) {
            $reasons[] = ['severity' => 'WARNING', 'label' => 'Firewall local não verificado', 'value' => sprintf('%d listener(s) em todas as interfaces', count($publicListeners))];
        }

        return $reasons;
    }

    /**
     * @param list<array{severity: string, label: string, value: string}> $reasons
     */
    private function statusFor(array $reasons): string
    {
        foreach ($reasons as $reason) {
            if ($reason['severity'] === 'CRITICAL') {
                return 'CRITICAL';
            }
        }

        return $reasons === [] ? 'OK' : 'WARNING';
    }

    /**
     * @param array<string, int> $first
     * @param array<string, int> $second
     */
    private function rate(array $first, array $second, string $key, float $seconds): float
    {
        return round(max(0, ($second[$key] ?? 0) - ($first[$key] ?? 0)) / $seconds, 2);
    }

    /**
     * @param list<string> $commands
     */
    private function runFirstAvailable(array $commands, string ...$arguments): ?string
    {
        foreach ($commands as $command) {
            if ($this->dataSource instanceof LocalSystemDataSource && !is_executable($command)) {
                continue;
            }

            $output = $this->dataSource->runCommand(self::SUDO_COMMAND, '-n', $command, ...$arguments)
                ?? $this->dataSource->runCommand($command, ...$arguments);

            if ($output !== null) {
                return $output;
            }
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private function decodeJsonList(?string $output): array
    {
        if ($output === null || trim($output) === '') {
            return [];
        }

        try {
            $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }
}
