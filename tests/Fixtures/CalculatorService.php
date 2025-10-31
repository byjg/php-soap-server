<?php

namespace Test\Fixtures;

use ByJG\SoapServer\Attributes\SoapOperation;
use ByJG\SoapServer\Attributes\SoapParameter;
use ByJG\SoapServer\Attributes\SoapService;

/**
 * Example SOAP service for testing
 */
#[SoapService(
    serviceName: 'CalculatorService',
    namespace: 'http://example.com/calculator',
    description: 'A simple calculator service',
    options: ['soap_version' => SOAP_1_2]
)]
class CalculatorService
{
    #[SoapOperation(description: 'Adds two numbers')]
    public function add(
        #[SoapParameter(description: 'First number')] int  $a,
        #[SoapParameter(description: 'Second number')] int $b
    ): int
    {
        return $a + $b;
    }

    #[SoapOperation(description: 'Subtracts two numbers')]
    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    #[SoapOperation(description: 'Multiplies two numbers')]
    public function multiply(float $a, float $b): float
    {
        return $a * $b;
    }

    #[SoapOperation(description: 'Divides two numbers')]
    public function divide(float $a, float $b): ?float
    {
        if ($b === 0.0) {
            return null;
        }
        return $a / $b;
    }

    #[SoapOperation(description: 'Greets a person')]
    public function greet(string $name = 'World'): string
    {
        return "Hello, $name!";
    }

    // Method without SoapOperation attribute - should be ignored
    public function internalMethod(): void
    {
    }
}
