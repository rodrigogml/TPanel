<?php

declare(strict_types=1);

namespace TPanel\Audit;

use DateTimeImmutable;

final class AuditRecord
{
    /**
     * @param array<string, mixed>|null $validatedParameters
     */
    public function __construct(
        private readonly int $id,
        private readonly int $idActorUser,
        private readonly ?int $idAdministrativeAction,
        private readonly string $auditType,
        private readonly ?string $actionKey,
        private readonly ?array $validatedParameters,
        private readonly string $resultStatus,
        private readonly ?int $exitCode,
        private readonly ?string $failureReason,
        private readonly string $requestId,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function idActorUser(): int
    {
        return $this->idActorUser;
    }

    public function idAdministrativeAction(): ?int
    {
        return $this->idAdministrativeAction;
    }

    public function auditType(): string
    {
        return $this->auditType;
    }

    public function actionKey(): ?string
    {
        return $this->actionKey;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validatedParameters(): ?array
    {
        return $this->validatedParameters;
    }

    public function resultStatus(): string
    {
        return $this->resultStatus;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
