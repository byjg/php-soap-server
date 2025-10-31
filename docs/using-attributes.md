---
sidebar_position: 2
---

# Using Attributes

Learn how to use PHP attributes to define your SOAP services declaratively.

## Overview

PHP 8 introduced attributes (annotations) that allow you to add metadata to classes, methods, and parameters. The byjg/soap-server library leverages attributes to provide a clean, declarative way to define SOAP services.

## Available Attributes

### SoapService

The `#[SoapService]` attribute defines the service configuration.

```php
use ByJG\SoapServer\Attributes\SoapService;

#[SoapService(
    serviceName: 'MyService',           // Required: Service name in WSDL
    namespace: 'http://example.com/',   // Required: XML namespace
    description: 'My SOAP Service',     // Optional: Service description
    options: []                         // Optional: Additional SOAP options
)]
class MyService
{
    // ... operations
}
```

#### Parameters

| Parameter     | Type   | Required | Default | Description                                |
|---------------|--------|----------|---------|--------------------------------------------|
| `serviceName` | string | **Yes**  | -       | Name of the service (used in WSDL)         |
| `namespace`   | string | **Yes**  | -       | XML namespace for the service              |
| `description` | string | No       | `''`    | Service description shown in documentation |
| `options`     | array  | No       | `[]`    | Additional SOAP server options             |

#### Options

The `options` array accepts standard PHP SoapServer options:

```php
#[SoapService(
    serviceName: 'MyService',
    options: [
        'soap_version' => SOAP_1_2,
        'encoding' => 'UTF-8',
        'cache_wsdl' => WSDL_CACHE_NONE
    ]
)]
```

### SoapOperation

The `#[SoapOperation]` attribute marks a method as a SOAP operation.

```php
use ByJG\SoapServer\Attributes\SoapOperation;

#[SoapOperation(description: 'Performs a calculation')]
public function calculate(int $x, int $y): int
{
    return $x + $y;
}
```

#### Parameters

| Parameter     | Type   | Required | Default | Description                             |
|---------------|--------|----------|---------|-----------------------------------------|
| `description` | string | No       | `''`    | Operation description for documentation |

### SoapParameter

The `#[SoapParameter]` attribute adds metadata to method parameters.

```php
use ByJG\SoapServer\Attributes\SoapParameter;

public function greet(
    #[SoapParameter(
        description: 'Name of the person to greet',
        minOccurs: 1,
        maxOccurs: 1
    )]
    string $name
): string {
    return "Hello, $name!";
}
```

#### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `description` | string | No | `''` | Parameter description for documentation |
| `minOccurs` | int | No | `1` | Minimum occurrences (0 = optional, 1 = required) |
| `maxOccurs` | int | No | `1` | Maximum occurrences (-1 = unbounded/unlimited) |

## Type Support

### Simple Types

The library automatically maps PHP types to SOAP/WSDL types:

```php
public function examples(
    string $text,      // xsd:string
    int $number,       // xsd:int
    float $decimal,    // xsd:float
    bool $flag         // xsd:boolean
): string {
    // ...
}
```

### Nullable Types

Use PHP's nullable type syntax:

```php
public function findUser(int $id): ?User
{
    // Returns User or null
}
```

### Arrays

Use array type hints with `maxOccurs`:

```php
public function getUsers(
    #[SoapParameter(maxOccurs: -1)]  // -1 for unbounded/unlimited
    array $ids
): array {
    // Returns array of users
}
```

For typed arrays, use docblocks:

```php
/**
 * @param int[] $ids Array of user IDs
 * @return User[] Array of users
 */
public function getUsers(array $ids): array
{
    // ...
}
```

## Optional Parameters

Use `minOccurs: 0` to make parameters optional:

```php
public function greet(
    #[SoapParameter(description: 'Name', minOccurs: 1)] string $name,
    #[SoapParameter(description: 'Title', minOccurs: 0)] ?string $title = null
): string {
    return $title ? "$title $name" : "Hello, $name";
}
```

## Complete Example

```php
<?php

use ByJG\SoapServer\Attributes\{SoapService, SoapOperation, SoapParameter};
use ByJG\SoapServer\SoapAttributeParser;

#[SoapService(
    serviceName: 'UserService',
    namespace: 'http://example.com/users',
    description: 'User management web service',
    options: ['soap_version' => SOAP_1_2]
)]
class UserService
{
    #[SoapOperation(description: 'Creates a new user')]
    public function createUser(
        #[SoapParameter(description: 'Username', minOccurs: 1)]
        string $username,

        #[SoapParameter(description: 'Email address', minOccurs: 1)]
        string $email,

        #[SoapParameter(description: 'Full name', minOccurs: 0)]
        ?string $fullName = null
    ): User {
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->fullName = $fullName ?? $username;
        return $user;
    }

    #[SoapOperation(description: 'Retrieves users by IDs')]
    public function getUsers(
        #[SoapParameter(description: 'User IDs', minOccurs: 1, maxOccurs: -1)]
        array $ids
    ): array {
        // Returns array of User objects
        return array_map(fn($id) => $this->findUserById($id), $ids);
    }

    #[SoapOperation(description: 'Searches for users by name')]
    public function searchUsers(
        #[SoapParameter(description: 'Search query')]
        string $query,

        #[SoapParameter(description: 'Maximum results', minOccurs: 0)]
        ?int $limit = 10
    ): array {
        // Search logic here
        return [];
    }
}

class User
{
    public string $username;
    public string $email;
    public string $fullName;
}

// Start the service
use ByJG\SoapServer\ResponseWriter;

$parser = new SoapAttributeParser();
$handler = $parser->parse(UserService::class);
$response = $handler->handle();
ResponseWriter::output($response);
```

## Best Practices

:::tip
**Best Practices**

1. **Always use type hints**: They're used for WSDL generation
2. **Add descriptions**: They appear in the documentation UI
3. **Use meaningful namespaces**: Use your domain name
4. **Document optional parameters**: Use `minOccurs: 0` and nullable types
5. **Group related operations**: One service class per logical domain
:::

## Next Steps

- [Complex Types](complex-types.md) - Work with objects and custom classes
- [Programmatic Configuration](programmatic-configuration.md) - Alternative to attributes
