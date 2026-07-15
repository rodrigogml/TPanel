<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use TPanel\Command\AuthorizedCommandResult;
use TPanel\Command\CommandExecutionRecord;
use TPanel\Command\CommandExecutionReservation;

final class InMemoryCommandExecutionRepository implements CommandExecutionRepository
{
    /** @var array<string, CommandExecutionRecord> */
    private array $records = [];

    public function findActiveByRequestId(string $requestId, DateTimeImmutable $now): ?CommandExecutionRecord
    {
        $record = $this->records[$requestId] ?? null;

        if ($record === null || $record->expiresAt() <= $now) {
            return null;
        }

        return $record;
    }

    public function reserve(
        string $requestId,
        string $actionKey,
        string $requestFingerprint,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now
    ): CommandExecutionReservation {
        $existing = $this->findActiveByRequestId($requestId, $now);

        if ($existing !== null) {
            return new CommandExecutionReservation($existing, false);
        }

        $record = new CommandExecutionRecord(
            requestId: $requestId,
            actionKey: $actionKey,
            requestFingerprint: $requestFingerprint,
            resultStatus: CommandExecutionRecord::IN_PROGRESS,
            exitCode: null,
            stdoutSummary: null,
            stderrSummary: null,
            failureReason: null,
            expiresAt: $expiresAt,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->records[$requestId] = $record;

        return new CommandExecutionReservation($record, true);
    }

    public function complete(
        string $requestId,
        AuthorizedCommandResult $result,
        DateTimeImmutable $completedAt
    ): void {
        $existing = $this->records[$requestId] ?? null;

        if ($existing === null) {
            return;
        }

        $this->records[$requestId] = new CommandExecutionRecord(
            requestId: $existing->requestId(),
            actionKey: $existing->actionKey(),
            requestFingerprint: $existing->requestFingerprint(),
            resultStatus: $result->resultStatus(),
            exitCode: $result->exitCode(),
            stdoutSummary: $result->stdoutSummary(),
            stderrSummary: $result->stderrSummary(),
            failureReason: $result->failureReason(),
            expiresAt: $existing->expiresAt(),
            createdAt: $existing->createdAt(),
            updatedAt: $completedAt,
        );
    }
}
