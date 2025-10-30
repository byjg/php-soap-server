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
 *     #[SoapParameter(description: 'The name to greet')] string $name
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
     * @param string|null $type Override the type (useful for complex types)
     */
    public function __construct(
        public readonly string $description = '',
        public readonly ?string $type = null
    ) {
    }
}
