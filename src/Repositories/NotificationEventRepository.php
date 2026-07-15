<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use TPanel\Notifications\NotificationEvent;
use TPanel\Notifications\NotificationEventDraft;

interface NotificationEventRepository
{
    public function create(
        NotificationEventDraft $draft,
        string $deliveryStatus,
        ?int $exitCode,
        ?string $failureReason
    ): NotificationEvent;

    public function updateDeliveryResult(
        NotificationEvent $event,
        string $deliveryStatus,
        ?int $exitCode,
        ?string $failureReason
    ): NotificationEvent;
}
