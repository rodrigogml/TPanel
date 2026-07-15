<?php

declare(strict_types=1);

namespace TPanel\Security;

final class UserRole
{
    public const ADMINISTRATOR = 'ADMINISTRATOR';
    public const MONITOR = 'MONITOR';

    public function __construct(
        private readonly int $id,
        private readonly string $roleName,
        private readonly string $description,
        private readonly bool $canRunAdministrativeAction,
        private readonly bool $canAcknowledgeAlert,
        private readonly bool $canCommentEvent,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function roleName(): string
    {
        return $this->roleName;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function canRunAdministrativeAction(): bool
    {
        return $this->canRunAdministrativeAction;
    }

    public function canAcknowledgeAlert(): bool
    {
        return $this->canAcknowledgeAlert;
    }

    public function canCommentEvent(): bool
    {
        return $this->canCommentEvent;
    }

    public function isKnown(): bool
    {
        return in_array($this->roleName, [self::ADMINISTRATOR, self::MONITOR], true);
    }
}
