<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use TPanel\Audit\AuditRecord;
use TPanel\Audit\AuditRecordDraft;

final class InMemoryAuditRecordRepository implements AuditRecordRepository
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
