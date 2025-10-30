<?php

declare(strict_types=1);

namespace ByJG\SoapServer;

use ByJG\SoapServer\Attributes\SoapOperation;
use ByJG\SoapServer\Attributes\SoapParameter as SoapParameterAttribute;
use ByJG\SoapServer\Attributes\SoapService;
use ByJG\SoapServer\Exception\InvalidServiceException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * SoapAttributeParser - Parses PHP attributes to create SOAP service configuration
 *
 * This class uses reflection to analyze a class annotated with SoapService attribute,
 * finds all methods with SoapOperation attributes, and extracts parameter information
 * to build SoapOperationConfig objects and return a SoapHandler.
 */
class SoapAttributeParser
{
    /**
     * Parse a class and return a SoapHandler with all configuration
     *
     * @param string|object $classOrInstance The class name or instance to parse
     * @return SoapHandler The handler ready for SOAP server use
     * @throws InvalidServiceException If the class is not properly annotated
     */
    public function parse(string|object $classOrInstance): SoapHandler
    {
        // Get reflection class
        if (is_object($classOrInstance)) {
            $reflection = new ReflectionClass($classOrInstance);
        } else {
            $reflection = new ReflectionClass($classOrInstance);
        }

        // Parse SoapService attribute
        $serviceMetadata = $this->parseServiceAttribute($reflection);

        // Parse all operations into SoapOperationConfig array
        $soapItems = $this->parseOperations($reflection);

        // Create and return SoapHandler
        return new SoapHandler(
            soapItems: $soapItems,
            serviceName: $serviceMetadata['serviceName'],
            namespace: $serviceMetadata['namespace'],
            description: $serviceMetadata['description'],
            options: $serviceMetadata['options']
        );
    }

    /**
     * Parse the SoapService attribute from a class
     *
     * @param ReflectionClass<object> $reflection
     * @return array{serviceName: string, namespace: string, description: string, options: array<string, mixed>}
     * @throws InvalidServiceException
     */
    private function parseServiceAttribute(ReflectionClass $reflection): array
    {
        $attributes = $reflection->getAttributes(SoapService::class);

        if (empty($attributes)) {
            throw new InvalidServiceException(
                "Class {$reflection->getName()} must have a #[SoapService] attribute"
            );
        }

        $soapService = $attributes[0]->newInstance();

        return [
            'serviceName' => $soapService->serviceName,
            'namespace' => $soapService->namespace,
            'description' => $soapService->description,
            'options' => $soapService->options,
        ];
    }

    /**
     * Parse all methods with SoapOperation attributes
     *
     * @param ReflectionClass<object> $reflection
     * @return array<string, SoapOperationConfig> Associative array with method name as key
     */
    private function parseOperations(ReflectionClass $reflection): array
    {
        $soapItems = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip constructor and magic methods
            if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Check for SoapOperation attribute
            $operationAttributes = $method->getAttributes(SoapOperation::class);
            if (empty($operationAttributes)) {
                continue;
            }

            $soapOperation = $operationAttributes[0]->newInstance();

            // Create SoapOperationConfig
            $config = new SoapOperationConfig();
            $config->description = $soapOperation->description;

            // Parse parameters into SoapParameter array
            $config->args = $this->parseParameters($method);

            // Parse return type
            $config->returnType = $this->parseReturnType($method);

            // Create executor that calls the method on the service instance
            $methodName = $method->getName();
            $config->executor = function(array $params) use ($reflection, $methodName) {
                // Get or create instance
                static $instance = null;
                if ($instance === null) {
                    $instance = $reflection->newInstance();
                }

                // Call method with positional arguments in correct order
                // Note: Type casting is handled by SoapHandler before calling the executor
                return $instance->$methodName(...array_values($params));
            };

            $soapItems[$methodName] = $config;
        }

        return $soapItems;
    }

    /**
     * Parse parameters from a method
     *
     * @param ReflectionMethod $method
     * @return array<SoapParameter>
     */
    private function parseParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $parameters[] = $this->parseParameter($param);
        }

        return $parameters;
    }

    /**
     * Parse a single parameter into SoapParameter
     *
     * @param ReflectionParameter $param
     * @return SoapParameter
     */
    private function parseParameter(ReflectionParameter $param): SoapParameter
    {
        // Check for SoapParameterAttribute
        $paramAttributes = $param->getAttributes(SoapParameterAttribute::class);
        $typeOverride = null;

        if (!empty($paramAttributes)) {
            $soapParamAttr = $paramAttributes[0]->newInstance();
            $typeOverride = $soapParamAttr->type;
        }

        // Get parameter type
        $type = $this->getParameterType($param, $typeOverride);

        // Determine minOccurs (0 = optional, 1 = required)
        $minOccurs = $param->isOptional() ? 0 : 1;

        return new SoapParameter(
            name: $param->getName(),
            type: $type,
            minOccurs: $minOccurs,
            maxOccurs: 1
        );
    }

    /**
     * Get parameter type as SoapType enum or class name
     *
     * @param ReflectionParameter $param
     * @param string|null $typeOverride
     * @return SoapType|string
     */
    private function getParameterType(ReflectionParameter $param, ?string $typeOverride): SoapType|string
    {
        // Use override if provided
        if ($typeOverride !== null) {
            return $this->convertToSoapType($typeOverride);
        }

        $reflectionType = $param->getType();

        if ($reflectionType === null) {
            return SoapType::Mixed;
        }

        return $this->extractType($reflectionType);
    }

    /**
     * Parse return type from a method
     *
     * @param ReflectionMethod $method
     * @return SoapType|string
     */
    private function parseReturnType(ReflectionMethod $method): SoapType|string
    {
        $returnType = $method->getReturnType();

        if ($returnType === null) {
            return SoapType::Mixed;
        }

        return $this->extractType($returnType);
    }

    /**
     * Extract type from ReflectionType
     *
     * @param \ReflectionType $type
     * @return SoapType|string
     */
    private function extractType(\ReflectionType $type): SoapType|string
    {
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            return $this->convertToSoapType($typeName);
        }

        if ($type instanceof ReflectionUnionType) {
            // For union types, get first non-null type
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() !== 'null') {
                    return $this->convertToSoapType($unionType->getName());
                }
            }
        }

        return SoapType::Mixed;
    }

    /**
     * Convert a string type to SoapType enum or return class name
     *
     * @param string $typeName
     * @return SoapType|string
     */
    private function convertToSoapType(string $typeName): SoapType|string
    {
        // Try to match SoapType enum
        $soapType = match($typeName) {
            'string' => SoapType::String,
            'int', 'integer' => SoapType::Integer,
            'float' => SoapType::Float,
            'double' => SoapType::Double,
            'bool', 'boolean' => SoapType::Boolean,
            'void' => SoapType::Void,
            'mixed' => SoapType::Mixed,
            'array' => SoapType::Mixed, // Generic array
            default => null
        };

        if ($soapType !== null) {
            return $soapType;
        }

        // Check if it's an array type (e.g., "string[]", "int[]")
        if (str_ends_with($typeName, '[]')) {
            $baseType = substr($typeName, 0, -2);
            return match($baseType) {
                'string' => SoapType::ArrayOfString,
                'int', 'integer' => SoapType::ArrayOfInteger,
                'float' => SoapType::ArrayOfFloat,
                'double' => SoapType::ArrayOfDouble,
                'bool', 'boolean' => SoapType::ArrayOfBoolean,
                default => SoapType::Mixed
            };
        }

        // Must be a class name - return as string
        return $typeName;
    }
}
