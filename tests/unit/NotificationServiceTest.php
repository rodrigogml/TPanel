<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Notifications\NotificationCommandResult;
use TPanel\Notifications\NotificationCommandRunner;
use TPanel\Notifications\NotificationEvent;
use TPanel\Notifications\NotificationEventDraft;
use TPanel\Notifications\NotificationValidationException;
use TPanel\Repositories\NotificationEventRepository;
use TPanel\Services\NotificationService;

final class NotificationServiceTest extends TestCase
{
    public function testPreparesPendingNotificationWhenIntegrationIsEnabled(): void
    {
        $repository = new InMemoryNotificationEventRepository();
        $service = new NotificationService($repository, $this->config(enabled: true));

        $event = $service->prepare(new NotificationEventDraft(
            idAlert: 10,
            sender: 'TPanel',
            category: 'service',
            priority: 'HIGH',
            title: 'Service warning token=abc123',
            message: 'apache2 failed password=plain',
        ));

        self::assertSame('PENDING', $event->deliveryStatus());
        self::assertSame('Service warning token=[REDACTED]', $event->title());
        self::assertSame('apache2 failed password=[REDACTED]', $event->message());
        self::assertNull($event->failureReason());
        self::assertSame($event, $repository->events()[0]);
    }

    public function testPreparesSkippedNotificationWhenIntegrationIsDisabled(): void
    {
        $service = new NotificationService(new InMemoryNotificationEventRepository(), $this->config(enabled: false));

        $event = $service->prepare(new NotificationEventDraft(
            idAlert: null,
            sender: 'TPanel',
            category: 'audit',
            priority: 'NORMAL',
            title: 'Action denied',
            message: 'Monitor attempted administrative action',
        ));

        self::assertSame('SKIPPED', $event->deliveryStatus());
        self::assertSame('NotiCLI integration is disabled.', $event->failureReason());
    }

    public function testRejectsCategoryOutsideAllowlist(): void
    {
        $service = new NotificationService(new InMemoryNotificationEventRepository(), $this->config(enabled: true));

        $this->expectException(NotificationValidationException::class);
        $this->expectExceptionMessage('Notification category "backup" is not allowed.');

        $service->prepare(new NotificationEventDraft(
            idAlert: null,
            sender: 'TPanel',
            category: 'backup',
            priority: 'NORMAL',
            title: 'Backup failed',
            message: 'backup failed',
        ));
    }

    public function testRejectsPriorityOutsideAllowlist(): void
    {
        $service = new NotificationService(new InMemoryNotificationEventRepository(), $this->config(enabled: true));

        $this->expectException(NotificationValidationException::class);
        $this->expectExceptionMessage('Notification priority "URGENT" is not allowed.');

        $service->prepare(new NotificationEventDraft(
            idAlert: null,
            sender: 'TPanel',
            category: 'service',
            priority: 'URGENT',
            title: 'Service failed',
            message: 'service failed',
        ));
    }

    public function testSendInvokesNotiCliAndMarksEventAsSent(): void
    {
        $runner = new FakeNotificationCommandRunner(new NotificationCommandResult(0, 'ok', ''));
        $service = new NotificationService(new InMemoryNotificationEventRepository(), $this->config(enabled: true), runner: $runner);

        $event = $service->send(new NotificationEventDraft(
            idAlert: null,
            sender: 'TPanel',
            category: 'service',
            priority: 'NORMAL',
            title: 'Service recovered',
            message: 'apache2 recovered',
        ));

        self::assertSame('SENT', $event->deliveryStatus());
        self::assertSame(0, $event->exitCode());
        self::assertSame('/usr/local/bin/noticli', $runner->binaryPath());
        self::assertSame('send', $runner->arguments()[0]);
        self::assertContains('--sender', $runner->arguments());
        self::assertContains('TPanel', $runner->arguments());
    }

    public function testSendCapturesFailedExitCodeAndSanitizedDiagnostic(): void
    {
        $runner = new FakeNotificationCommandRunner(new NotificationCommandResult(6, '', 'delivery failed token=abc123'));
        $service = new NotificationService(new InMemoryNotificationEventRepository(), $this->config(enabled: true), runner: $runner);

        $event = $service->send(new NotificationEventDraft(
            idAlert: null,
            sender: 'TPanel',
            category: 'service',
            priority: 'HIGH',
            title: 'Service failed',
            message: 'apache2 failed',
        ));

        self::assertSame('FAILED', $event->deliveryStatus());
        self::assertSame(6, $event->exitCode());
        self::assertSame('delivery failed token=[REDACTED]', $event->failureReason());
    }

    public function testSendSkipsWithoutInvokingRunnerWhenIntegrationIsDisabled(): void
    {
        $runner = new FakeNotificationCommandRunner(new NotificationCommandResult(0, 'ok', ''));
        $service = new NotificationService(new InMemoryNotificationEventRepository(), $this->config(enabled: false), runner: $runner);

        $event = $service->send(new NotificationEventDraft(
            idAlert: null,
            sender: 'TPanel',
            category: 'audit',
            priority: 'LOW',
            title: 'Skipped',
            message: 'integration disabled',
        ));

        self::assertSame('SKIPPED', $event->deliveryStatus());
        self::assertSame([], $runner->arguments());
    }

    /**
     * @return array<string, mixed>
     */
    private function config(bool $enabled): array
    {
        $config = require __DIR__ . '/../../config/noticli.php.model';
        $config['enabled'] = $enabled;

        return $config;
    }
}

final class InMemoryNotificationEventRepository implements NotificationEventRepository
{
    private int $nextId = 1;

    /** @var list<NotificationEvent> */
    private array $events = [];

    public function create(
        NotificationEventDraft $draft,
        string $deliveryStatus,
        ?int $exitCode,
        ?string $failureReason
    ): NotificationEvent {
        $event = new NotificationEvent(
            id: $this->nextId++,
            idAlert: $draft->idAlert,
            sender: $draft->sender,
            category: $draft->category,
            priority: $draft->priority,
            title: $draft->title,
            message: $draft->message,
            deliveryStatus: $deliveryStatus,
            exitCode: $exitCode,
            failureReason: $failureReason,
            createdAt: new DateTimeImmutable('2026-07-15 00:30:00'),
            sentAt: null,
        );

        $this->events[] = $event;

        return $event;
    }

    public function updateDeliveryResult(
        NotificationEvent $event,
        string $deliveryStatus,
        ?int $exitCode,
        ?string $failureReason
    ): NotificationEvent {
        $updated = new NotificationEvent(
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
            sentAt: $deliveryStatus === 'SENT' ? new DateTimeImmutable('2026-07-15 00:31:00') : null,
        );

        $this->events[$event->id() - 1] = $updated;

        return $updated;
    }

    /**
     * @return list<NotificationEvent>
     */
    public function events(): array
    {
        return $this->events;
    }
}

final class FakeNotificationCommandRunner implements NotificationCommandRunner
{
    /** @var list<string> */
    private array $arguments = [];

    private ?string $binaryPath = null;

    public function __construct(
        private readonly NotificationCommandResult $result
    ) {
    }

    public function run(string $binaryPath, array $arguments, int $timeoutSeconds): NotificationCommandResult
    {
        $this->binaryPath = $binaryPath;
        $this->arguments = $arguments;

        return $this->result;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function binaryPath(): ?string
    {
        return $this->binaryPath;
    }
}
