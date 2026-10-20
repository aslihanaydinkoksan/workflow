<?php

declare(strict_types=1);

namespace App\Security\Contracts;

use App\Security\Enums\SecurityEventType;
use App\Security\Enums\Severity;
use Illuminate\Http\Request;

interface SecurityEventLogger
{
    /**
     * Writes a structured security event. Implementations must never throw:
     * a logging failure must not break the request being handled.
     *
     * @param array<string, mixed> $attributes Event-specific fields. Never pass secrets.
     * @param Request|null $request When null, the current HTTP request (if any) is used for context.
     */
    public function record(
        SecurityEventType $type,
        array $attributes = [],
        ?Severity $severity = null,
        ?Request $request = null,
    ): void;
}
