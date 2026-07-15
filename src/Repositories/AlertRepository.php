<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use TPanel\Alerts\Alert;
use TPanel\Alerts\AlertAcknowledgement;
use TPanel\Alerts\AlertDraft;
use TPanel\Alerts\EventComment;

interface AlertRepository
{
    public function create(AlertDraft $draft): Alert;

    public function findAlert(int $idAlert): ?Alert;

    public function acknowledgeAlert(
        int $idAlert,
        int $idActorUser,
        ?string $acknowledgementNote,
        DateTimeImmutable $acknowledgedAt
    ): AlertAcknowledgement;

    public function updateAlertStatus(int $idAlert, string $status, ?DateTimeImmutable $resolvedAt = null): void;

    public function addComment(
        ?int $idAlert,
        ?int $idAuditRecord,
        int $idActorUser,
        string $commentText,
        DateTimeImmutable $createdAt
    ): EventComment;
}
