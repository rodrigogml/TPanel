<?php

declare(strict_types=1);

namespace TPanel\Audit;

use DateTimeImmutable;

final class AuditRecordDraft
{
    /**
     * @param array<string, mixed>|null $validatedParameters
     */
    public function __construct(
        public readonly int $idActorUser,
        public readonly ?int $idAdministrativeAction,
        public readonly string $auditType,
        public readonly ?string $actionKey,
        public readonly ?array $validatedParameters,
        public readonly string $resultStatus,
        public readonly ?int $exitCode,
        public readonly ?string $failureReason,
        public readonly string $requestId,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
