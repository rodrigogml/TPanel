<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Collectors\CpuCollector;
use TPanel\Collectors\DiskHealthCollector;
use TPanel\Collectors\LogCollector;
use TPanel\Collectors\MemoryCollector;
use TPanel\Collectors\MonitoringCollectorService;
use TPanel\Collectors\NetworkCollector;
use TPanel\Collectors\ProcessCollector;
use TPanel\Collectors\RaidCollector;
use TPanel\Collectors\SecurityCollector;
use TPanel\Collectors\SensorCollector;
use TPanel\Collectors\StorageCollector;
use TPanel\Collectors\SystemCollector;

require_once __DIR__ . '/FakeSystemDataSource.php';

final class NetworkProcessLogSecurityCollectorsTest extends TestCase
{
    public function testNetworkCollectorParsesInterfacesIpsTrafficGatewayDnsAndLatency(): void
    {
        $collector = new NetworkCollector(new FakeSystemDataSource(
            files: [
                '/proc/net/dev' => implode("\n", [
                    'Inter-|   Receive                                                |  Transmit',
                    ' face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed',
                    '  eth0: 1000 10 2 0 0 0 0 0 2000 20 1 0 0 0 0 0',
                    '    lo: 1 1 0 0 0 0 0 0 1 1 0 0 0 0 0 0',
                ]),
                '/etc/resolv.conf' => "nameserver 1.1.1.1\nnameserver 8.8.8.8\n",
            ],
            commands: [
                '/usr/sbin/ip -o addr show' => "2: eth0    inet 192.168.1.10/24 brd 192.168.1.255 scope global eth0\n2: eth0    inet6 fe80::1/64 scope link\n",
                '/usr/sbin/ip route show default' => "default via 192.168.1.1 dev eth0 proto dhcp src 192.168.1.10 metric 100\n",
                '/bin/ping -c 1 -W 1 1.1.1.1' => "64 bytes from 1.1.1.1: icmp_seq=1 ttl=58 time=12.3 ms\n",
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 13:00:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('eth0', $snapshot->interfaces[0]['name']);
        self::assertSame(['192.168.1.10/24', 'fe80::1/64'], $snapshot->interfaces[0]['ips']);
        self::assertSame(1000, $snapshot->interfaces[0]['rxBytes']);
        self::assertSame(2, $snapshot->interfaces[0]['rxErrors']);
        self::assertSame(2000, $snapshot->interfaces[0]['txBytes']);
        self::assertSame(1, $snapshot->interfaces[0]['txErrors']);
        self::assertSame('192.168.1.1', $snapshot->gateway);
        self::assertSame(['1.1.1.1', '8.8.8.8'], $snapshot->dnsServers);
        self::assertSame(12.3, $snapshot->latencyMs);
    }

    public function testProcessCollectorParsesTopCpuAndMemoryProcesses(): void
    {
        $collector = new ProcessCollector(new FakeSystemDataSource(commands: [
            '/bin/ps -eo pid,user,pcpu,pmem,comm --no-headers' => implode("\n", [
                ' 100 www-data  1.0  2.5 apache2',
                ' 101 tpanel   20.0  1.0 php-fpm',
                ' 102 mysql     5.0 30.0 mysqld',
                '',
            ]),
        ]), limit: 2);

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 13:01:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('tpanel', $snapshot->topByCpu[0]['user']);
        self::assertSame('php-fpm', $snapshot->topByCpu[0]['command']);
        self::assertSame(20.0, $snapshot->topByCpu[0]['cpuPercent']);
        self::assertSame('mysqld', $snapshot->topByMemory[0]['command']);
        self::assertSame(30.0, $snapshot->topByMemory[0]['memoryPercent']);
        self::assertCount(2, $snapshot->topByCpu);
    }

    public function testLogCollectorSanitizesJournalAndSyslogErrors(): void
    {
        $collector = new LogCollector(new FakeSystemDataSource(
            files: [
                '/var/log/syslog' => implode("\n", [
                    'Jul 15 app info regular line',
                    'Jul 15 app error token=abc123 failed request',
                    '',
                ]),
            ],
            commands: [
                '/usr/bin/journalctl -p warning..alert -n 20 --no-pager' => "Jul 15 service warning password=plain denied\n",
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 13:02:00'));

        self::assertTrue($snapshot->available);
        self::assertSame('Jul 15 service warning password=[REDACTED] denied', $snapshot->journalErrors[0]);
        self::assertSame('Jul 15 app error token=[REDACTED] failed request', $snapshot->syslogErrors[0]);
    }

    public function testSecurityCollectorParsesSshFirewallAndUpdates(): void
    {
        $collector = new SecurityCollector(new FakeSystemDataSource(
            files: [
                '/var/log/auth.log' => implode("\n", [
                    'Jul 15 host sshd[111]: Accepted publickey for admin from 10.0.0.1 port 55000 ssh2',
                    'Jul 15 host sshd[112]: Failed password for invalid user root from 10.0.0.2 port 55001 ssh2 password=plain',
                    '',
                ]),
            ],
            commands: [
                '/usr/sbin/ufw status' => "Status: active\n",
                '/usr/bin/apt list --upgradable' => "Listing...\nopenssl/stable 3.0 amd64 [upgradable from: 2.9]\nphp/stable 8.4 amd64 [upgradable from: 8.4]\n",
            ]
        ));

        $snapshot = $collector->collect(new DateTimeImmutable('2026-07-15 13:03:00'));

        self::assertTrue($snapshot->available);
        self::assertCount(1, $snapshot->recentSshLogins);
        self::assertCount(1, $snapshot->recentSshFailures);
        self::assertStringContainsString('password=[REDACTED]', $snapshot->recentSshFailures[0]);
        self::assertSame('ACTIVE', $snapshot->firewallState);
        self::assertSame(2, $snapshot->availableUpdates);
    }

    public function testMonitoringCollectorServiceBuildsUnavailableDraftsWhenSourcesAreMissing(): void
    {
        $dataSource = new FakeSystemDataSource();
        $service = new MonitoringCollectorService(
            new SystemCollector($dataSource),
            new CpuCollector($dataSource),
            new MemoryCollector($dataSource),
            new StorageCollector($dataSource),
            new DiskHealthCollector($dataSource),
            new RaidCollector($dataSource),
            new SensorCollector($dataSource),
            new NetworkCollector($dataSource),
            new ProcessCollector($dataSource),
            new LogCollector($dataSource),
            new SecurityCollector($dataSource),
        );

        $drafts = $service->collectNetworkProcessesLogsSecurity(new DateTimeImmutable('2026-07-15 13:04:00'));

        self::assertCount(4, $drafts);
        self::assertSame('NETWORK', $drafts[0]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[0]->severity);
        self::assertSame('PROCESS', $drafts[1]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[1]->severity);
        self::assertSame('LOG', $drafts[2]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[2]->severity);
        self::assertSame('SECURITY', $drafts[3]->metricCategory);
        self::assertSame('UNAVAILABLE', $drafts[3]->severity);
    }
}
