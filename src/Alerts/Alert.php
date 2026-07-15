<?php

declare(strict_types=1);

namespace TPanel\Alerts;

use DateTimeImmutable;

final class Alert
{
    public function __construct(
        private readonly int $id,
        private readonly ?int $idMetricReading,
        private readonly string $alertSource,
        private readonly string $severity,
        private readonly string $title,
        private readonly string $message,
        private readonly string $status,
        private readonly DateTimeImmutable $openedAt,
        private readonly ?DateTimeImmutable $resolvedAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function idMetricReading(): ?int
    {
        return $this->idMetricReading;
    }

    public function alertSource(): string
    {
        return $this->alertSource;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
