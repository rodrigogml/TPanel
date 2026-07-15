<?php

declare(strict_types=1);

namespace TPanel\Services;

use TPanel\Audit\AuditDataSanitizer;
use TPanel\Audit\AuditRecord;
use TPanel\Audit\AuditRecordDraft;
use TPanel\Audit\AuditValidationException;
use TPanel\Repositories\AuditRecordRepository;

final class AuditService
{
    /** @var list<string> */
    private const AUDIT_TYPES = [
        'ADMIN_ACTION',
        'DENIED_ACTION',
        'ALERT_ACK',
        'EVENT_COMMENT',
        'NOTIFICATION',
        'AUTHORIZATION',
    ];

    /** @var list<string> */
    private const RESULT_STATUSES = [
        'SUCCESS',
        'DENIED',
        'FAILED',
        'TIMED_OUT',
        'SKIPPED',
    ];

    public function __construct(
        private readonly AuditRecordRepository $records,
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
    ) {
    }

    public function record(AuditRecordDraft $draft): AuditRecord
    {
        $this->validate($draft);

        $sanitizedDraft = new AuditRecordDraft(
            idActorUser: $draft->idActorUser,
            idAdministrativeAction: $draft->idAdministrativeAction,
            auditType: $draft->auditType,
            actionKey: $this->normalizeOptionalString($draft->actionKey),
            validatedParameters: $this->sanitizer->sanitizeParameters($draft->validatedParameters),
            resultStatus: $draft->resultStatus,
            exitCode: $draft->exitCode,
            failureReason: $this->sanitizer->sanitizeText($this->normalizeOptionalString($draft->failureReason)),
            requestId: trim($draft->requestId),
            occurredAt: $draft->occurredAt,
        );

        return $this->records->append($sanitizedDraft);
    }

    private function validate(AuditRecordDraft $draft): void
    {
        if ($draft->idActorUser <= 0) {
            throw new AuditValidationException('Audit actor is required.');
        }

        if (!in_array($draft->auditType, self::AUDIT_TYPES, true)) {
            throw new AuditValidationException(sprintf('Audit type "%s" is not allowed.', $draft->auditType));
        }

        if (!in_array($draft->resultStatus, self::RESULT_STATUSES, true)) {
            throw new AuditValidationException(sprintf('Audit result status "%s" is not allowed.', $draft->resultStatus));
        }

        if (trim($draft->requestId) === '') {
            throw new AuditValidationException('Audit requestId is required.');
        }

        if ($draft->auditType === 'ADMIN_ACTION' && $this->normalizeOptionalString($draft->actionKey) === null) {
            throw new AuditValidationException('Administrative audit records require an actionKey.');
        }

        if (in_array($draft->resultStatus, ['DENIED', 'FAILED', 'TIMED_OUT'], true)
            && $this->normalizeOptionalString($draft->failureReason) === null) {
            throw new AuditValidationException('Denied, failed and timed out audit records require a failureReason.');
        }
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
