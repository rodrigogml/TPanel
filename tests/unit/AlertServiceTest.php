<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Alerts\Alert;
use TPanel\Alerts\AlertAcknowledgement;
use TPanel\Alerts\AlertDraft;
use TPanel\Alerts\AlertValidationException;
use TPanel\Alerts\EventComment;
use TPanel\Audit\AuditRecord;
use TPanel\Audit\AuditRecordDraft;
use TPanel\Repositories\AlertRepository;
use TPanel\Repositories\AuditRecordRepository;
use TPanel\Security\AuthenticatedUser;
use TPanel\Security\UserRole;
use TPanel\Services\AlertService;
use TPanel\Services\AuditService;

final class AlertServiceTest extends TestCase
{
    public function testAdministratorAcknowledgesAlertAndCreatesAuditRecord(): void
    {
        $alerts = new InMemoryAlertRepository();
        $audit = new InMemoryAlertAuditRepository();
        $service = new AlertService($alerts, new AuditService($audit));
        $alert = $alerts->create($this->alertDraft());

        $result = $service->acknowledge(
            $this->user(UserRole::ADMINISTRATOR),
            $alert->id(),
            'checked password=plain',
            'ack-1',
            new DateTimeImmutable('2026-07-14 12:00:00')
        );

        self::assertSame('ACKNOWLEDGED', $result->alertStatus);
        self::assertSame('ACKNOWLEDGED', $alerts->findAlert($alert->id())?->status());
        self::assertSame('checked password=[REDACTED]', $alerts->acknowledgements()[0]->acknowledgementNote());
        self::assertSame('ALERT_ACK', $audit->records()[0]->auditType());
        self::assertSame('alert.acknowledge', $audit->records()[0]->actionKey());
        self::assertSame('checked password=[REDACTED]', $audit->records()[0]->validatedParameters()['acknowledgementNote']);
    }

    public function testMonitorAcknowledgesAlertWithoutAdministrativeActionCapability(): void
    {
        $alerts = new InMemoryAlertRepository();
        $audit = new InMemoryAlertAuditRepository();
        $service = new AlertService($alerts, new AuditService($audit));
        $alert = $alerts->create($this->alertDraft());

        $result = $service->acknowledge(
            $this->user(UserRole::MONITOR),
            $alert->id(),
            null,
            'ack-2',
            new DateTimeImmutable('2026-07-14 12:01:00')
        );

        self::assertSame('ACKNOWLEDGED', $result->alertStatus);
        self::assertFalse($this->user(UserRole::MONITOR)->role()->canRunAdministrativeAction());
        self::assertSame('ALERT_ACK', $audit->records()[0]->auditType());
    }

    public function testUserWithoutCapabilityCannotAcknowledgeAlert(): void
    {
        $alerts = new InMemoryAlertRepository();
        $service = new AlertService($alerts, new AuditService(new InMemoryAlertAuditRepository()));
        $alert = $alerts->create($this->alertDraft());

        $this->expectException(AlertValidationException::class);
        $this->expectExceptionMessage('Authenticated user cannot acknowledge alerts.');

        $service->acknowledge(
            $this->user(UserRole::MONITOR, canAcknowledgeAlert: false, canCommentEvent: false),
            $alert->id(),
            null,
            'ack-3',
            new DateTimeImmutable('2026-07-14 12:02:00')
        );
    }

    public function testMonitorCommentsAlertWithSanitizedContentAndAudit(): void
    {
        $alerts = new InMemoryAlertRepository();
        $audit = new InMemoryAlertAuditRepository();
        $service = new AlertService($alerts, new AuditService($audit));
        $alert = $alerts->create($this->alertDraft());

        $result = $service->comment(
            $this->user(UserRole::MONITOR),
            'ALERT',
            $alert->id(),
            'investigated token=abc123',
            'comment-1',
            new DateTimeImmutable('2026-07-14 12:03:00')
        );

        self::assertSame(1, $result->commentId);
        self::assertSame('investigated token=[REDACTED]', $alerts->comments()[0]->commentText());
        self::assertSame('EVENT_COMMENT', $audit->records()[0]->auditType());
        self::assertSame('investigated token=[REDACTED]', $audit->records()[0]->validatedParameters()['commentText']);
    }

    public function testUserWithoutCapabilityCannotCommentEvent(): void
    {
        $alerts = new InMemoryAlertRepository();
        $service = new AlertService($alerts, new AuditService(new InMemoryAlertAuditRepository()));
        $alert = $alerts->create($this->alertDraft());

        $this->expectException(AlertValidationException::class);
        $this->expectExceptionMessage('Authenticated user cannot comment events.');

        $service->comment(
            $this->user(UserRole::MONITOR, canAcknowledgeAlert: false, canCommentEvent: false),
            'ALERT',
            $alert->id(),
            'comment',
            'comment-2',
            new DateTimeImmutable('2026-07-14 12:04:00')
        );
    }

    public function testCreatesAlertWithSanitizedMessage(): void
    {
        $service = new AlertService(new InMemoryAlertRepository(), new AuditService(new InMemoryAlertAuditRepository()));

        $alert = $service->createAlert(new AlertDraft(
            idMetricReading: null,
            alertSource: 'security',
            severity: 'WARNING',
            title: 'SSH failure',
            message: 'failed login token=abc123',
            openedAt: new DateTimeImmutable('2026-07-14 12:05:00')
        ));

        self::assertSame('failed login token=[REDACTED]', $alert->message());
    }

    private function alertDraft(): AlertDraft
    {
        return new AlertDraft(
            idMetricReading: null,
            alertSource: 'service',
            severity: 'WARNING',
            title: 'Service degraded',
            message: 'apache2 is degraded',
            openedAt: new DateTimeImmutable('2026-07-14 11:59:00')
        );
    }

    private function user(
        string $roleName,
        bool $canAcknowledgeAlert = true,
        bool $canCommentEvent = true
    ): AuthenticatedUser {
        return new AuthenticatedUser(
            id: 1,
            externalUsername: 'actor.local',
            displayName: null,
            isActive: true,
            role: new UserRole(
                id: 1,
                roleName: $roleName,
                description: $roleName,
                canRunAdministrativeAction: $roleName === UserRole::ADMINISTRATOR,
                canAcknowledgeAlert: $canAcknowledgeAlert,
                canCommentEvent: $canCommentEvent,
            ),
        );
    }
}

final class InMemoryAlertRepository implements AlertRepository
{
    private int $nextAlertId = 1;
    private int $nextAcknowledgementId = 1;
    private int $nextCommentId = 1;

    /** @var array<int, Alert> */
    private array $alerts = [];

    /** @var list<AlertAcknowledgement> */
    private array $acknowledgements = [];

    /** @var list<EventComment> */
    private array $comments = [];

    public function create(AlertDraft $draft): Alert
    {
        $alert = new Alert(
            id: $this->nextAlertId++,
            idMetricReading: $draft->idMetricReading,
            alertSource: $draft->alertSource,
            severity: $draft->severity,
            title: $draft->title,
            message: $draft->message,
            status: 'OPEN',
            openedAt: $draft->openedAt,
            resolvedAt: null,
        );

        $this->alerts[$alert->id()] = $alert;

        return $alert;
    }

    public function findAlert(int $idAlert): ?Alert
    {
        return $this->alerts[$idAlert] ?? null;
    }

    public function acknowledgeAlert(
        int $idAlert,
        int $idActorUser,
        ?string $acknowledgementNote,
        DateTimeImmutable $acknowledgedAt
    ): AlertAcknowledgement {
        $acknowledgement = new AlertAcknowledgement(
            id: $this->nextAcknowledgementId++,
            idAlert: $idAlert,
            idActorUser: $idActorUser,
            acknowledgementNote: $acknowledgementNote,
            acknowledgedAt: $acknowledgedAt,
        );

        $this->acknowledgements[] = $acknowledgement;

        return $acknowledgement;
    }

    public function updateAlertStatus(int $idAlert, string $status, ?DateTimeImmutable $resolvedAt = null): void
    {
        $alert = $this->alerts[$idAlert];
        $this->alerts[$idAlert] = new Alert(
            id: $alert->id(),
            idMetricReading: $alert->idMetricReading(),
            alertSource: $alert->alertSource(),
            severity: $alert->severity(),
            title: $alert->title(),
            message: $alert->message(),
            status: $status,
            openedAt: $alert->openedAt(),
            resolvedAt: $resolvedAt,
        );
    }

    public function addComment(
        ?int $idAlert,
        ?int $idAuditRecord,
        int $idActorUser,
        string $commentText,
        DateTimeImmutable $createdAt
    ): EventComment {
        $comment = new EventComment(
            id: $this->nextCommentId++,
            idAlert: $idAlert,
            idAuditRecord: $idAuditRecord,
            idActorUser: $idActorUser,
            commentText: $commentText,
            createdAt: $createdAt,
        );

        $this->comments[] = $comment;

        return $comment;
    }

    /**
     * @return list<AlertAcknowledgement>
     */
    public function acknowledgements(): array
    {
        return $this->acknowledgements;
    }

    /**
     * @return list<EventComment>
     */
    public function comments(): array
    {
        return $this->comments;
    }
}

final class InMemoryAlertAuditRepository implements AuditRecordRepository
{
    private int $nextId = 1;

    /** @var list<AuditRecord> */
    private array $records = [];

    public function append(AuditRecordDraft $draft): AuditRecord
    {
        $record = new AuditRecord(
            id: $this->nextId++,
            idActorUser: $draft->idActorUser,
            idAdministrativeAction: $draft->idAdministrativeAction,
            auditType: $draft->auditType,
            actionKey: $draft->actionKey,
            validatedParameters: $draft->validatedParameters,
            resultStatus: $draft->resultStatus,
            exitCode: $draft->exitCode,
            failureReason: $draft->failureReason,
            requestId: $draft->requestId,
            occurredAt: $draft->occurredAt,
        );

        $this->records[] = $record;

        return $record;
    }

    /**
     * @return list<AuditRecord>
     */
    public function records(): array
    {
        return $this->records;
    }
}
