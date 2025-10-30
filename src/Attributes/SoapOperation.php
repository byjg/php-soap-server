<?php

declare(strict_types=1);

namespace ByJG\SoapServer\Attributes;

use Attribute;

/**
 * SoapOperation attribute for marking a method as a SOAP operation
 *
 * This attribute is used at the method level to define SOAP operation metadata.
 *
 * Example:
 * ```php
 * #[SoapOperation(description: 'Adds two numbers together')]
 * public function add(int $a, int $b): int {
 *     return $a + $b;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
class SoapOperation
{
    /**
     * @param string $description A description of what the operation does
     */
    public function __construct(
        public readonly string $description = ''
    ) {
    }
}
