<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;

final class SystemCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): SystemSnapshot
    {
        $hostname = trim((string) ($this->dataSource->readFile('/proc/sys/kernel/hostname') ?? gethostname() ?: 'unknown'));
        $osRelease = $this->parseKeyValueFile($this->dataSource->readFile('/etc/os-release') ?? '');
        $loadAverage = $this->parseLoadAverage($this->dataSource->readFile('/proc/loadavg') ?? '');

        return new SystemSnapshot(
            hostname: $hostname === '' ? 'unknown' : $hostname,
            debianVersion: $osRelease['VERSION_ID'] ?? $osRelease['PRETTY_NAME'] ?? null,
            kernelRelease: $this->normalizeNullable($this->dataSource->runCommand('/usr/bin/uname', '-r')),
            uptimeSeconds: $this->parseUptimeSeconds($this->dataSource->readFile('/proc/uptime') ?? ''),
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
            loadAverage: $loadAverage,
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValueFile(string $content): array
    {
        $values = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }

    /**
     * @return array{oneMinute: float|null, fiveMinutes: float|null, fifteenMinutes: float|null}
     */
    private function parseLoadAverage(string $content): array
    {
        $parts = preg_split('/\s+/', trim($content)) ?: [];

        return [
            'oneMinute' => isset($parts[0]) && is_numeric($parts[0]) ? (float) $parts[0] : null,
            'fiveMinutes' => isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : null,
            'fifteenMinutes' => isset($parts[2]) && is_numeric($parts[2]) ? (float) $parts[2] : null,
        ];
    }

    private function parseUptimeSeconds(string $content): ?int
    {
        $parts = preg_split('/\s+/', trim($content)) ?: [];

        if (!isset($parts[0]) || !is_numeric($parts[0])) {
            return null;
        }

        return (int) floor((float) $parts[0]);
    }

    private function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
