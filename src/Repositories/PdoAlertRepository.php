<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use PDO;
use TPanel\Alerts\Alert;
use TPanel\Alerts\AlertAcknowledgement;
use TPanel\Alerts\AlertDraft;
use TPanel\Alerts\EventComment;

final class PdoAlertRepository implements AlertRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(AlertDraft $draft): Alert
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO alert (
                idMetricReading,
                alertSource,
                severity,
                title,
                message,
                status,
                openedAt
            ) VALUES (
                :idMetricReading,
                :alertSource,
                :severity,
                :title,
                :message,
                :status,
                :openedAt
            )'
        );

        $statement->execute([
            'idMetricReading' => $draft->idMetricReading,
            'alertSource' => $draft->alertSource,
            'severity' => $draft->severity,
            'title' => $draft->title,
            'message' => $draft->message,
            'status' => 'OPEN',
            'openedAt' => $draft->openedAt->format('Y-m-d H:i:s'),
        ]);

        return new Alert(
            id: (int) $this->pdo->lastInsertId(),
            idMetricReading: $draft->idMetricReading,
            alertSource: $draft->alertSource,
            severity: $draft->severity,
            title: $draft->title,
            message: $draft->message,
            status: 'OPEN',
            openedAt: $draft->openedAt,
            resolvedAt: null,
        );
    }

    public function findAlert(int $idAlert): ?Alert
    {
        $statement = $this->pdo->prepare('SELECT * FROM alert WHERE id = :idAlert LIMIT 1');
        $statement->execute(['idAlert' => $idAlert]);
        $row = $statement->fetch();

        return $row === false ? null : $this->alertFromRow($row);
    }

    public function acknowledgeAlert(
        int $idAlert,
        int $idActorUser,
        ?string $acknowledgementNote,
        DateTimeImmutable $acknowledgedAt
    ): AlertAcknowledgement {
        $statement = $this->pdo->prepare(
            'INSERT INTO alertAcknowledgement (
                idAlert,
                idActorUser,
                acknowledgementNote,
                acknowledgedAt
            ) VALUES (
                :idAlert,
                :idActorUser,
                :acknowledgementNote,
                :acknowledgedAt
            )'
        );

        $statement->execute([
            'idAlert' => $idAlert,
            'idActorUser' => $idActorUser,
            'acknowledgementNote' => $acknowledgementNote,
            'acknowledgedAt' => $acknowledgedAt->format('Y-m-d H:i:s'),
        ]);

        return new AlertAcknowledgement(
            id: (int) $this->pdo->lastInsertId(),
            idAlert: $idAlert,
            idActorUser: $idActorUser,
            acknowledgementNote: $acknowledgementNote,
            acknowledgedAt: $acknowledgedAt,
        );
    }

    public function updateAlertStatus(int $idAlert, string $status, ?DateTimeImmutable $resolvedAt = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE alert
            SET status = :status,
                resolvedAt = :resolvedAt
            WHERE id = :idAlert'
        );

        $statement->execute([
            'idAlert' => $idAlert,
            'status' => $status,
            'resolvedAt' => $resolvedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function addComment(
        ?int $idAlert,
        ?int $idAuditRecord,
        int $idActorUser,
        string $commentText,
        DateTimeImmutable $createdAt
    ): EventComment {
        $statement = $this->pdo->prepare(
            'INSERT INTO eventComment (
                idAlert,
                idAuditRecord,
                idActorUser,
                commentText,
                createdAt,
                updatedAt
            ) VALUES (
                :idAlert,
                :idAuditRecord,
                :idActorUser,
                :commentText,
                :createdAt,
                :updatedAt
            )'
        );

        $statement->execute([
            'idAlert' => $idAlert,
            'idAuditRecord' => $idAuditRecord,
            'idActorUser' => $idActorUser,
            'commentText' => $commentText,
            'createdAt' => $createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $createdAt->format('Y-m-d H:i:s'),
        ]);

        return new EventComment(
            id: (int) $this->pdo->lastInsertId(),
            idAlert: $idAlert,
            idAuditRecord: $idAuditRecord,
            idActorUser: $idActorUser,
            commentText: $commentText,
            createdAt: $createdAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function alertFromRow(array $row): Alert
    {
        return new Alert(
            id: (int) $row['id'],
            idMetricReading: $row['idMetricReading'] === null ? null : (int) $row['idMetricReading'],
            alertSource: (string) $row['alertSource'],
            severity: (string) $row['severity'],
            title: (string) $row['title'],
            message: (string) $row['message'],
            status: (string) $row['status'],
            openedAt: new DateTimeImmutable((string) $row['openedAt']),
            resolvedAt: $row['resolvedAt'] === null ? null : new DateTimeImmutable((string) $row['resolvedAt']),
        );
    }
}
