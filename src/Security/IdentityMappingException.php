<?php

declare(strict_types=1);

namespace TPanel\Security;

use RuntimeException;

final class IdentityMappingException extends RuntimeException
{
    public static function missingApacheIdentity(): self
    {
        return new self('Apache authenticated identity is missing.');
    }

    public static function unknownUser(string $username): self
    {
        return new self(sprintf('Authenticated user "%s" is not registered in TPanel.', $username));
    }

    public static function inactiveUser(string $username): self
    {
        return new self(sprintf('Authenticated user "%s" is inactive in TPanel.', $username));
    }

    public static function unknownRole(string $username, string $roleName): self
    {
        return new self(sprintf('Authenticated user "%s" has unknown role "%s".', $username, $roleName));
    }
}
