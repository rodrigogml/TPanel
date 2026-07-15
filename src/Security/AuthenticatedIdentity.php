<?php

declare(strict_types=1);

namespace TPanel\Security;

final class AuthenticatedIdentity
{
    public function __construct(
        private readonly string $username
    ) {
    }

    public function username(): string
    {
        return $this->username;
    }
}
