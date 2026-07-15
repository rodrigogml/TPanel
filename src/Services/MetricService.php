<?php

declare(strict_types=1);

namespace TPanel\Services;

use DateTimeImmutable;
use TPanel\Monitoring\MetricReading;
use TPanel\Monitoring\MetricReadingDraft;
use TPanel\Monitoring\MetricRetentionPolicy;
use TPanel\Monitoring\MetricValidationException;
use TPanel\Repositories\MetricReadingRepository;

final class MetricService
{
    /** @var list<string> */
    private const METRIC_CATEGORIES = [
        'SYSTEM',
        'CPU',
        'MEMORY',
        'STORAGE',
        'DISK_HEALTH',
        'RAID',
        'NETWORK',
        'SERVICE',
        'PROCESS',
        'LOG',
        'SECURITY',
        'SENSOR',
        'SCHEDULE',
    ];

    /** @var list<string> */
    private const SEVERITIES = [
        'NORMAL',
        'WARNING',
        'CRITICAL',
        'UNAVAILABLE',
    ];

    public function __construct(
        private readonly MetricReadingRepository $readings,
        private readonly MetricRetentionPolicy $retentionPolicy = new MetricRetentionPolicy(),
    ) {
    }

    public function record(MetricReadingDraft $draft): MetricReading
    {
        $this->validate($draft);

        $retainedDraft = new MetricReadingDraft(
            idServerHealthSummary: $draft->idServerHealthSummary,
            metricCategory: $draft->metricCategory,
            metricName: trim($draft->metricName),
            metricValue: $draft->metricValue,
            unit: $this->normalizeOptionalString($draft->unit),
            severity: $draft->severity,
            source: trim($draft->source),
            collectedAt: $draft->collectedAt,
            expiresAt: $draft->expiresAt ?? $this->retentionPolicy->expirationFor($draft->collectedAt),
        );

        return $this->readings->append($retainedDraft);
    }

    public function latest(string $metricCategory, string $metricName, string $source): ?MetricReading
    {
        return $this->readings->latest($metricCategory, $metricName, $source);
    }

    /**
     * @return list<MetricReading>
     */
    public function history(
        string $metricCategory,
        string $metricName,
        string $source,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 500
    ): array {
        if ($from > $to) {
            throw new MetricValidationException('Metric history start date must be before or equal to end date.');
        }

        return $this->readings->history($metricCategory, $metricName, $source, $from, $to, $limit);
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        return $this->readings->purgeExpired($now);
    }

    private function validate(MetricReadingDraft $draft): void
    {
        if (!in_array($draft->metricCategory, self::METRIC_CATEGORIES, true)) {
            throw new MetricValidationException(sprintf('Metric category "%s" is not allowed.', $draft->metricCategory));
        }

        if (trim($draft->metricName) === '') {
            throw new MetricValidationException('Metric name is required.');
        }

        if (!in_array($draft->severity, self::SEVERITIES, true)) {
            throw new MetricValidationException(sprintf('Metric severity "%s" is not allowed.', $draft->severity));
        }

        if (trim($draft->source) === '') {
            throw new MetricValidationException('Metric source is required.');
        }
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
