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
 *
 * #[SoapOperation(description: 'Returns JSON data', contentType: 'application/json')]
 * public function getJson(): string {
 *     return json_encode(['status' => 'ok']);
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
class SoapOperation
{
    /**
     * @param string $description A description of what the operation does
     * @param string $contentType Content-Type for HTTP method responses (default: 'text/plain')
     */
    public function __construct(
        public readonly string $description = '',
        public readonly string $contentType = 'text/plain'
    ) {
    }
}
