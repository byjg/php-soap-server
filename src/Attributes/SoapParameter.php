<?php

declare(strict_types=1);

namespace ByJG\SoapServer\Attributes;

use Attribute;

/**
 * SoapParameter attribute for providing additional metadata about operation parameters
 *
 * This attribute is optional and can be used at the parameter level to provide
 * additional information beyond what can be inferred from type hints.
 *
 * Example:
 * ```php
 * public function greet(
 *     #[SoapParameter(description: 'The name to greet', minOccurs: 1)] string $name,
 *     #[SoapParameter(description: 'Optional title', minOccurs: 0)] ?string $title = null
 * ): string {
 *     return "Hello, $name!";
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class SoapParameter
{
    /**
     * @param string $description A description of the parameter
     * @param int|null $minOccurs Minimum occurrences (0 = optional, 1 = required)
     * @param int|null $maxOccurs Maximum occurrences (1 = single value, -1 = unbounded array)
     * @param string|null $type Override the type (useful for complex types)
     */
    public function __construct(
        public readonly string $description = '',
        public readonly ?int $minOccurs = null,
        public readonly ?int $maxOccurs = null,
        public readonly ?string $type = null
    ) {
    }
}
