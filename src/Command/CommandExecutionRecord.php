<?php

declare(strict_types=1);

namespace TPanel\Command;

use DateTimeImmutable;

final class CommandExecutionRecord
{
    public const IN_PROGRESS = 'IN_PROGRESS';

    public function __construct(
        private readonly string $requestId,
        private readonly string $actionKey,
        private readonly string $requestFingerprint,
        private readonly string $resultStatus,
        private readonly ?int $exitCode,
        private readonly ?string $stdoutSummary,
        private readonly ?string $stderrSummary,
        private readonly ?string $failureReason,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function actionKey(): string
    {
        return $this->actionKey;
    }

    public function requestFingerprint(): string
    {
        return $this->requestFingerprint;
    }

    public function resultStatus(): string
    {
        return $this->resultStatus;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function stdoutSummary(): ?string
    {
        return $this->stdoutSummary;
    }

    public function stderrSummary(): ?string
    {
        return $this->stderrSummary;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isInProgress(): bool
    {
        return $this->resultStatus === self::IN_PROGRESS;
    }

    public function toResult(): AuthorizedCommandResult
    {
        return new AuthorizedCommandResult(
            requestId: $this->requestId,
            resultStatus: $this->resultStatus,
            exitCode: $this->exitCode,
            stdoutSummary: $this->stdoutSummary,
            stderrSummary: $this->stderrSummary,
            failureReason: $this->failureReason,
            startedAt: $this->createdAt,
            finishedAt: $this->updatedAt,
        );
    }
}
