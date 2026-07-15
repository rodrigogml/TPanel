<?php

declare(strict_types=1);

namespace TPanel\Notifications;

final class NotificationCommandResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly ?string $stdout,
        public readonly ?string $stderr,
        public readonly bool $timedOut = false,
    ) {
    }
}
