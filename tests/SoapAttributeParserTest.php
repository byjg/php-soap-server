<?php

declare(strict_types=1);

namespace Test;

use ByJG\SoapServer\Attributes\SoapOperation;
use ByJG\SoapServer\Attributes\SoapParameter;
use ByJG\SoapServer\Attributes\SoapService;
use ByJG\SoapServer\Exception\InvalidServiceException;
use ByJG\SoapServer\SoapAttributeParser;
use ByJG\SoapServer\SoapHandler;
use ByJG\SoapServer\SoapOperationConfig;
use ByJG\SoapServer\SoapParameterConfig;
use ByJG\SoapServer\SoapType;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

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
        #[SoapParameter(description: 'First number')] int $a,
        #[SoapParameter(description: 'Second number')] int $b
    ): int {
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

/**
 * Service without SoapService attribute for testing error handling
 */
class InvalidService
{
    public function someMethod(): void
    {
    }
}

/**
 * Test suite for SoapAttributeParser
 */
class SoapAttributeParserTest extends TestCase
{
    private SoapAttributeParser $parser;

    #[Override]
    protected function setUp(): void
    {
        $this->parser = new SoapAttributeParser();
    }

    public function testCanParseServiceMetadata(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        $this->assertInstanceOf(SoapHandler::class, $handler);
        // Use public properties
        $this->assertEquals('http://example.com/calculator', $handler->namespace);
        $this->assertEquals('A simple calculator service', $handler->description);
        $this->assertEquals(
            [
                'soap_version' => SOAP_1_2,
                'uri' => 'http://example.com/calculator',
                'encoding' => 1
            ],
            $handler->soapServerOptions
        );
    }

    public function testCanParseOperations(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access the soapItems through reflection since it's private
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Should have 5 operations (excluding internalMethod)
        $this->assertCount(5, $soapItems);

        // Check operation names
        $this->assertArrayHasKey('add', $soapItems);
        $this->assertArrayHasKey('subtract', $soapItems);
        $this->assertArrayHasKey('multiply', $soapItems);
        $this->assertArrayHasKey('divide', $soapItems);
        $this->assertArrayHasKey('greet', $soapItems);
        $this->assertArrayNotHasKey('internalMethod', $soapItems);
    }

    public function testCanParseOperationDetails(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access soapItems
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Check 'add' operation
        $addOperation = $soapItems['add'];
        $this->assertEquals('Adds two numbers', $addOperation->description);
        $this->assertEquals(SoapType::Integer, $addOperation->returnType);
    }

    public function testCanParseParameters(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access soapItems
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Check 'add' operation parameters
        $addOperation = $soapItems['add'];
        $this->assertCount(2, $addOperation->args);
        $this->assertIsArray($addOperation->args);

        // Check first parameter
        /** @var SoapParameterConfig $paramA */
        $paramA = $addOperation->args[0];
        $this->assertEquals('a', $paramA->name);
        $this->assertEquals(SoapType::Integer, $paramA->type);
        $this->assertEquals(1, $paramA->minOccurs); // Required

        // Check second parameter
        /** @var SoapParameterConfig $paramB */
        $paramB = $addOperation->args[1];
        $this->assertEquals('b', $paramB->name);
        $this->assertEquals(SoapType::Integer, $paramB->type);
        $this->assertEquals(1, $paramB->minOccurs); // Required
    }

    public function testCanParseOptionalParameters(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access soapItems
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Check 'greet' operation
        $greetOperation = $soapItems['greet'];
        $this->assertCount(1, $greetOperation->args);

        /** @var SoapParameterConfig $param */
        $param = $greetOperation->args[0];
        $this->assertEquals('name', $param->name);
        $this->assertEquals(SoapType::String, $param->type);
        $this->assertEquals(0, $param->minOccurs); // Optional
    }

    public function testCanParseFloatParameters(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access soapItems
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Check 'multiply' operation
        $multiplyOperation = $soapItems['multiply'];
        $this->assertCount(2, $multiplyOperation->args);

        /** @var SoapParameterConfig $paramA */
        $paramA = $multiplyOperation->args[0];
        $this->assertEquals('a', $paramA->name);
        $this->assertEquals(SoapType::Float, $paramA->type);

        $this->assertEquals(SoapType::Float, $multiplyOperation->returnType);
    }

    public function testCanParseNullableReturnType(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access soapItems
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Check 'divide' operation
        $divideOperation = $soapItems['divide'];
        $this->assertEquals(SoapType::Float, $divideOperation->returnType);
    }

    public function testThrowsExceptionForInvalidService(): void
    {
        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage('must have a #[SoapService] attribute');

        $this->parser->parse(InvalidService::class);
    }

    public function testExecutorWorks(): void
    {
        $handler = $this->parser->parse(CalculatorService::class);

        // Access soapItems
        $reflection = new ReflectionObject($handler);
        $property = $reflection->getProperty('soapItems');
        //$property->setAccessible(true);
        /** @var array<string, SoapOperationConfig> $soapItems */
        $soapItems = $property->getValue($handler);

        // Test 'add' operation executor
        $addOperation = $soapItems['add'];
        $result = ($addOperation->executor)(['a' => 10, 'b' => 5]);
        $this->assertEquals(15, $result);

        // Test 'greet' operation executor
        $greetOperation = $soapItems['greet'];
        $result = ($greetOperation->executor)(['name' => 'Alice']);
        $this->assertEquals('Hello, Alice!', $result);
    }
}
