<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use PDO;
use PDOException;
use TPanel\Command\AuthorizedCommandResult;
use TPanel\Command\CommandExecutionRecord;
use TPanel\Command\CommandExecutionReservation;

final class PdoCommandExecutionRepository implements CommandExecutionRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findActiveByRequestId(string $requestId, DateTimeImmutable $now): ?CommandExecutionRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT
                requestId,
                actionKey,
                requestFingerprint,
                resultStatus,
                exitCode,
                stdoutSummary,
                stderrSummary,
                failureReason,
                expiresAt,
                createdAt,
                updatedAt
            FROM commandExecutionRequest
            WHERE requestId = :requestId AND expiresAt > :now
            LIMIT 1'
        );
        $statement->execute([
            'requestId' => $requestId,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
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

        $this->deleteExpiredRequest($requestId, $now);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO commandExecutionRequest (
                    requestId,
                    actionKey,
                    requestFingerprint,
                    resultStatus,
                    expiresAt,
                    createdAt,
                    updatedAt
                ) VALUES (
                    :requestId,
                    :actionKey,
                    :requestFingerprint,
                    :resultStatus,
                    :expiresAt,
                    :createdAt,
                    :updatedAt
                )'
            );
            $statement->execute([
                'requestId' => $requestId,
                'actionKey' => $actionKey,
                'requestFingerprint' => $requestFingerprint,
                'resultStatus' => CommandExecutionRecord::IN_PROGRESS,
                'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
                'createdAt' => $now->format('Y-m-d H:i:s'),
                'updatedAt' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            $existing = $this->findActiveByRequestId($requestId, $now);

            if ($existing !== null) {
                return new CommandExecutionReservation($existing, false);
            }

            throw $exception;
        }

        return new CommandExecutionReservation(
            new CommandExecutionRecord(
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
            ),
            true
        );
    }

    public function complete(
        string $requestId,
        AuthorizedCommandResult $result,
        DateTimeImmutable $completedAt
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE commandExecutionRequest
            SET resultStatus = :resultStatus,
                exitCode = :exitCode,
                stdoutSummary = :stdoutSummary,
                stderrSummary = :stderrSummary,
                failureReason = :failureReason,
                updatedAt = :updatedAt
            WHERE requestId = :requestId'
        );
        $statement->execute([
            'requestId' => $requestId,
            'resultStatus' => $result->resultStatus(),
            'exitCode' => $result->exitCode(),
            'stdoutSummary' => $result->stdoutSummary(),
            'stderrSummary' => $result->stderrSummary(),
            'failureReason' => $result->failureReason(),
            'updatedAt' => $completedAt->format('Y-m-d H:i:s'),
        ]);
    }

    private function deleteExpiredRequest(string $requestId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM commandExecutionRequest WHERE requestId = :requestId AND expiresAt <= :now'
        );
        $statement->execute([
            'requestId' => $requestId,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): CommandExecutionRecord
    {
        return new CommandExecutionRecord(
            requestId: (string) $row['requestId'],
            actionKey: (string) $row['actionKey'],
            requestFingerprint: (string) $row['requestFingerprint'],
            resultStatus: (string) $row['resultStatus'],
            exitCode: $row['exitCode'] === null ? null : (int) $row['exitCode'],
            stdoutSummary: $row['stdoutSummary'] === null ? null : (string) $row['stdoutSummary'],
            stderrSummary: $row['stderrSummary'] === null ? null : (string) $row['stderrSummary'],
            failureReason: $row['failureReason'] === null ? null : (string) $row['failureReason'],
            expiresAt: new DateTimeImmutable((string) $row['expiresAt']),
            createdAt: new DateTimeImmutable((string) $row['createdAt']),
            updatedAt: new DateTimeImmutable((string) $row['updatedAt']),
        );
    }
}
