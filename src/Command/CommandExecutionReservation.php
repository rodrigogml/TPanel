<?php

declare(strict_types=1);

namespace TPanel\Command;

final class CommandExecutionReservation
{
    public function __construct(
        private readonly CommandExecutionRecord $record,
        private readonly bool $created,
    ) {
    }

    public function record(): CommandExecutionRecord
    {
        return $this->record;
    }

    public function created(): bool
    {
        return $this->created;
    }
}
