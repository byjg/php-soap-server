<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/CalculatorObject.php';

use ByJG\SoapServer\Attributes\SoapOperation;
use ByJG\SoapServer\Attributes\SoapParameter;
use ByJG\SoapServer\Attributes\SoapService;
use ByJG\SoapServer\ResponseWriter;
use ByJG\SoapServer\SoapAttributeParser;

/**
 * Example: Simple Calculator SOAP Service using PHP Attributes
 *
 * This example demonstrates how to create a SOAP service using the
 * attribute-based approach provided by byjg/soap-server.
 */

#[SoapService(
    serviceName: 'CalculatorService',
    namespace: 'http://example.com/calculator',
    description: 'A simple calculator web service',
    options: ['soap_version' => SOAP_1_2]
)]
class Calculator
{
    #[SoapOperation(description: 'Adds two numbers together')]
    public function add(
        #[SoapParameter(description: 'The first number to add')] int $a,
        #[SoapParameter(description: 'The second number to add')] int $b
    ): int {
        return $a + $b;
    }

    #[SoapOperation(description: 'Subtracts the second number from the first')]
    public function subtract(
        #[SoapParameter(description: 'The number to subtract from')] int $a,
        #[SoapParameter(description: 'The number to subtract')] int $b
    ): int {
        return $a - $b;
    }

    #[SoapOperation(description: 'Multiplies two numbers')]
    public function multiply(float $a, float $b): float
    {
        return $a * $b;
    }

    #[SoapOperation(description: 'Divides the first number by the second')]
    public function divide(float $numerator, float $denominator): ?float
    {
        if ($denominator === 0.0) {
            return null;
        }
        return $numerator / $denominator;
    }

    #[SoapOperation(description: 'Returns a complex object')]
    public function complex(int $a, int $b): CalculatorObject
    {
        $calculator = new CalculatorObject();
        $calculator->a = $a;
        $calculator->b = $b;
        $calculator->result = $a + $b;
        return $calculator;
    }
}

// Parse the service
$handler = SoapAttributeParser::parse(Calculator::class);
$response = $handler->handle();
ResponseWriter::output($response);
