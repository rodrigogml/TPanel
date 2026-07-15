<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;
use TPanel\Audit\AuditDataSanitizer;

final class LogCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
        private readonly int $limit = 20,
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): LogSnapshot
    {
        $journal = $this->extractErrorLines(
            $this->dataSource->runCommand('/usr/bin/journalctl', '-p', 'warning..alert', '-n', (string) $this->limit, '--no-pager') ?? ''
        );
        $syslogContent = $this->dataSource->readFile('/var/log/syslog')
            ?? $this->dataSource->readFile('/var/log/messages')
            ?? '';
        $syslog = $this->extractErrorLines($syslogContent);

        return new LogSnapshot(
            available: $journal !== [] || $syslog !== [],
            journalErrors: $journal,
            syslogErrors: $syslog,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return list<string>
     */
    private function extractErrorLines(string $content): array
    {
        $lines = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            if (!preg_match('/error|fail|warn|critical|denied|refused/i', $line)) {
                continue;
            }

            $sanitized = $this->sanitizer->sanitizeText($line);

            if ($sanitized !== null && $sanitized !== '') {
                $lines[] = $sanitized;
            }
        }

        return array_slice($lines, -$this->limit);
    }
}
