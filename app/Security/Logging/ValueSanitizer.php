<?php

declare(strict_types=1);

namespace App\Security\Logging;

use App\Security\Config\LoggingSettings;

/**
 * Makes untrusted values safe to write into the security log:
 * redacts secrets, strips control characters (log injection) and truncates.
 */
final class ValueSanitizer
{
    private const REDACTED = '[REDACTED]';
    private const MAX_DEPTH = 4;
    private const MAX_ITEMS = 50;

    public function __construct(private readonly LoggingSettings $settings)
    {
    }

    /**
     * @param array<array-key, mixed> $attributes
     * @return array<array-key, mixed>
     */
    public function sanitizeAttributes(array $attributes, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['[TRUNCATED_DEPTH]'];
        }

        $clean = [];
        $count = 0;

        foreach ($attributes as $key => $value) {
            if (++$count > self::MAX_ITEMS) {
                $clean['_truncated_items'] = count($attributes) - self::MAX_ITEMS;
                break;
            }

            $safeKey = is_string($key) ? $this->string($key, 64) : $key;

            if (is_string($key) && $this->isSensitive($key)) {
                $clean[$safeKey] = self::REDACTED;
                continue;
            }

            $clean[$safeKey] = match (true) {
                is_array($value) => $this->sanitizeAttributes($value, $depth + 1),
                is_string($value) => $this->string($value),
                is_int($value), is_float($value), is_bool($value), $value === null => $value,
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \Stringable => $this->string((string) $value),
                default => get_debug_type($value),
            };
        }

        return $clean;
    }

    public function string(string $value, ?int $maxLength = null): string
    {
        $maxLength ??= $this->settings->maxValueLength;

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        // CR/LF and other control chars would let an attacker forge extra log lines.
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

        if (mb_strlen($value) > $maxLength) {
            return mb_substr($value, 0, $maxLength) . '…[+' . (mb_strlen($value) - $maxLength) . ']';
        }

        return $value;
    }

    public function username(string $username): string
    {
        $username = mb_strtolower(trim($username));

        if (!$this->settings->pseudonymizeUsernames) {
            return $this->string($username, 128);
        }

        return 'h:' . substr(hash_hmac('sha256', $username, $this->settings->pseudonymizationKey), 0, 24);
    }

    private function isSensitive(string $key): bool
    {
        return in_array(strtolower($key), $this->settings->sensitiveFields, true);
    }
}
