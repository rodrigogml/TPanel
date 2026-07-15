<?php

declare(strict_types=1);

namespace TPanel\Services;

final class WebSubmissionResult
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $resultStatus,
        public readonly string $message,
        public readonly ?int $auditRecordId,
        public readonly ?int $exitCode,
    ) {
    }
}
