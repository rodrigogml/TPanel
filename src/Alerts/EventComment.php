<?php

declare(strict_types=1);

namespace TPanel\Alerts;

use DateTimeImmutable;

final class EventComment
{
    public function __construct(
        private readonly int $id,
        private readonly ?int $idAlert,
        private readonly ?int $idAuditRecord,
        private readonly int $idActorUser,
        private readonly string $commentText,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function idAlert(): ?int
    {
        return $this->idAlert;
    }

    public function idAuditRecord(): ?int
    {
        return $this->idAuditRecord;
    }

    public function idActorUser(): int
    {
        return $this->idActorUser;
    }

    public function commentText(): string
    {
        return $this->commentText;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
