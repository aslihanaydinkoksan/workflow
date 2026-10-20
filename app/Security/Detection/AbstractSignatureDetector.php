<?php

declare(strict_types=1);

namespace App\Security\Detection;

use App\Security\Contracts\PayloadDetector;

/**
 * Runs a fixed, ordered list of regex signatures against normalized input.
 * Signature detection is a monitoring aid, not a defence: the real fixes are
 * parameterized queries and contextual output encoding.
 */
abstract class AbstractSignatureDetector implements PayloadDetector
{
    private const MATCH_PREVIEW_LENGTH = 120;

    public function __construct(protected readonly InputNormalizer $normalizer)
    {
    }

    abstract protected function category(): string;

    /**
     * @return array<string, string> rule id => PCRE pattern (applied to lower-cased input)
     */
    abstract protected function rules(): array;

    /**
     * Additional normalized views of the input (e.g. with SQL comments removed).
     *
     * @return list<string>
     */
    protected function variants(string $normalized): array
    {
        return [$normalized];
    }

    public function detect(string $value): ?DetectionResult
    {
        if (trim($value) === '') {
            return null;
        }

        foreach ($this->variants($this->normalizer->normalize($value)) as $candidate) {
            foreach ($this->rules() as $ruleId => $pattern) {
                if (preg_match($pattern, $candidate, $match) === 1) {
                    return new DetectionResult(
                        category: $this->category(),
                        ruleId: $ruleId,
                        matched: mb_substr($match[0], 0, self::MATCH_PREVIEW_LENGTH),
                    );
                }
            }
        }

        return null;
    }
}
