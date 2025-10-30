---
sidebar_position: 1
---

# Getting Started

This guide will walk you through creating your first SOAP web service with byjg/soap-server.

## Installation

Install the library via Composer:

```bash
composer require byjg/soap-server
```

## Creating Your First Service

Let's create a simple calculator service that can add and subtract numbers.

### Step 1: Create the Service Class

Create a file named `calculator.php`:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use ByJG\SoapServer\Attributes\SoapService;
use ByJG\SoapServer\Attributes\SoapOperation;
use ByJG\SoapServer\Attributes\SoapParameter;
use ByJG\SoapServer\SoapAttributeParser;

#[SoapService(
    serviceName: 'CalculatorService',
    namespace: 'http://example.com/calculator',
    description: 'A simple calculator web service'
)]
class Calculator
{
    #[SoapOperation(description: 'Adds two numbers together')]
    public function add(
        #[SoapParameter(description: 'The first number')] int $a,
        #[SoapParameter(description: 'The second number')] int $b
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
}

// Parse and start the service
$parser = new SoapAttributeParser();
$handler = $parser->parse(Calculator::class);
$handler->handle();
```

### Step 2: Start the Service

Start PHP's built-in web server:

```bash
php -S localhost:8080 calculator.php
```

### Step 3: View the Service Documentation

Open your browser and navigate to:

```
http://localhost:8080
```

You'll see a modern, interactive documentation page showing:
- Service overview
- Available operations with parameters
- Example SOAP requests
- WSDL and DISCO links

### Step 4: Access the WSDL

The WSDL is automatically generated and available at:

```
http://localhost:8080?wsdl
```

## Testing Your Service

Create a client file `client.php`:

```php
<?php

try {
    $client = new SoapClient('http://localhost:8080?wsdl', [
        'trace' => 1,
        'exceptions' => true,
    ]);

    // Call the add operation
    $result = $client->add(10, 5);
    echo "10 + 5 = $result\n";

    // Call the subtract operation
    $result = $client->subtract(20, 8);
    echo "20 - 8 = $result\n";

} catch (SoapFault $e) {
    echo "SOAP Error: " . $e->getMessage() . "\n";
}
```

Run the client:

```bash
php client.php
```

Expected output:
```
10 + 5 = 15
20 - 8 = 12
```

## Understanding the Code

### Service Attributes

```php
#[SoapService(
    serviceName: 'CalculatorService',      // Name in WSDL
    namespace: 'http://example.com/calculator',  // XML namespace
    description: 'A simple calculator web service'  // Service description
)]
```

The `#[SoapService]` attribute defines the service configuration.

### Operation Attributes

```php
#[SoapOperation(description: 'Adds two numbers together')]
public function add(...)
```

The `#[SoapOperation]` attribute marks a method as a SOAP operation and provides a description.

### Parameter Attributes

```php
#[SoapParameter(description: 'The first number')] int $a
```

The `#[SoapParameter]` attribute adds documentation for method parameters.

## Service Endpoints

When you access your service, these endpoints are available:

| Endpoint                                    | Description                  |
|---------------------------------------------|------------------------------|
| `http://localhost:8080`                     | Service documentation (HTML) |
| `http://localhost:8080?wsdl`                | WSDL document (XML)          |
| `http://localhost:8080?disco`               | Discovery document (XML)     |
| `http://localhost:8080` (with SOAP request) | SOAP endpoint                |

## Next Steps

- [Using Attributes](using-attributes.md) - Learn more about attribute configuration
- [Programmatic Configuration](programmatic-configuration.md) - Use the API without attributes
- [Complex Types](complex-types.md) - Work with objects and arrays
