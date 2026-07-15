<?php

declare(strict_types=1);

namespace TPanel\Collectors;

use DateTimeImmutable;
use TPanel\Audit\AuditDataSanitizer;

final class ScheduleCollector
{
    public function __construct(
        private readonly SystemDataSource $dataSource = new LocalSystemDataSource(),
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
    ) {
    }

    public function collect(?DateTimeImmutable $collectedAt = null): ScheduleSnapshot
    {
        [$cronAvailable, $cronJobs] = $this->collectCronJobs();
        [$timerAvailable, $timers] = $this->collectTimers();

        return new ScheduleSnapshot(
            available: $cronAvailable || $timerAvailable,
            cronAvailable: $cronAvailable,
            timerAvailable: $timerAvailable,
            cronJobs: $cronJobs,
            timers: $timers,
            collectedAt: $collectedAt ?? new DateTimeImmutable(),
        );
    }

    /**
     * @return array{bool, list<array{source: string, schedule: string, user: string|null, command: string}>}
     */
    private function collectCronJobs(): array
    {
        $jobs = [];
        $available = false;

        $crontab = $this->dataSource->readFile('/etc/crontab');

        if ($crontab !== null) {
            $available = true;
            $jobs = array_merge($jobs, $this->parseCronContent($crontab, '/etc/crontab', true));
        }

        $cronD = $this->dataSource->runCommand('/bin/ls', '-1', '/etc/cron.d');

        if ($cronD !== null) {
            $available = true;

            foreach (preg_split('/\R/', trim($cronD)) ?: [] as $filename) {
                $filename = trim($filename);

                if ($filename === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $filename) !== 1) {
                    continue;
                }

                $source = '/etc/cron.d/' . $filename;
                $content = $this->dataSource->readFile($source);

                if ($content !== null) {
                    $jobs = array_merge($jobs, $this->parseCronContent($content, $source, true));
                }
            }
        }

        return [$available, $jobs];
    }

    /**
     * @return array{bool, list<array{unit: string, activates: string|null, next: string|null, last: string|null, state: string|null}>}
     */
    private function collectTimers(): array
    {
        $output = $this->dataSource->runCommand(
            '/usr/bin/systemctl',
            'list-timers',
            '--all',
            '--output=json',
            '--no-pager'
        );

        if ($output === null) {
            return [false, []];
        }

        return [true, $this->parseTimers($output)];
    }

    /**
     * @return list<array{source: string, schedule: string, user: string|null, command: string}>
     */
    private function parseCronContent(string $content, string $source, bool $hasUserField): array
    {
        $jobs = [];

        foreach (preg_split('/\R/', trim($content)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $line) === 1) {
                continue;
            }

            if (str_starts_with($line, '@')) {
                $job = $this->parseNicknameCronLine($line, $source, $hasUserField);
            } else {
                $job = $this->parseClassicCronLine($line, $source, $hasUserField);
            }

            if ($job !== null) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @return array{source: string, schedule: string, user: string|null, command: string}|null
     */
    private function parseClassicCronLine(string $line, string $source, bool $hasUserField): ?array
    {
        $parts = preg_split('/\s+/', $line, $hasUserField ? 7 : 6) ?: [];
        $minimumParts = $hasUserField ? 7 : 6;

        if (count($parts) < $minimumParts) {
            return null;
        }

        $command = $this->sanitizer->sanitizeText($parts[$hasUserField ? 6 : 5]);

        if ($command === null || $command === '') {
            return null;
        }

        return [
            'source' => $source,
            'schedule' => implode(' ', array_slice($parts, 0, 5)),
            'user' => $hasUserField ? $parts[5] : null,
            'command' => $command,
        ];
    }

    /**
     * @return array{source: string, schedule: string, user: string|null, command: string}|null
     */
    private function parseNicknameCronLine(string $line, string $source, bool $hasUserField): ?array
    {
        $parts = preg_split('/\s+/', $line, $hasUserField ? 3 : 2) ?: [];
        $minimumParts = $hasUserField ? 3 : 2;

        if (count($parts) < $minimumParts) {
            return null;
        }

        $command = $this->sanitizer->sanitizeText($parts[$hasUserField ? 2 : 1]);

        if ($command === null || $command === '') {
            return null;
        }

        return [
            'source' => $source,
            'schedule' => $parts[0],
            'user' => $hasUserField ? $parts[1] : null,
            'command' => $command,
        ];
    }

    /**
     * @return list<array{unit: string, activates: string|null, next: string|null, last: string|null, state: string|null}>
     */
    private function parseTimers(string $output): array
    {
        $decoded = json_decode($output, true);

        if (is_array($decoded)) {
            return $this->parseJsonTimers($decoded);
        }

        return $this->parseTableTimers($output);
    }

    /**
     * @param array<int, mixed> $decoded
     * @return list<array{unit: string, activates: string|null, next: string|null, last: string|null, state: string|null}>
     */
    private function parseJsonTimers(array $decoded): array
    {
        $timers = [];

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $unit = $this->firstString($row, ['unit', 'UNIT']);

            if ($unit === null || !str_ends_with($unit, '.timer')) {
                continue;
            }

            $timers[] = [
                'unit' => $unit,
                'activates' => $this->firstString($row, ['activates', 'ACTIVATES']),
                'next' => $this->firstString($row, ['next', 'NEXT']),
                'last' => $this->firstString($row, ['last', 'LAST']),
                'state' => $this->firstString($row, ['active', 'ACTIVE', 'state', 'STATE']),
            ];
        }

        return $timers;
    }

    /**
     * @return list<array{unit: string, activates: string|null, next: string|null, last: string|null, state: string|null}>
     */
    private function parseTableTimers(string $output): array
    {
        $timers = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $unitIndex = null;

            foreach ($parts as $index => $part) {
                if (str_ends_with($part, '.timer')) {
                    $unitIndex = $index;
                    break;
                }
            }

            if ($unitIndex === null) {
                continue;
            }

            $timers[] = [
                'unit' => $parts[$unitIndex],
                'activates' => $parts[$unitIndex + 1] ?? null,
                'next' => null,
                'last' => null,
                'state' => null,
            ];
        }

        return $timers;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return null;
    }
}
