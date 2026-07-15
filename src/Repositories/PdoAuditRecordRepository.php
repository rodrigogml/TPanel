<?php

declare(strict_types=1);

namespace TPanel\Repositories;

use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;
use TPanel\Audit\AuditRecord;
use TPanel\Audit\AuditRecordDraft;

final class PdoAuditRecordRepository implements AuditRecordRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function append(AuditRecordDraft $draft): AuditRecord
    {
        $validatedParameters = $this->encodeParameters($draft->validatedParameters);

        $statement = $this->pdo->prepare(
            'INSERT INTO auditRecord (
                idActorUser,
                idAdministrativeAction,
                auditType,
                actionKey,
                validatedParameters,
                resultStatus,
                exitCode,
                failureReason,
                requestId,
                occurredAt
            ) VALUES (
                :idActorUser,
                :idAdministrativeAction,
                :auditType,
                :actionKey,
                :validatedParameters,
                :resultStatus,
                :exitCode,
                :failureReason,
                :requestId,
                :occurredAt
            )'
        );

        $statement->execute([
            'idActorUser' => $draft->idActorUser,
            'idAdministrativeAction' => $draft->idAdministrativeAction,
            'auditType' => $draft->auditType,
            'actionKey' => $draft->actionKey,
            'validatedParameters' => $validatedParameters,
            'resultStatus' => $draft->resultStatus,
            'exitCode' => $draft->exitCode,
            'failureReason' => $draft->failureReason,
            'requestId' => $draft->requestId,
            'occurredAt' => $draft->occurredAt->format('Y-m-d H:i:s'),
        ]);

        return new AuditRecord(
            id: (int) $this->pdo->lastInsertId(),
            idActorUser: $draft->idActorUser,
            idAdministrativeAction: $draft->idAdministrativeAction,
            auditType: $draft->auditType,
            actionKey: $draft->actionKey,
            validatedParameters: $draft->validatedParameters,
            resultStatus: $draft->resultStatus,
            exitCode: $draft->exitCode,
            failureReason: $draft->failureReason,
            requestId: $draft->requestId,
            occurredAt: new DateTimeImmutable($draft->occurredAt->format('Y-m-d H:i:s')),
        );
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    private function encodeParameters(?array $parameters): ?string
    {
        if ($parameters === null) {
            return null;
        }

        try {
            return json_encode($parameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode audit parameters as JSON.', previous: $exception);
        }
    }
}
