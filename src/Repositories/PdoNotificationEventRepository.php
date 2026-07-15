<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use PDO;
use TPanel\Notifications\NotificationEvent;
use TPanel\Notifications\NotificationEventDraft;

final class PdoNotificationEventRepository implements NotificationEventRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(
        NotificationEventDraft $draft,
        string $deliveryStatus,
        ?int $exitCode,
        ?string $failureReason
    ): NotificationEvent {
        $statement = $this->pdo->prepare(
            'INSERT INTO notificationEvent (
                idAlert,
                sender,
                category,
                priority,
                title,
                message,
                deliveryStatus,
                exitCode,
                failureReason
            ) VALUES (
                :idAlert,
                :sender,
                :category,
                :priority,
                :title,
                :message,
                :deliveryStatus,
                :exitCode,
                :failureReason
            )'
        );

        $statement->execute([
            'idAlert' => $draft->idAlert,
            'sender' => $draft->sender,
            'category' => $draft->category,
            'priority' => $draft->priority,
            'title' => $draft->title,
            'message' => $draft->message,
            'deliveryStatus' => $deliveryStatus,
            'exitCode' => $exitCode,
            'failureReason' => $failureReason,
        ]);

        return new NotificationEvent(
            id: (int) $this->pdo->lastInsertId(),
            idAlert: $draft->idAlert,
            sender: $draft->sender,
            category: $draft->category,
            priority: $draft->priority,
            title: $draft->title,
            message: $draft->message,
            deliveryStatus: $deliveryStatus,
            exitCode: $exitCode,
            failureReason: $failureReason,
            createdAt: new DateTimeImmutable(),
            sentAt: null,
        );
    }

    public function updateDeliveryResult(
        NotificationEvent $event,
        string $deliveryStatus,
        ?int $exitCode,
        ?string $failureReason
    ): NotificationEvent {
        $sentAt = $deliveryStatus === 'SENT' ? new DateTimeImmutable() : null;
        $statement = $this->pdo->prepare(
            'UPDATE notificationEvent
            SET deliveryStatus = :deliveryStatus,
                exitCode = :exitCode,
                failureReason = :failureReason,
                sentAt = :sentAt
            WHERE id = :id'
        );

        $statement->execute([
            'id' => $event->id(),
            'deliveryStatus' => $deliveryStatus,
            'exitCode' => $exitCode,
            'failureReason' => $failureReason,
            'sentAt' => $sentAt?->format('Y-m-d H:i:s'),
        ]);

        return new NotificationEvent(
            id: $event->id(),
            idAlert: $event->idAlert(),
            sender: $event->sender(),
            category: $event->category(),
            priority: $event->priority(),
            title: $event->title(),
            message: $event->message(),
            deliveryStatus: $deliveryStatus,
            exitCode: $exitCode,
            failureReason: $failureReason,
            createdAt: $event->createdAt(),
            sentAt: $sentAt,
        );
    }
}
