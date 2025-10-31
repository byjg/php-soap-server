---
sidebar_position: 3
---

# Programmatic Configuration

Learn how to configure SOAP services programmatically without using PHP attributes.

## Overview

While attributes provide a clean, declarative way to define services, you might need programmatic configuration for:
- Dynamic service generation
- Legacy PHP versions (though this library requires PHP 8.1+)
- More control over the service structure
- Runtime configuration

## Core Classes

### SoapHandler

The `SoapHandler` class is the core of the library. It handles WSDL generation, service documentation, and SOAP requests.

```php
use ByJG\SoapServer\SoapHandler;

$handler = new SoapHandler(
    soapItems: [],              // Array of SoapOperationConfig
    serviceName: 'MyService',   // Service name
    namespace: 'http://example.com/',  // XML namespace
    description: 'My Service',  // Description
    options: []                 // SOAP options
);
```

### SoapOperationConfig

Defines a single SOAP operation.

```php
use ByJG\SoapServer\SoapOperationConfig;
use ByJG\SoapServer\SoapParameterConfig;
use ByJG\SoapServer\SoapType;

$operation = new SoapOperationConfig();
$operation->description = 'Adds two numbers';
$operation->args = [
    new SoapParameterConfig('a', SoapType::Integer),
    new SoapParameterConfig('b', SoapType::Integer)
];
$operation->returnType = SoapType::Integer;
$operation->executor = function(array $params) {
    return $params['a'] + $params['b'];
};
```

### SoapParameterConfig

Defines a parameter for an operation (programmatic configuration).

:::info
For attribute-based configuration, use `#[SoapParameter]` attribute instead.
:::

```php
use ByJG\SoapServer\SoapParameterConfig;
use ByJG\SoapServer\SoapType;

$param = new SoapParameterConfig(
    name: 'username',           // Parameter name
    type: SoapType::String,     // Parameter type
    minOccurs: 1,               // Minimum occurrences (0 = optional)
    maxOccurs: 1                // Maximum occurrences (-1 = unbounded)
);
```

### SoapType

Enum defining available SOAP types.

```php
use ByJG\SoapServer\SoapType;

SoapType::String
SoapType::Integer
SoapType::Float
SoapType::Double
SoapType::Boolean
SoapType::ArrayOfString
SoapType::ArrayOfInteger
SoapType::ArrayOfFloat
SoapType::ArrayOfDouble
SoapType::ArrayOfBoolean
```

## Complete Example

Here's a complete calculator service using programmatic configuration:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ByJG\SoapServer\SoapHandler;
use ByJG\SoapServer\SoapOperationConfig;
use ByJG\SoapServer\SoapParameterConfig;
use ByJG\SoapServer\SoapType;

// Define the add operation
$addOperation = new SoapOperationConfig();
$addOperation->description = 'Adds two numbers together';
$addOperation->args = [
    new SoapParameterConfig('a', SoapType::Integer, 1, 1),
    new SoapParameterConfig('b', SoapType::Integer, 1, 1)
];
$addOperation->returnType = SoapType::Integer;
$addOperation->executor = function(array $params) {
    return $params['a'] + $params['b'];
};

// Define the subtract operation
$subtractOperation = new SoapOperationConfig();
$subtractOperation->description = 'Subtracts second number from first';
$subtractOperation->args = [
    new SoapParameterConfig('a', SoapType::Integer),
    new SoapParameterConfig('b', SoapType::Integer)
];
$subtractOperation->returnType = SoapType::Integer;
$subtractOperation->executor = function(array $params) {
    return $params['a'] - $params['b'];
};

// Define the greet operation with optional parameter
$greetOperation = new SoapOperationConfig();
$greetOperation->description = 'Greets a person';
$greetOperation->args = [
    new SoapParameterConfig('name', SoapType::String, 1, 1),
    new SoapParameterConfig('title', SoapType::String, 0, 1)  // Optional
];
$greetOperation->returnType = SoapType::String;
$greetOperation->executor = function(array $params) {
    $title = $params['title'] ?? '';
    $name = $params['name'];
    return $title ? "$title $name" : "Hello, $name!";
};

// Create the SOAP handler
$handler = new SoapHandler(
    soapItems: [
        'add' => $addOperation,
        'subtract' => $subtractOperation,
        'greet' => $greetOperation
    ],
    serviceName: 'CalculatorService',
    namespace: 'http://example.com/calculator',
    description: 'A simple calculator web service',
    options: ['soap_version' => SOAP_1_2]
);

// Handle the request
use ByJG\SoapServer\ResponseWriter;

$response = $handler->handle();
ResponseWriter::output($response);
```

## Using Complex Types

For complex types (custom classes), use the class name as a string:

```php
class User
{
    public string $username;
    public string $email;
    public int $age;
}

$createUserOperation = new SoapOperationConfig();
$createUserOperation->description = 'Creates a new user';
$createUserOperation->args = [
    new SoapParameterConfig('username', SoapType::String),
    new SoapParameterConfig('email', SoapType::String),
    new SoapParameterConfig('age', SoapType::Integer)
];
$createUserOperation->returnType = User::class;  // Use class name as string
$createUserOperation->executor = function(array $params) {
    $user = new User();
    $user->username = $params['username'];
    $user->email = $params['email'];
    $user->age = $params['age'];
    return $user;
};

$handler = new SoapHandler(
    soapItems: ['createUser' => $createUserOperation],
    serviceName: 'UserService'
);

$response = $handler->handle();
ResponseWriter::output($response);
```

## Arrays and Collections

Use `maxOccurs: -1` for unbounded arrays:

```php
$operation = new SoapOperationConfig();
$operation->description = 'Sums an array of numbers';
$operation->args = [
    new SoapParameterConfig(
        name: 'numbers',
        type: SoapType::ArrayOfInteger,
        minOccurs: 1,
        maxOccurs: -1  // -1 = unbounded/unlimited occurrences
    )
];
$operation->returnType = SoapType::Integer;
$operation->executor = function(array $params) {
    return array_sum($params['numbers']);
};
```

## Service Options

The `options` parameter accepts standard PHP SoapServer options:

```php
$handler = new SoapHandler(
    soapItems: $operations,
    serviceName: 'MyService',
    options: [
        'soap_version' => SOAP_1_2,
        'encoding' => 'UTF-8',
        'cache_wsdl' => WSDL_CACHE_NONE,
        'features' => SOAP_SINGLE_ELEMENT_ARRAYS
    ]
);
```

## Mixing with Attributes

You can mix programmatic configuration with attributes by using `SoapAttributeParser`:

```php
use ByJG\SoapServer\SoapAttributeParser;

#[SoapService(serviceName: 'MixedService')]
class MyService
{
    #[SoapOperation(description: 'Defined with attributes')]
    public function attributeMethod(string $input): string
    {
        return "From attribute: $input";
    }
}

// Parse attributes
$parser = new SoapAttributeParser();
$handler = $parser->parse(MyService::class);

// Get existing operations
$operations = $handler->getSoapItems();  // This method doesn't exist in current API

// Add programmatic operation
$newOperation = new SoapOperationConfig();
// ... configure operation

// Note: Current API doesn't support mixing, but you can create
// separate services or extend the handler
```

## Error Handling

Operations can throw exceptions which will be converted to SOAP faults:

```php
$operation->executor = function(array $params) {
    if ($params['denominator'] === 0) {
        throw new Exception('Division by zero');
    }
    return $params['numerator'] / $params['denominator'];
};
```

## Next Steps

- [Complex Types](complex-types.md) - Work with custom classes
- [Using Attributes](using-attributes.md) - Simpler declarative approach
