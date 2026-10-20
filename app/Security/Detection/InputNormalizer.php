<?php

declare(strict_types=1);

namespace App\Security\Detection;

/**
 * Undoes common evasion encodings so signature rules see the payload the
 * application (or browser) will eventually interpret.
 */
final class InputNormalizer
{
    private const MAX_DECODE_ROUNDS = 3;

    public function normalize(string $value): string
    {
        for ($i = 0; $i < self::MAX_DECODE_ROUNDS; $i++) {
            $decoded = html_entity_decode(rawurldecode($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $value) {
                break;
            }

            $value = $decoded;
        }

        // Invalid UTF-8 would make every /u pattern fail silently (an easy evasion), so repair it.
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        // Null bytes and other control characters are used to split keywords.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Collapse all whitespace variants (tabs, newlines, NBSP) into single spaces.
        $value = (string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value);

        return mb_strtolower(trim($value), 'UTF-8');
    }
}
