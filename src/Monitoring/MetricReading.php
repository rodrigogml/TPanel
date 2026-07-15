<?php

declare(strict_types=1);

namespace TPanel\Monitoring;

use DateTimeImmutable;

final class MetricReading
{
    public function __construct(
        private readonly int $id,
        private readonly ?int $idServerHealthSummary,
        private readonly string $metricCategory,
        private readonly string $metricName,
        private readonly mixed $metricValue,
        private readonly ?string $unit,
        private readonly string $severity,
        private readonly string $source,
        private readonly DateTimeImmutable $collectedAt,
        private readonly ?DateTimeImmutable $expiresAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function idServerHealthSummary(): ?int
    {
        return $this->idServerHealthSummary;
    }

    public function metricCategory(): string
    {
        return $this->metricCategory;
    }

    public function metricName(): string
    {
        return $this->metricName;
    }

    public function metricValue(): mixed
    {
        return $this->metricValue;
    }

    public function unit(): ?string
    {
        return $this->unit;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function collectedAt(): DateTimeImmutable
    {
        return $this->collectedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
