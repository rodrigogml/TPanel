<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Monitoring\MetricReading;
use TPanel\Monitoring\MetricReadingDraft;
use TPanel\Monitoring\MetricRetentionPolicy;
use TPanel\Monitoring\MetricValidationException;
use TPanel\Repositories\MetricReadingRepository;
use TPanel\Services\MetricService;

final class MetricServiceTest extends TestCase
{
    public function testRecordsMetricWithDefaultNinetyDayExpiration(): void
    {
        $repository = new InMemoryMetricReadingRepository();
        $service = new MetricService($repository);
        $collectedAt = new DateTimeImmutable('2026-07-14 10:00:00');

        $reading = $service->record($this->draft(collectedAt: $collectedAt));

        self::assertSame('CPU', $reading->metricCategory());
        self::assertSame('usage.total', $reading->metricName());
        self::assertSame(['percent' => 42.5], $reading->metricValue());
        self::assertSame('2026-10-12 10:00:00', $reading->expiresAt()?->format('Y-m-d H:i:s'));
    }

    public function testRecordsMetricWithConfiguredRetention(): void
    {
        $repository = new InMemoryMetricReadingRepository();
        $service = new MetricService($repository, new MetricRetentionPolicy(7));
        $collectedAt = new DateTimeImmutable('2026-07-14 10:00:00');

        $reading = $service->record($this->draft(collectedAt: $collectedAt));

        self::assertSame('2026-07-21 10:00:00', $reading->expiresAt()?->format('Y-m-d H:i:s'));
    }

    public function testFindsLatestMetricReading(): void
    {
        $repository = new InMemoryMetricReadingRepository();
        $service = new MetricService($repository);

        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-14 10:00:00'), metricValue: ['percent' => 20]));
        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-14 10:01:00'), metricValue: ['percent' => 30]));

        $latest = $service->latest('CPU', 'usage.total', 'proc.stat');

        self::assertNotNull($latest);
        self::assertSame(['percent' => 30], $latest->metricValue());
    }

    public function testReturnsHistoryWithinRange(): void
    {
        $repository = new InMemoryMetricReadingRepository();
        $service = new MetricService($repository);

        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-14 09:59:00'), metricValue: ['percent' => 10]));
        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-14 10:00:00'), metricValue: ['percent' => 20]));
        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-14 10:01:00'), metricValue: ['percent' => 30]));

        $history = $service->history(
            'CPU',
            'usage.total',
            'proc.stat',
            new DateTimeImmutable('2026-07-14 10:00:00'),
            new DateTimeImmutable('2026-07-14 10:01:00')
        );

        self::assertCount(2, $history);
        self::assertSame(['percent' => 20], $history[0]->metricValue());
        self::assertSame(['percent' => 30], $history[1]->metricValue());
    }

    public function testPurgesExpiredMetricsOnly(): void
    {
        $repository = new InMemoryMetricReadingRepository();
        $service = new MetricService($repository, new MetricRetentionPolicy(1));

        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-13 10:00:00')));
        $service->record($this->draft(collectedAt: new DateTimeImmutable('2026-07-14 10:00:00')));

        $purged = $service->purgeExpired(new DateTimeImmutable('2026-07-14 10:00:00'));

        self::assertSame(1, $purged);
        self::assertCount(1, $repository->all());
        self::assertSame('2026-07-15 10:00:00', $repository->all()[0]->expiresAt()?->format('Y-m-d H:i:s'));
    }

    public function testRejectsUnknownCategory(): void
    {
        $service = new MetricService(new InMemoryMetricReadingRepository());

        $this->expectException(MetricValidationException::class);
        $this->expectExceptionMessage('Metric category "UNKNOWN_CATEGORY" is not allowed.');

        $service->record($this->draft(metricCategory: 'UNKNOWN_CATEGORY'));
    }

    private function draft(
        string $metricCategory = 'CPU',
        string $metricName = 'usage.total',
        mixed $metricValue = ['percent' => 42.5],
        ?string $unit = 'percent',
        string $severity = 'NORMAL',
        string $source = 'proc.stat',
        ?DateTimeImmutable $collectedAt = null,
    ): MetricReadingDraft {
        return new MetricReadingDraft(
            idServerHealthSummary: null,
            metricCategory: $metricCategory,
            metricName: $metricName,
            metricValue: $metricValue,
            unit: $unit,
            severity: $severity,
            source: $source,
            collectedAt: $collectedAt ?? new DateTimeImmutable('2026-07-14 10:00:00'),
        );
    }
}

final class InMemoryMetricReadingRepository implements MetricReadingRepository
{
    private int $nextId = 1;

    /** @var list<MetricReading> */
    private array $readings = [];

    public function append(MetricReadingDraft $draft): MetricReading
    {
        $reading = new MetricReading(
            id: $this->nextId++,
            idServerHealthSummary: $draft->idServerHealthSummary,
            metricCategory: $draft->metricCategory,
            metricName: $draft->metricName,
            metricValue: $draft->metricValue,
            unit: $draft->unit,
            severity: $draft->severity,
            source: $draft->source,
            collectedAt: $draft->collectedAt,
            expiresAt: $draft->expiresAt,
        );

        $this->readings[] = $reading;

        return $reading;
    }

    public function latest(string $metricCategory, string $metricName, string $source): ?MetricReading
    {
        $matches = array_filter(
            $this->readings,
            static fn (MetricReading $reading): bool => $reading->metricCategory() === $metricCategory
                && $reading->metricName() === $metricName
                && $reading->source() === $source
        );

        usort(
            $matches,
            static fn (MetricReading $left, MetricReading $right): int => $right->collectedAt() <=> $left->collectedAt()
                ?: $right->id() <=> $left->id()
        );

        return $matches[0] ?? null;
    }

    public function history(
        string $metricCategory,
        string $metricName,
        string $source,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $limit = 500
    ): array {
        $matches = array_filter(
            $this->readings,
            static fn (MetricReading $reading): bool => $reading->metricCategory() === $metricCategory
                && $reading->metricName() === $metricName
                && $reading->source() === $source
                && $reading->collectedAt() >= $from
                && $reading->collectedAt() <= $to
        );

        usort(
            $matches,
            static fn (MetricReading $left, MetricReading $right): int => $left->collectedAt() <=> $right->collectedAt()
                ?: $left->id() <=> $right->id()
        );

        return array_slice(array_values($matches), 0, $limit);
    }

    public function purgeExpired(DateTimeImmutable $now): int
    {
        $before = count($this->readings);
        $this->readings = array_values(array_filter(
            $this->readings,
            static fn (MetricReading $reading): bool => $reading->expiresAt() === null || $reading->expiresAt() > $now
        ));

        return $before - count($this->readings);
    }

    /**
     * @return list<MetricReading>
     */
    public function all(): array
    {
        return $this->readings;
    }
}
