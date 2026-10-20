<?php

declare(strict_types=1);

namespace App\Security\Contracts;

use App\Security\Detection\DetectionResult;

interface PayloadDetector
{
    /**
     * Inspects one input value and returns the first matching rule, or null.
     * Implementations must be pure (no I/O) so they stay cheap and testable.
     */
    public function detect(string $value): ?DetectionResult;
}
