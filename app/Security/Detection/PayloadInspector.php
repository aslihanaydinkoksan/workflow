<?php

declare(strict_types=1);

namespace App\Security\Detection;

use App\Security\Config\InspectionSettings;
use App\Security\Contracts\PayloadDetector;

/**
 * Walks request input (framework-agnostic arrays) and applies every detector to
 * every value and key. Has no knowledge of HTTP, so it is unit-testable in isolation.
 */
final class PayloadInspector
{
    private const MAX_DEPTH = 8;
    private const MAX_FIELDS = 500;

    /** @var list<PayloadDetector> */
    private readonly array $detectors;

    /**
     * @param iterable<PayloadDetector> $detectors
     */
    public function __construct(iterable $detectors, private readonly InspectionSettings $settings)
    {
        $this->detectors = is_array($detectors) ? array_values($detectors) : iterator_to_array($detectors, false);
    }

    /**
     * @param array<string, mixed> $sources location => input, e.g. ['query' => [...], 'body' => [...], 'path' => '/x']
     * @return list<InspectionFinding>
     */
    public function inspect(array $sources): array
    {
        $findings = [];
        $budget = self::MAX_FIELDS;

        foreach ($sources as $location => $input) {
            $this->walk((string) $location, '', $input, 0, $findings, $budget);
        }

        return $findings;
    }

    /**
     * @param list<InspectionFinding> $findings
     */
    private function walk(string $location, string $path, mixed $value, int $depth, array &$findings, int &$budget): void
    {
        if ($budget <= 0 || $depth > self::MAX_DEPTH) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $key = (string) $key;

                if ($this->settings->isFieldExcluded($key)) {
                    continue;
                }

                $childPath = $path === '' ? $key : $path . '.' . $key;

                // Parameter names are attacker-controlled too (e.g. ?id'or'1'='1=x).
                if (!ctype_digit($key)) {
                    $this->check($location, $childPath . '[key]', $key, $findings);
                }

                $this->walk($location, $childPath, $child, $depth + 1, $findings, $budget);
            }

            return;
        }

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return;
        }

        $budget--;
        $this->check($location, $path === '' ? $location : $path, (string) $value, $findings);
    }

    /**
     * @param list<InspectionFinding> $findings
     */
    private function check(string $location, string $field, string $value, array &$findings): void
    {
        if ($value === '' || is_numeric($value)) {
            return;
        }

        $value = substr($value, 0, $this->settings->maxInspectLength);

        foreach ($this->detectors as $detector) {
            $result = $detector->detect($value);

            if ($result !== null) {
                $findings[] = new InspectionFinding($location, $field, $result);
            }
        }
    }
}
