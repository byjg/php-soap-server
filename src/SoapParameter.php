<?php

namespace ByJG\SoapServer;

class SoapParameter
{
    // Argument name
    public string $name;

    // Argument type:
    // - SoapType enum for simple/array types (e.g., SoapType::String, SoapType::ArrayOfInt)
    // - string class name for complex types (e.g., MyClass::class) - will use ComplexType in WSDL
    public SoapType|string $type;

    // Minimum occurrences (default 1 = required, 0 = optional)
    public int $minOccurs = 1;

    // Maximum occurrences (default 1, use 'unbounded' for unlimited)
    public int|string $maxOccurs = 1;

    /**
     * @param string $name Argument name
     * @param SoapType|string $type SoapType enum OR class name (e.g., MyClass::class)
     * @param int $minOccurs Minimum occurrences (0 = optional, 1+ = required)
     * @param int|string $maxOccurs Maximum occurrences (1 or 'unbounded')
     * @throws \InvalidArgumentException if string type is not a valid class
     */
    public function __construct(string $name, SoapType|string $type, int $minOccurs = 1, int|string $maxOccurs = 1)
    {
        $this->name = $name;

        if ($type instanceof SoapType) {
            // Using enum - keep as SoapType
            $this->type = $type;
        } else {
            // Using string - must be a valid class name
            if (!class_exists($type)) {
                throw new \InvalidArgumentException(
                    "Type '{$type}' must be a SoapType enum or a valid class name. " .
                    "Use SoapType enum for simple types (e.g., SoapType::String, SoapType::ArrayOfInt)."
                );
            }
            $this->type = $type;
        }

        $this->minOccurs = $minOccurs;
        $this->maxOccurs = $maxOccurs;
    }
}
