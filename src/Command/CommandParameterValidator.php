<?php

declare(strict_types=1);

namespace TPanel\Command;

final class CommandParameterValidator
{
    /**
     * @param array<string, mixed> $catalogEntry
     * @param array<string, mixed> $parameters
     */
    public function validate(array $catalogEntry, array $parameters): CommandParameterValidationResult
    {
        $schema = $catalogEntry['allowedParametersSchema'] ?? null;

        if (!is_array($schema)) {
            return CommandParameterValidationResult::invalid('Command catalog entry has no parameter schema.');
        }

        foreach ($parameters as $parameterName => $_) {
            if (!is_string($parameterName) || !array_key_exists($parameterName, $schema)) {
                return CommandParameterValidationResult::invalid(
                    sprintf('Parameter "%s" is not allowed.', (string) $parameterName)
                );
            }
        }

        $validatedParameters = [];

        foreach ($schema as $parameterName => $definition) {
            if (!is_string($parameterName) || !is_array($definition)) {
                return CommandParameterValidationResult::invalid('Command catalog parameter schema is invalid.');
            }

            if (!array_key_exists($parameterName, $parameters)) {
                return CommandParameterValidationResult::invalid(
                    sprintf('Parameter "%s" is required.', $parameterName)
                );
            }

            $value = $parameters[$parameterName];
            $type = $definition['type'] ?? null;

            if ($type === 'string') {
                $result = $this->validateString($parameterName, $value, $definition);
            } elseif ($type === 'enum') {
                $result = $this->validateEnum($parameterName, $value, $definition);
            } else {
                return CommandParameterValidationResult::invalid(
                    sprintf('Parameter "%s" has unsupported schema type.', $parameterName)
                );
            }

            if (!$result->valid()) {
                return $result;
            }

            $validatedParameters[$parameterName] = $result->validatedParameters()[$parameterName];
        }

        return CommandParameterValidationResult::success($validatedParameters);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $definition
     */
    private function validateString(
        string $parameterName,
        mixed $value,
        array $definition
    ): CommandParameterValidationResult {
        if (!is_string($value) || $value === '') {
            return CommandParameterValidationResult::invalid(
                sprintf('Parameter "%s" must be a non-empty string.', $parameterName)
            );
        }

        $maxLength = $definition['maxLength'] ?? null;

        if (is_int($maxLength) && strlen($value) > $maxLength) {
            return CommandParameterValidationResult::invalid(
                sprintf('Parameter "%s" exceeds maximum length.', $parameterName)
            );
        }

        $pattern = $definition['pattern'] ?? null;

        if (is_string($pattern) && preg_match('~' . str_replace('~', '\~', $pattern) . '~', $value) !== 1) {
            return CommandParameterValidationResult::invalid(
                sprintf('Parameter "%s" does not match the allowed pattern.', $parameterName)
            );
        }

        return CommandParameterValidationResult::success([$parameterName => $value]);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $definition
     */
    private function validateEnum(
        string $parameterName,
        mixed $value,
        array $definition
    ): CommandParameterValidationResult {
        $allowedValues = $definition['values'] ?? null;

        if (!is_string($value) || !is_array($allowedValues) || !in_array($value, $allowedValues, true)) {
            return CommandParameterValidationResult::invalid(
                sprintf('Parameter "%s" is not an allowed value.', $parameterName)
            );
        }

        return CommandParameterValidationResult::success([$parameterName => $value]);
    }
}
