<?php

declare(strict_types=1);

namespace TPanel\Notifications;

use DateTimeImmutable;

final class NotificationEvent
{
    public function __construct(
        private readonly int $id,
        private readonly ?int $idAlert,
        private readonly string $sender,
        private readonly string $category,
        private readonly string $priority,
        private readonly string $title,
        private readonly string $message,
        private readonly string $deliveryStatus,
        private readonly ?int $exitCode,
        private readonly ?string $failureReason,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?DateTimeImmutable $sentAt,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function idAlert(): ?int
    {
        return $this->idAlert;
    }

    public function sender(): string
    {
        return $this->sender;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function priority(): string
    {
        return $this->priority;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function deliveryStatus(): string
    {
        return $this->deliveryStatus;
    }

    public function exitCode(): ?int
    {
        return $this->exitCode;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }
}
