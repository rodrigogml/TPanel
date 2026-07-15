<?php

declare(strict_types=1);

namespace TPanel\Security;

final class LogAccessDecision
{
    public const ALLOWED = 'ALLOWED';
    public const USER_INACTIVE = 'USER_INACTIVE';
    public const ROLE_UNKNOWN = 'ROLE_UNKNOWN';
    public const LOG_SOURCE_UNKNOWN = 'LOG_SOURCE_UNKNOWN';
    public const LOG_SOURCE_RESTRICTED = 'LOG_SOURCE_RESTRICTED';

    private function __construct(
        private readonly bool $allowed,
        private readonly string $reasonCode,
        private readonly string $message,
    ) {
    }

    public static function allow(): self
    {
        return new self(true, self::ALLOWED, 'Log source is allowed.');
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
