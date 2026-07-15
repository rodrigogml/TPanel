<?php

declare(strict_types=1);

namespace TPanel\Alerts;

final class AlertAcknowledgementResult
{
    public function __construct(
        public readonly int $acknowledgementId,
        public readonly string $alertStatus,
        public readonly int $auditRecordId,
    ) {
    }
}
