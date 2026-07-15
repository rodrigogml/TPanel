<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use TPanel\Monitoring\MetricReading;
use TPanel\Monitoring\MetricReadingDraft;

interface MetricReadingRepository
{
    public function append(MetricReadingDraft $draft): MetricReading;

    public function latest(string $metricCategory, string $metricName, string $source): ?MetricReading;

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
    ): array;

    public function purgeExpired(DateTimeImmutable $now): int;
}
