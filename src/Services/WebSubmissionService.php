<?php

declare(strict_types=1);

namespace TPanel\Services;

use DateTimeImmutable;
use TPanel\Alerts\AlertValidationException;
use TPanel\Audit\AuditRecordDraft;
use TPanel\Command\AuthorizedCommandExecutor;
use TPanel\Command\AuthorizedCommandRequest;
use TPanel\Command\AuthorizedCommandResult;
use TPanel\Security\ActionAuthorizationService;
use TPanel\Security\AuthenticatedUser;

final class WebSubmissionService
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly AuditService $auditService,
        private readonly AuthorizedCommandExecutor $commandExecutor = new AuthorizedCommandExecutor(),
        private readonly ActionAuthorizationService $authorization = new ActionAuthorizationService(),
        private readonly ?array $commandCatalog = null,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function handle(AuthenticatedUser $actor, array $post): WebSubmissionResult
    {
        if (isset($post['actionKey'])) {
            return $this->handleAdministrativeAction($actor, $post);
        }

        if (isset($post['alertId'])) {
            return $this->handleAlertAcknowledgement($actor, $post);
        }

        if (isset($post['targetType'], $post['targetId'], $post['commentText'])) {
            return $this->handleEventComment($actor, $post);
        }

        return new WebSubmissionResult(
            requestId: $this->stringValue($post['requestId'] ?? ''),
            resultStatus: AuthorizedCommandResult::DENIED,
            message: 'POST payload is not recognized by TPanel.',
            auditRecordId: null,
            exitCode: null,
        );
    }

    /**
     * @param array<string, mixed> $post
     */
    private function handleAdministrativeAction(AuthenticatedUser $actor, array $post): WebSubmissionResult
    {
        $requestId = $this->requiredString($post, 'requestId');
        $actionKey = $this->requiredString($post, 'actionKey');
        $catalog = $this->commandCatalog();
        $catalogEntry = $this->catalogEntry($catalog, $actionKey);

        if (!is_array($catalogEntry)) {
            return $this->deniedAuditResult($actor, $requestId, $actionKey, [], 'Administrative action is not in the authorized catalog.');
        }

        $parameters = $this->stringMap($post['parameters'] ?? []);
        $decision = $this->authorization->canExecuteAdministrativeAction($actor, $catalogEntry);

        if (!$decision->allowed()) {
            return $this->deniedAuditResult($actor, $requestId, $actionKey, $parameters, $decision->message());
        }

        if (($catalogEntry['requiresConfirmation'] ?? true) === true && $this->stringValue($post['confirmationAccepted'] ?? '') !== '1') {
            return $this->deniedAuditResult($actor, $requestId, $actionKey, $parameters, 'Confirmation is required before executing this administrative action.');
        }

        $validation = $this->commandExecutor->validateParameters($catalogEntry, $parameters);

        if (!$validation->valid()) {
            return $this->deniedAuditResult($actor, $requestId, $actionKey, $parameters, $validation->message());
        }

        $commandRequest = AuthorizedCommandRequest::fromCatalog(
            requestId: $requestId,
            actorUsername: $actor->externalUsername(),
            actionKey: $actionKey,
            catalogEntry: $catalogEntry,
            validatedParameters: $validation->validatedParameters(),
            auditContext: ['sourcePage' => 'dashboard']
        );
        $commandResult = $this->commandExecutor->execute($commandRequest);
        $auditRecord = $this->auditService->record(new AuditRecordDraft(
            idActorUser: $actor->id(),
            idAdministrativeAction: null,
            auditType: $commandResult->resultStatus() === AuthorizedCommandResult::DENIED ? 'DENIED_ACTION' : 'ADMIN_ACTION',
            actionKey: $actionKey,
            validatedParameters: $validation->validatedParameters(),
            resultStatus: $commandResult->resultStatus(),
            exitCode: $commandResult->exitCode(),
            failureReason: $commandResult->failureReason(),
            requestId: $requestId,
            occurredAt: $commandResult->finishedAt() ?? new DateTimeImmutable(),
        ));

        return new WebSubmissionResult(
            requestId: $requestId,
            resultStatus: $commandResult->resultStatus(),
            message: $this->commandMessage($commandResult),
            auditRecordId: $auditRecord->id(),
            exitCode: $commandResult->exitCode(),
        );
    }

    /**
     * @param array<string, mixed> $post
     */
    private function handleAlertAcknowledgement(AuthenticatedUser $actor, array $post): WebSubmissionResult
    {
        $requestId = $this->requiredString($post, 'requestId');

        try {
            $result = $this->alertService->acknowledge(
                actor: $actor,
                idAlert: $this->positiveInt($post['alertId'] ?? null, 'alertId'),
                acknowledgementNote: $this->stringValue($post['acknowledgementNote'] ?? null),
                requestId: $requestId,
                acknowledgedAt: new DateTimeImmutable(),
            );

            return new WebSubmissionResult(
                requestId: $requestId,
                resultStatus: AuthorizedCommandResult::SUCCESS,
                message: sprintf('Alert acknowledged with status %s.', $result->alertStatus),
                auditRecordId: $result->auditRecordId,
                exitCode: null,
            );
        } catch (AlertValidationException $exception) {
            return new WebSubmissionResult(
                requestId: $requestId,
                resultStatus: AuthorizedCommandResult::DENIED,
                message: $exception->getMessage(),
                auditRecordId: null,
                exitCode: null,
            );
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    private function handleEventComment(AuthenticatedUser $actor, array $post): WebSubmissionResult
    {
        $requestId = $this->requiredString($post, 'requestId');

        try {
            $result = $this->alertService->comment(
                actor: $actor,
                targetType: $this->requiredString($post, 'targetType'),
                targetId: $this->positiveInt($post['targetId'] ?? null, 'targetId'),
                commentText: $this->requiredString($post, 'commentText'),
                requestId: $requestId,
                createdAt: new DateTimeImmutable(),
            );

            return new WebSubmissionResult(
                requestId: $requestId,
                resultStatus: AuthorizedCommandResult::SUCCESS,
                message: sprintf('Comment #%d registered.', $result->commentId),
                auditRecordId: $result->auditRecordId,
                exitCode: null,
            );
        } catch (AlertValidationException $exception) {
            return new WebSubmissionResult(
                requestId: $requestId,
                resultStatus: AuthorizedCommandResult::DENIED,
                message: $exception->getMessage(),
                auditRecordId: null,
                exitCode: null,
            );
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function deniedAuditResult(
        AuthenticatedUser $actor,
        string $requestId,
        string $actionKey,
        array $parameters,
        string $reason
    ): WebSubmissionResult {
        $auditRecord = $this->auditService->record(new AuditRecordDraft(
            idActorUser: $actor->id(),
            idAdministrativeAction: null,
            auditType: 'DENIED_ACTION',
            actionKey: $actionKey,
            validatedParameters: $parameters,
            resultStatus: AuthorizedCommandResult::DENIED,
            exitCode: null,
            failureReason: $reason,
            requestId: $requestId,
            occurredAt: new DateTimeImmutable(),
        ));

        return new WebSubmissionResult(
            requestId: $requestId,
            resultStatus: AuthorizedCommandResult::DENIED,
            message: $reason,
            auditRecordId: $auditRecord->id(),
            exitCode: null,
        );
    }

    private function commandMessage(AuthorizedCommandResult $result): string
    {
        if ($result->resultStatus() === AuthorizedCommandResult::SUCCESS) {
            return $result->stdoutSummary() ?? 'Administrative action executed successfully.';
        }

        return $result->failureReason()
            ?? $result->stderrSummary()
            ?? 'Administrative action did not complete successfully.';
    }

    /**
     * @return array<string, mixed>
     */
    private function commandCatalog(): array
    {
        if ($this->commandCatalog !== null) {
            return $this->commandCatalog;
        }

        return require dirname(__DIR__, 2) . '/config/commands.php.model';
    }

    /**
     * @param array<string, mixed> $catalog
     * @return array<string, mixed>|null
     */
    private function catalogEntry(array $catalog, string $actionKey): ?array
    {
        $entry = $catalog['commands'][$actionKey] ?? null;

        if (!is_array($entry)) {
            return null;
        }

        $defaults = $catalog['defaults'] ?? [];

        if (!is_array($defaults)) {
            $defaults = [];
        }

        return array_replace($defaults, $entry);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function requiredString(array $post, string $field): string
    {
        $value = $this->stringValue($post[$field] ?? null);

        if ($value === '') {
            throw new \InvalidArgumentException(sprintf('POST field "%s" is required.', $field));
        }

        return $value;
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_scalar($item)) {
                continue;
            }

            $result[$key] = trim((string) $item);
        }

        return $result;
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            throw new AlertValidationException(sprintf('%s is required.', $field));
        }

        return (int) $value;
    }
}
