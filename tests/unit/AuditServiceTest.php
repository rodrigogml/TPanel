<?php

declare(strict_types=1);

namespace TPanel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TPanel\Audit\AuditRecord;
use TPanel\Audit\AuditRecordDraft;
use TPanel\Audit\AuditValidationException;
use TPanel\Repositories\AuditRecordRepository;
use TPanel\Services\AuditService;

final class AuditServiceTest extends TestCase
{
    public function testRecordsSuccessfulAdministrativeAction(): void
    {
        $repository = new InMemoryAuditRecordRepository();
        $service = new AuditService($repository);

        $record = $service->record($this->draft(resultStatus: 'SUCCESS', failureReason: null));

        self::assertSame(1, $record->idActorUser());
        self::assertSame('ADMIN_ACTION', $record->auditType());
        self::assertSame('service.restart', $record->actionKey());
        self::assertSame('SUCCESS', $record->resultStatus());
        self::assertNull($record->failureReason());
        self::assertSame(['serviceName' => 'apache2'], $record->validatedParameters());
    }

    public function testRecordsDeniedActionWithSanitizedParametersAndFailureReason(): void
    {
        $repository = new InMemoryAuditRecordRepository();
        $service = new AuditService($repository);

        $record = $service->record($this->draft(
            auditType: 'DENIED_ACTION',
            resultStatus: 'DENIED',
            failureReason: 'authorization=Bearer abc123 password=plain',
            validatedParameters: [
                'serviceName' => 'mysql',
                'apiToken' => 'token-value',
                'nested' => [
                    'webhookUrl' => 'https://example.invalid/hook',
                ],
            ],
        ));

        self::assertSame('DENIED', $record->resultStatus());
        self::assertSame('authorization=[REDACTED] password=[REDACTED]', $record->failureReason());
        self::assertSame('[REDACTED]', $record->validatedParameters()['apiToken']);
        self::assertSame('[REDACTED]', $record->validatedParameters()['nested']['webhookUrl']);
    }

    public function testRecordsFailedAction(): void
    {
        $repository = new InMemoryAuditRecordRepository();
        $service = new AuditService($repository);

        $record = $service->record($this->draft(resultStatus: 'FAILED', exitCode: 1, failureReason: 'systemctl returned non-zero exit'));

        self::assertSame('FAILED', $record->resultStatus());
        self::assertSame(1, $record->exitCode());
        self::assertSame('systemctl returned non-zero exit', $record->failureReason());
    }

    public function testRecordsTimedOutAction(): void
    {
        $repository = new InMemoryAuditRecordRepository();
        $service = new AuditService($repository);

        $record = $service->record($this->draft(resultStatus: 'TIMED_OUT', exitCode: null, failureReason: 'Command exceeded 30 seconds'));

        self::assertSame('TIMED_OUT', $record->resultStatus());
        self::assertNull($record->exitCode());
        self::assertSame('Command exceeded 30 seconds', $record->failureReason());
    }

    public function testRejectsFailureWithoutReason(): void
    {
        $service = new AuditService(new InMemoryAuditRecordRepository());

        $this->expectException(AuditValidationException::class);
        $this->expectExceptionMessage('Denied, failed and timed out audit records require a failureReason.');

        $service->record($this->draft(resultStatus: 'FAILED', failureReason: null));
    }

    /**
     * @param array<string, mixed>|null $validatedParameters
     */
    private function draft(
        string $auditType = 'ADMIN_ACTION',
        string $resultStatus = 'SUCCESS',
        ?int $exitCode = 0,
        ?string $failureReason = null,
        ?array $validatedParameters = ['serviceName' => 'apache2'],
    ): AuditRecordDraft {
        return new AuditRecordDraft(
            idActorUser: 1,
            idAdministrativeAction: 10,
            auditType: $auditType,
            actionKey: 'service.restart',
            validatedParameters: $validatedParameters,
            resultStatus: $resultStatus,
            exitCode: $exitCode,
            failureReason: $failureReason,
            requestId: 'req-123',
            occurredAt: new DateTimeImmutable('2026-07-14 12:30:00'),
        );
    }
}

final class InMemoryAuditRecordRepository implements AuditRecordRepository
{
    private int $nextId = 1;

    public function append(AuditRecordDraft $draft): AuditRecord
    {
        return new AuditRecord(
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
    }
}
