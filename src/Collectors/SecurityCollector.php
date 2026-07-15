<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;
use TPanel\Audit\AuditDataSanitizer;

final class SecurityCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
        private readonly int $limit = 20,
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): SecuritySnapshot
    {
        $authLog = $this->dataSource->readFile('/var/log/auth.log')
            ?? $this->dataSource->readFile('/var/log/secure')
            ?? '';

        return new SecuritySnapshot(
            available: $authLog !== '' || $this->firewallState() !== null || $this->availableUpdates() !== null,
            recentSshLogins: $this->extractSshLines($authLog, '/Accepted\s+/i'),
            recentSshFailures: $this->extractSshLines($authLog, '/Failed password|Invalid user|authentication failure/i'),
            firewallState: $this->firewallState(),
            availableUpdates: $this->availableUpdates(),
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<string>
     */
    private function extractSshLines(string $content, string $pattern): array
    {
        $lines = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            if (!str_contains($line, 'sshd') || preg_match($pattern, $line) !== 1) {
                continue;
            }

            $sanitized = $this->sanitizer->sanitizeText($line);

            if ($sanitized !== null && $sanitized !== '') {
                $lines[] = $sanitized;
            }
        }

        return array_slice($lines, -$this->limit);
    }

    private function firewallState(): ?string
    {
        $ufw = $this->dataSource->runCommand('/usr/sbin/ufw', 'status');

        if ($ufw !== null && preg_match('/Status:\s*(active|inactive)/i', $ufw, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        $nft = $this->dataSource->runCommand('/usr/sbin/nft', 'list', 'ruleset');

        if ($nft !== null) {
            return trim($nft) === '' ? 'EMPTY' : 'RULES_PRESENT';
        }

        return null;
    }

    private function availableUpdates(): ?int
    {
        $output = $this->dataSource->runCommand('/usr/bin/apt', 'list', '--upgradable');

        if ($output === null) {
            return null;
        }

        $count = 0;

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, 'Listing...')) {
                continue;
            }

            $count++;
        }

        return $count;
    }
}
