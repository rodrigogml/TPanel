<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use TPanel\Command\AuthorizedCommandResult;
use TPanel\Command\CommandExecutionReservation;

interface CommandExecutionRepository
{
    public function findActiveByRequestId(string $requestId, DateTimeImmutable $now): ?\TPanel\Command\CommandExecutionRecord;

    public function reserve(
        string $requestId,
        string $actionKey,
        string $requestFingerprint,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now
    ): CommandExecutionReservation;

    public function complete(
        string $requestId,
        AuthorizedCommandResult $result,
        DateTimeImmutable $completedAt
    ): void;
}
