<?php

declare(strict_types=1);

namespace TPanel\Notifications;

final class NotificationEventDraft
{
    public function __construct(
        public readonly ?int $idAlert,
        public readonly string $sender,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $title,
        public readonly string $message,
    ) {
    }
}
