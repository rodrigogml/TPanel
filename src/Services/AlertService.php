<?php

declare(strict_types=1);

namespace TPanel\Services;

use DateTimeImmutable;
use TPanel\Alerts\Alert;
use TPanel\Alerts\AlertAcknowledgementResult;
use TPanel\Alerts\AlertDraft;
use TPanel\Alerts\AlertValidationException;
use TPanel\Alerts\EventCommentResult;
use TPanel\Audit\AuditDataSanitizer;
use TPanel\Audit\AuditRecordDraft;
use TPanel\Repositories\AlertRepository;
use TPanel\Security\ActionAuthorizationService;
use TPanel\Security\AuthenticatedUser;

final class AlertService
{
    /** @var list<string> */
    private const ALERT_SEVERITIES = ['INFO', 'WARNING', 'CRITICAL'];

    /** @var list<string> */
    private const COMMENT_TARGET_TYPES = ['ALERT', 'AUDIT_RECORD'];

    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly AuditService $audit,
        private readonly AuditDataSanitizer $sanitizer = new AuditDataSanitizer(),
        private readonly ActionAuthorizationService $authorization = new ActionAuthorizationService(),
    ) {
    }

    public function createAlert(AlertDraft $draft): Alert
    {
        $this->validateAlertDraft($draft);

        return $this->alerts->create(new AlertDraft(
            idMetricReading: $draft->idMetricReading,
            alertSource: trim($draft->alertSource),
            severity: $draft->severity,
            title: $this->sanitizeRequiredText($draft->title, 'Alert title'),
            message: $this->sanitizeRequiredText($draft->message, 'Alert message'),
            openedAt: $draft->openedAt,
        ));
    }

    public function acknowledge(
        AuthenticatedUser $actor,
        int $idAlert,
        ?string $acknowledgementNote,
        string $requestId,
        DateTimeImmutable $acknowledgedAt
    ): AlertAcknowledgementResult {
        $authorization = $this->authorization->canAcknowledgeAlert($actor);

        if (!$authorization->allowed()) {
            throw new AlertValidationException($authorization->message());
        }

        $alert = $this->alerts->findAlert($idAlert);

        if ($alert === null) {
            throw new AlertValidationException(sprintf('Alert "%d" was not found.', $idAlert));
        }

        $note = $this->sanitizeOptionalText($acknowledgementNote);
        $acknowledgement = $this->alerts->acknowledgeAlert($idAlert, $actor->id(), $note, $acknowledgedAt);
        $this->alerts->updateAlertStatus($idAlert, 'ACKNOWLEDGED');

        $auditRecord = $this->audit->record(new AuditRecordDraft(
            idActorUser: $actor->id(),
            idAdministrativeAction: null,
            auditType: 'ALERT_ACK',
            actionKey: 'alert.acknowledge',
            validatedParameters: [
                'alertId' => $idAlert,
                'previousStatus' => $alert->status(),
                'acknowledgementNote' => $note,
            ],
            resultStatus: 'SUCCESS',
            exitCode: null,
            failureReason: null,
            requestId: $requestId,
            occurredAt: $acknowledgedAt,
        ));

        return new AlertAcknowledgementResult(
            acknowledgementId: $acknowledgement->id(),
            alertStatus: 'ACKNOWLEDGED',
            auditRecordId: $auditRecord->id(),
        );
    }

    public function comment(
        AuthenticatedUser $actor,
        string $targetType,
        int $targetId,
        string $commentText,
        string $requestId,
        DateTimeImmutable $createdAt
    ): EventCommentResult {
        $authorization = $this->authorization->canCommentEvent($actor);

        if (!$authorization->allowed()) {
            throw new AlertValidationException($authorization->message());
        }

        if (!in_array($targetType, self::COMMENT_TARGET_TYPES, true)) {
            throw new AlertValidationException(sprintf('Comment target type "%s" is not allowed.', $targetType));
        }

        if ($targetId <= 0) {
            throw new AlertValidationException('Comment target id is required.');
        }

        $commentText = $this->sanitizeRequiredText($commentText, 'Comment text');
        $idAlert = $targetType === 'ALERT' ? $targetId : null;
        $idAuditRecord = $targetType === 'AUDIT_RECORD' ? $targetId : null;

        if ($idAlert !== null && $this->alerts->findAlert($idAlert) === null) {
            throw new AlertValidationException(sprintf('Alert "%d" was not found.', $idAlert));
        }

        $comment = $this->alerts->addComment($idAlert, $idAuditRecord, $actor->id(), $commentText, $createdAt);

        $auditRecord = $this->audit->record(new AuditRecordDraft(
            idActorUser: $actor->id(),
            idAdministrativeAction: null,
            auditType: 'EVENT_COMMENT',
            actionKey: 'event.comment',
            validatedParameters: [
                'targetType' => $targetType,
                'targetId' => $targetId,
                'commentText' => $commentText,
            ],
            resultStatus: 'SUCCESS',
            exitCode: null,
            failureReason: null,
            requestId: $requestId,
            occurredAt: $createdAt,
        ));

        return new EventCommentResult(
            commentId: $comment->id(),
            auditRecordId: $auditRecord->id(),
        );
    }

    private function validateAlertDraft(AlertDraft $draft): void
    {
        if (trim($draft->alertSource) === '') {
            throw new AlertValidationException('Alert source is required.');
        }

        if (!in_array($draft->severity, self::ALERT_SEVERITIES, true)) {
            throw new AlertValidationException(sprintf('Alert severity "%s" is not allowed.', $draft->severity));
        }
    }

    private function sanitizeRequiredText(string $text, string $label): string
    {
        $sanitized = $this->sanitizer->sanitizeText($text) ?? '';

        if ($sanitized === '') {
            throw new AlertValidationException(sprintf('%s is required.', $label));
        }

        return $sanitized;
    }

    private function sanitizeOptionalText(?string $text): ?string
    {
        $sanitized = $this->sanitizer->sanitizeText($text);

        return $sanitized === '' ? null : $sanitized;
    }
}
