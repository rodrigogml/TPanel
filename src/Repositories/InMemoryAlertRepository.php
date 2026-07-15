<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use TPanel\Alerts\Alert;
use TPanel\Alerts\AlertAcknowledgement;
use TPanel\Alerts\AlertDraft;
use TPanel\Alerts\EventComment;

final class InMemoryAlertRepository implements AlertRepository
{
    private int $nextAlertId = 1;
    private int $nextAcknowledgementId = 1;
    private int $nextCommentId = 1;

    /** @var array<int, Alert> */
    private array $alerts = [];

    /** @var list<AlertAcknowledgement> */
    private array $acknowledgements = [];

    /** @var list<EventComment> */
    private array $comments = [];

    public function create(AlertDraft $draft): Alert
    {
        $alert = new Alert(
            id: $this->nextAlertId++,
            idMetricReading: $draft->idMetricReading,
            alertSource: $draft->alertSource,
            severity: $draft->severity,
            title: $draft->title,
            message: $draft->message,
            status: 'OPEN',
            openedAt: $draft->openedAt,
            resolvedAt: null,
        );

        $this->alerts[$alert->id()] = $alert;

        return $alert;
    }

    public function seed(Alert $alert): void
    {
        $this->alerts[$alert->id()] = $alert;
        $this->nextAlertId = max($this->nextAlertId, $alert->id() + 1);
    }

    public function findAlert(int $idAlert): ?Alert
    {
        return $this->alerts[$idAlert] ?? null;
    }

    public function acknowledgeAlert(
        int $idAlert,
        int $idActorUser,
        ?string $acknowledgementNote,
        DateTimeImmutable $acknowledgedAt
    ): AlertAcknowledgement {
        $acknowledgement = new AlertAcknowledgement(
            id: $this->nextAcknowledgementId++,
            idAlert: $idAlert,
            idActorUser: $idActorUser,
            acknowledgementNote: $acknowledgementNote,
            acknowledgedAt: $acknowledgedAt,
        );

        $this->acknowledgements[] = $acknowledgement;

        return $acknowledgement;
    }

    public function updateAlertStatus(int $idAlert, string $status, ?DateTimeImmutable $resolvedAt = null): void
    {
        $alert = $this->alerts[$idAlert];
        $this->alerts[$idAlert] = new Alert(
            id: $alert->id(),
            idMetricReading: $alert->idMetricReading(),
            alertSource: $alert->alertSource(),
            severity: $alert->severity(),
            title: $alert->title(),
            message: $alert->message(),
            status: $status,
            openedAt: $alert->openedAt(),
            resolvedAt: $resolvedAt,
        );
    }

    public function addComment(
        ?int $idAlert,
        ?int $idAuditRecord,
        int $idActorUser,
        string $commentText,
        DateTimeImmutable $createdAt
    ): EventComment {
        $comment = new EventComment(
            id: $this->nextCommentId++,
            idAlert: $idAlert,
            idAuditRecord: $idAuditRecord,
            idActorUser: $idActorUser,
            commentText: $commentText,
            createdAt: $createdAt,
        );

        $this->comments[] = $comment;

        return $comment;
    }

    /**
     * @return list<AlertAcknowledgement>
     */
    public function acknowledgements(): array
    {
        return $this->acknowledgements;
    }

    /**
     * @return list<EventComment>
     */
    public function comments(): array
    {
        return $this->comments;
    }
}
