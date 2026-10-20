<?php

declare(strict_types=1);

namespace App\Security\Enums;

use Psr\Log\LogLevel;

enum Severity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function logLevel(): string
    {
        return match ($this) {
            self::Info, self::Low => LogLevel::INFO,
            self::Medium => LogLevel::NOTICE,
            self::High => LogLevel::WARNING,
            self::Critical => LogLevel::CRITICAL,
        };
    }
}
