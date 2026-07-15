<?php

declare(strict_types=1);

namespace TPanel\Alerts;

use DateTimeImmutable;

final class AlertAcknowledgement
{
    public function __construct(
        private readonly int $id,
        private readonly int $idAlert,
        private readonly int $idActorUser,
        private readonly ?string $acknowledgementNote,
        private readonly DateTimeImmutable $acknowledgedAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function idAlert(): int
    {
        return $this->idAlert;
    }

    public function idActorUser(): int
    {
        return $this->idActorUser;
    }

    public function acknowledgementNote(): ?string
    {
        return $this->acknowledgementNote;
    }

    public function acknowledgedAt(): DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }
}
