<?php

declare(strict_types=1);

namespace TPanel\Command;

use DateTimeImmutable;

final class AuthorizedCommandResult
{
    public const SUCCESS = 'SUCCESS';
    public const DENIED = 'DENIED';
    public const FAILED = 'FAILED';
    public const TIMED_OUT = 'TIMED_OUT';

    public function __construct(
        private readonly string $requestId,
        private readonly string $resultStatus,
        private readonly ?int $exitCode,
        private readonly ?string $stdoutSummary,
        private readonly ?string $stderrSummary,
        private readonly ?string $failureReason,
        private readonly DateTimeImmutable $startedAt,
        private readonly ?DateTimeImmutable $finishedAt,
    ) {
    }

    public function requestId(): string
    {
        return $this->requestId;
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

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
