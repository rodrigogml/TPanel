<?php

declare(strict_types=1);

namespace TPanel\Alerts;

use DateTimeImmutable;

final class AlertDraft
{
    public function __construct(
        public readonly ?int $idMetricReading,
        public readonly string $alertSource,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly DateTimeImmutable $openedAt,
    ) {
    }
}
