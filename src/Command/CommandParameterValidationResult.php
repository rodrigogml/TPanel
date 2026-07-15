<?php

declare(strict_types=1);

namespace TPanel\Command;

final class CommandParameterValidationResult
{
    public const VALID = 'VALID';
    public const PARAMETER_INVALID = 'PARAMETER_INVALID';

    /**
     * @param array<string, string> $validatedParameters
     */
    private function __construct(
        private readonly bool $valid,
        private readonly string $reasonCode,
        private readonly string $message,
        private readonly array $validatedParameters,
    ) {
    }

    /**
     * @param array<string, string> $validatedParameters
     */
    public static function success(array $validatedParameters): self
    {
        return new self(true, self::VALID, 'Parameters are valid.', $validatedParameters);
    }

    public static function invalid(string $message): self
    {
        return new self(false, self::PARAMETER_INVALID, $message, []);
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, string>
     */
    public function validatedParameters(): array
    {
        return $this->validatedParameters;
    }
}
