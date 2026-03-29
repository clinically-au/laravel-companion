<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

final class ConfigRedactionService
{
    private const REDACTED = '********';

    /**
     * Redact sensitive values from a config array.
     *
     * @param  array<string, mixed>  $config
     * @param  string  $prefix  Dot-notation prefix for matching rules.
     * @return array<string, mixed>
     */
    public function redact(array $config, string $prefix = ''): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

            if (is_array($value)) {
                $result[$key] = $this->redact($value, $fullKey);
            } elseif ($this->shouldRedact($fullKey, (string) $key)) {
                $result[$key] = self::REDACTED;
                $result["_{$key}_redacted"] = true;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Redact a single value by its full dot-notation key.
     */
    public function redactValue(string $key, mixed $value): mixed
    {
        $lastSegment = (string) last(explode('.', $key));

        if ($this->shouldRedact($key, $lastSegment)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            return $this->redact($value, $key);
        }

        return $value;
    }

    private function shouldRedact(string $fullKey, string $leafKey): bool
    {
        // Never redact explicitly safe keys
        $neverRedact = (array) config('companion.config_redaction.never_redact', []);
        if (in_array($fullKey, $neverRedact, true)) {
            return false;
        }

        // Always redact explicitly sensitive keys (supports wildcards)
        $alwaysRedact = (array) config('companion.config_redaction.always_redact', []);
        foreach ($alwaysRedact as $pattern) {
            if ($this->matchesWildcard($fullKey, $pattern)) {
                return true;
            }
        }

        // Check patterns against the leaf key name
        $patterns = (array) config('companion.config_redaction.patterns', []);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $leafKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a dot-notation key against a pattern with * wildcards.
     * e.g. "database.connections.*.password" matches "database.connections.mysql.password"
     */
    private function matchesWildcard(string $key, string $pattern): bool
    {
        $regex = str_replace(['.', '*'], ['\.', '[^.]+'], $pattern);

        return (bool) preg_match("/^{$regex}$/", $key);
    }
}
