<?php

declare(strict_types=1);

namespace App\Security\Detection;

final readonly class InspectionFinding
{
    public function __construct(
        public string $location,
        public string $field,
        public DetectionResult $result,
    ) {
    }
}
