<?php

declare(strict_types=1);

namespace TPanel\Audit;

final class AuditDataSanitizer
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SECRET_KEY_PATTERNS = [
        'password',
        'passwd',
        'secret',
        'token',
        'webhook',
        'apiKey',
        'api_key',
        'privateKey',
        'private_key',
        'credential',
        'authorization',
    ];

    /**
     * @param array<string, mixed>|null $parameters
     * @return array<string, mixed>|null
     */
    public function sanitizeParameters(?array $parameters): ?array
    {
        if ($parameters === null) {
            return null;
        }

        $sanitized = [];

        foreach ($parameters as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($key, $value);
        }

        return $sanitized;
    }

    public function sanitizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $sanitized = preg_replace('/(password|passwd|secret|token|webhook|api[_-]?key|authorization)\\s*[=:]\\s*(?:Bearer\\s+)?[^\\s,;]+/i', '$1=' . self::REDACTED, $text);
        $sanitized = preg_replace('/Bearer\\s+[A-Za-z0-9._~+\\/-]+=*/i', 'Bearer ' . self::REDACTED, (string) $sanitized);

        return trim((string) $sanitized);
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        if ($this->isSecretKey($key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeValue((string) $childKey, $childValue);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeText($value);
        }

        return $value;
    }

    private function isSecretKey(string $key): bool
    {
        foreach (self::SECRET_KEY_PATTERNS as $pattern) {
            if (stripos($key, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
