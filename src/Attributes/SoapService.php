<?php

declare(strict_types=1);

namespace ByJG\SoapServer\Attributes;

use Attribute;

/**
 * SoapService attribute for marking a class as a SOAP service
 *
 * This attribute is used at the class level to define SOAP service metadata.
 *
 * Example:
 * ```php
 * #[SoapService(
 *     serviceName: 'MyService',
 *     namespace: 'http://example.com/soap',
 *     description: 'My SOAP Service',
 *     options: ['soap_version' => SOAP_1_2]
 * )]
 * class MyService {
 *     // ...
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class SoapService
{
    /**
     * @param string $serviceName The name of the SOAP service
     * @param string $namespace The XML namespace for the service
     * @param string $description A description of what the service does
     * @param array<string, mixed> $options Additional SOAP options (e.g., soap_version, encoding, etc.)
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly string $namespace,
        public readonly string $description = '',
        public readonly array $options = []
    ) {
    }
}
