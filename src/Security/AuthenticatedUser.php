<?php

declare(strict_types=1);

namespace TPanel\Security;

final class AuthenticatedUser
{
    public function __construct(
        private readonly int $id,
        private readonly string $externalUsername,
        private readonly ?string $displayName,
        private readonly bool $isActive,
        private readonly UserRole $role,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function externalUsername(): string
    {
        return $this->externalUsername;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function role(): UserRole
    {
        return $this->role;
    }
}
