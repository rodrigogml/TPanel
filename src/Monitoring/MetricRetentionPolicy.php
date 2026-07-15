<?php

declare(strict_types=1);

namespace TPanel\Monitoring;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class MetricRetentionPolicy
{
    public const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly int $retentionDays = self::DEFAULT_RETENTION_DAYS
    ) {
        if ($retentionDays < 1) {
            throw new InvalidArgumentException('Metric retention must be at least 1 day.');
        }
    }

    /**
     * @param array<string, mixed> $appConfig
     */
    public static function fromAppConfig(array $appConfig): self
    {
        $monitoring = is_array($appConfig['monitoring'] ?? null) ? $appConfig['monitoring'] : [];

        return new self((int) ($monitoring['retentionDays'] ?? self::DEFAULT_RETENTION_DAYS));
    }

    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    public function expirationFor(DateTimeImmutable $collectedAt): DateTimeImmutable
    {
        return $collectedAt->add(new DateInterval(sprintf('P%dD', $this->retentionDays)));
    }
}
