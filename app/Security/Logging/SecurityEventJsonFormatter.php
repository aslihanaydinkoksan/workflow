<?php

declare(strict_types=1);

namespace App\Security\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Emits one flat JSON object per security event (the record context only),
 * which SIEM parsers handle more reliably than Monolog's nested envelope.
 */
final class SecurityEventJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $payload = $record->context !== [] ? $record->context : ['message' => $record->message];

        $normalized = $this->normalize($payload);

        return $this->toJson($normalized, true) . ($this->appendNewline ? "\n" : '');
    }
}
