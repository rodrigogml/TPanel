<?php

declare(strict_types=1);

namespace TPanel\Security;

final class AuthorizationDecision
{
    public const ALLOWED = 'ALLOWED';
    public const USER_INACTIVE = 'USER_INACTIVE';
    public const ROLE_UNKNOWN = 'ROLE_UNKNOWN';
    public const ROLE_NOT_ALLOWED = 'ROLE_NOT_ALLOWED';
    public const ACTION_DISABLED = 'ACTION_DISABLED';

    private function __construct(
        private readonly bool $allowed,
        private readonly string $reasonCode,
        private readonly string $message,
    ) {
    }

    public static function allow(): self
    {
        return new self(true, self::ALLOWED, 'Operation is allowed.');
    }

    public static function deny(string $reasonCode, string $message): self
    {
        return new self(false, $reasonCode, $message);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function message(): string
    {
        return $this->message;
    }
}
