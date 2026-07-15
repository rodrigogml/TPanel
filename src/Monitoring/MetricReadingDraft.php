<?php

declare(strict_types=1);

namespace TPanel\Monitoring;

use DateTimeImmutable;

final class MetricReadingDraft
{
    public function __construct(
        public readonly ?int $idServerHealthSummary,
        public readonly string $metricCategory,
        public readonly string $metricName,
        public readonly mixed $metricValue,
        public readonly ?string $unit,
        public readonly string $severity,
        public readonly string $source,
        public readonly DateTimeImmutable $collectedAt,
        public readonly ?DateTimeImmutable $expiresAt = null,
    ) {
    }
}
