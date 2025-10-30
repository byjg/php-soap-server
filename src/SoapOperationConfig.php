<?php

namespace ByJG\SoapServer;

class SoapOperationConfig
{
    // Soap Description
    public string $description;

    // Array of SoapParameterConfig objects defining method arguments
    // Example: [new SoapParameterConfig('name', SoapType::String), new SoapParameterConfig('age', SoapType::Integer, 0)]
    public array $args = [];

    // Return type of the method:
    // - SoapType enum for simple/array types (e.g., SoapType::String, SoapType::ArrayOfInt)
    // - string class name for complex types (e.g., MyClass::class) - will use ComplexType in WSDL
    public SoapType|string $returnType = 'string';

    // Callable that executes the SOAP method
    // Signature: function(array $params): mixed
    // The $params array is associative with keys matching the arg names from SoapArg objects
    // Example: $soapItem->executor = function(array $params) { return $params['name']; };
    public mixed $executor;

    /**
     * Set the return type
     *
     * @param SoapType|string $returnType SoapType enum OR class name (e.g., MyClass::class)
     * @throws \InvalidArgumentException if string type is not a valid class
     */
    public function setReturnType(SoapType|string $returnType): void
    {
        if ($returnType instanceof SoapType) {
            // Keep as SoapType
            $this->returnType = $returnType;
        } else {
            // Using string - must be a valid class name or 'void'
            if ($returnType !== 'void' && !class_exists($returnType)) {
                throw new \InvalidArgumentException(
                    "Return type '{$returnType}' must be a SoapType enum, 'void', or a valid class name. " .
                    "Use SoapType enum for simple types (e.g., SoapType::String, SoapType::ArrayOfInt)."
                );
            }
            $this->returnType = $returnType;
        }
    }
}