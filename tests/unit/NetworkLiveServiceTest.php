<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TPanel\Services\NetworkLiveService;

final class NetworkLiveServiceTest extends TestCase
{
    public function testSnapshotParsesInterfacesSocketsRoutesDnsAndFirewall(): void
    {
        $service = new NetworkLiveService(new FakeSystemDataSource(
            files: [
                '/proc/net/dev' => implode("\n", [
                    'Inter-|   Receive                                                |  Transmit',
                    ' face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed',
                    ' eth0: 1000 10 0 0 0 0 0 0 2000 20 0 0 0 0 0 0',
                    ' lo: 50 1 0 0 0 0 0 0 50 1 0 0 0 0 0 0',
                ]),
                '/etc/resolv.conf' => "nameserver 192.168.1.1\n",
            ],
            commands: [
                '/usr/bin/ip -j addr show' => json_encode([[
                    'ifname' => 'eth0',
                    'operstate' => 'UP',
                    'mtu' => 1500,
                    'address' => 'aa:bb:cc:dd:ee:ff',
                    'addr_info' => [[
                        'local' => '192.168.1.20',
                        'prefixlen' => 24,
                    ]],
                ]], JSON_THROW_ON_ERROR),
                '/usr/bin/ip -j route show' => json_encode([[
                    'dst' => 'default',
                    'gateway' => '192.168.1.1',
                    'dev' => 'eth0',
                    'prefsrc' => '192.168.1.20',
                ]], JSON_THROW_ON_ERROR),
                '/usr/bin/ss -H -tunlp' => implode("\n", [
                    'tcp LISTEN 0 128 0.0.0.0:443 0.0.0.0:* users:(("apache2",pid=22,fd=4))',
                    'tcp LISTEN 0 128 127.0.0.1:3306 0.0.0.0:* users:(("mysqld",pid=33,fd=8))',
                ]),
                '/usr/bin/ss -H -tunp state established' => 'tcp 0 128 192.168.1.20:443 192.168.1.30:52000 users:(("apache2",pid=22,fd=9))',
                '/usr/sbin/nft list ruleset' => 'tcp dport 443 accept',
            ],
        ));

        $snapshot = $service->snapshot(deltaMicroseconds: 0);

        self::assertSame('OK', $snapshot['status']);
        self::assertSame('eth0', $snapshot['interfaces'][0]['name']);
        self::assertSame(['192.168.1.20/24'], $snapshot['interfaces'][0]['addresses']);
        self::assertSame('192.168.1.1', $snapshot['routes'][0]['gateway']);
        self::assertSame(['192.168.1.1'], $snapshot['dnsServers']);
        self::assertTrue($snapshot['firewall']['available']);
        self::assertSame([443], $snapshot['firewall']['openPorts']);
        self::assertSame('aparentemente aberto', $snapshot['listeners'][0]['firewallStatus']);
        self::assertSame('local', $snapshot['listeners'][1]['firewallStatus']);
        self::assertSame('apache2', $snapshot['connections'][0]['process']);
        self::assertSame(128, $snapshot['connections'][0]['sendQueue']);
    }

    public function testSnapshotWarnsWhenPublicListenersCannotBeCheckedAgainstFirewall(): void
    {
        $service = new NetworkLiveService(new FakeSystemDataSource(
            files: [
                '/proc/net/dev' => "eth0: 1000 10 0 0 0 0 0 0 2000 20 0 0 0 0 0 0\n",
                '/etc/resolv.conf' => '',
            ],
            commands: [
                '/usr/bin/ip -j addr show' => json_encode([[
                    'ifname' => 'eth0',
                    'operstate' => 'UP',
                    'addr_info' => [],
                ]], JSON_THROW_ON_ERROR),
                '/usr/bin/ip -j route show' => '[]',
                '/usr/bin/ss -H -tunlp' => 'tcp LISTEN 0 128 0.0.0.0:22 0.0.0.0:*',
                '/usr/bin/ss -H -tunp state established' => '',
            ],
        ));

        $snapshot = $service->snapshot(deltaMicroseconds: 0);

        self::assertSame('WARNING', $snapshot['status']);
        self::assertSame('Firewall local não verificado', $snapshot['statusReasons'][0]['label']);
        self::assertSame('não verificado', $snapshot['listeners'][0]['firewallStatus']);
    }
}
