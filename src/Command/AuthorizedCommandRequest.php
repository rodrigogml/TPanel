<?php

declare(strict_types=1);

namespace TPanel\Command;

use InvalidArgumentException;

final class AuthorizedCommandRequest
{
    /**
     * @param array<string, string> $parameters
     * @param array<string, string> $auditContext
     */
    private function __construct(
        private readonly string $requestId,
        private readonly string $actorUsername,
        private readonly string $actionKey,
        private readonly string $commandKey,
        private readonly string $executablePath,
        private readonly array $parameters,
        private readonly int $timeoutSeconds,
        private readonly array $auditContext,
        private readonly ?string $runAsUser,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $catalogEntry
     * @param array<string, string> $validatedParameters
     * @param array<string, string> $auditContext
     */
    public static function fromCatalog(
        string $requestId,
        string $actorUsername,
        string $actionKey,
        array $catalogEntry,
        array $validatedParameters,
        array $auditContext
    ): self {
        return new self(
            requestId: $requestId,
            actorUsername: $actorUsername,
            actionKey: $actionKey,
            commandKey: self::requiredString($catalogEntry, 'commandKey'),
            executablePath: self::requiredString($catalogEntry, 'executablePath'),
            parameters: $validatedParameters,
            timeoutSeconds: self::requiredPositiveInteger($catalogEntry, 'timeoutSeconds'),
            auditContext: $auditContext,
            runAsUser: self::optionalString($catalogEntry, 'runAsUser'),
        );
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function actorUsername(): string
    {
        return $this->actorUsername;
    }

    public function actionKey(): string
    {
        return $this->actionKey;
    }

    public function commandKey(): string
    {
        return $this->commandKey;
    }

    public function executablePath(): string
    {
        return $this->executablePath;
    }

    /**
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function runAsUser(): ?string
    {
        return $this->runAsUser;
    }

    /**
     * @return array<string, string>
     */
    public function auditContext(): array
    {
        return $this->auditContext;
    }

    /**
     * @return list<string>
     */
    public function processArguments(): array
    {
        $arguments = array_merge([$this->executablePath], array_values($this->parameters));

        if ($this->runAsUser === null) {
            return $arguments;
        }

        return array_merge(['/usr/bin/sudo', '-n', '-u', $this->runAsUser, '--'], $arguments);
    }

    private function validate(): void
    {
        foreach ([
            'requestId' => $this->requestId,
            'actorUsername' => $this->actorUsername,
            'actionKey' => $this->actionKey,
            'commandKey' => $this->commandKey,
            'executablePath' => $this->executablePath,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Authorized command request field "%s" is required.', $field));
            }
        }

        if ($this->timeoutSeconds <= 0) {
            throw new InvalidArgumentException('Authorized command request timeout must be positive.');
        }
    }

    /**
     * @param array<string, mixed> $catalogEntry
     */
    private static function requiredString(array $catalogEntry, string $key): string
    {
        $value = $catalogEntry[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Command catalog field "%s" is required.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $catalogEntry
     */
    private static function requiredPositiveInteger(array $catalogEntry, string $key): int
    {
        $value = $catalogEntry[$key] ?? null;

        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException(sprintf('Command catalog field "%s" must be a positive integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $catalogEntry
     */
    private static function optionalString(array $catalogEntry, string $key): ?string
    {
        $value = $catalogEntry[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Command catalog field "%s" must be a non-empty string when provided.', $key));
        }

        return trim($value);
    }
}
