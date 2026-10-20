<?php

declare(strict_types=1);

namespace App\Security\Detection;

final readonly class DetectionResult
{
    public function __construct(
        public string $category,
        public string $ruleId,
        public string $matched,
    ) {
    }
}
