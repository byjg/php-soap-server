---
sidebar_position: 4
---

# Complex Types

Learn how to work with custom classes, objects, and complex data structures in your SOAP services.

## Overview

SOAP services often need to work with complex data types beyond simple strings and integers. The byjg/soap-server library fully supports custom PHP classes as SOAP complex types.

## Defining Complex Types

Complex types are simple PHP classes with public properties:

```php
class User
{
    public string $username;
    public string $email;
    public int $age;
    public bool $active;
}
```

:::info
**Type Hints Required**

All public properties must have type hints. The library uses these to generate the WSDL schema.
:::

## Using Complex Types with Attributes

### As Parameters

```php
use ByJG\SoapServer\Attributes\{SoapService, SoapOperation};

#[SoapService(serviceName: 'UserService')]
class UserService
{
    #[SoapOperation(description: 'Creates a new user')]
    public function createUser(User $user): bool
    {
        // Save user to database
        return true;
    }
}
```

### As Return Types

```php
#[SoapOperation(description: 'Gets user by ID')]
public function getUser(int $id): ?User
{
    // Fetch from database
    $user = new User();
    $user->username = 'john_doe';
    $user->email = 'john@example.com';
    $user->age = 30;
    $user->active = true;
    return $user;
}
```

### Arrays of Complex Types

```php
#[SoapOperation(description: 'Gets all users')]
public function getAllUsers(): array
{
    return [
        $user1,
        $user2,
        $user3
    ];
}
```

Use PHPDoc for better documentation:

```php
/**
 * @return User[] Array of users
 */
#[SoapOperation(description: 'Gets all active users')]
public function getActiveUsers(): array
{
    // Returns array of User objects
}
```

## Using Complex Types Programmatically

When using programmatic configuration, specify the class name as a string:

```php
use ByJG\SoapServer\{SoapHandler, SoapOperationConfig, SoapParameter};

$operation = new SoapOperationConfig();
$operation->description = 'Creates a user';
$operation->args = [
    new SoapParameter('user', User::class)  // Use class name
];
$operation->returnType = SoapType::Boolean;
$operation->executor = function(array $params) {
    $user = $params['user'];  // stdClass with user properties
    // Process user
    return true;
};
```

## Nested Complex Types

Complex types can contain other complex types:

```php
class Address
{
    public string $street;
    public string $city;
    public string $zipCode;
    public string $country;
}

class User
{
    public string $username;
    public string $email;
    public Address $address;  // Nested complex type
}

#[SoapOperation(description: 'Creates user with address')]
public function createUser(User $user): bool
{
    echo $user->address->city;  // Access nested properties
    return true;
}
```

## Arrays in Complex Types

Complex types can have array properties:

```php
class User
{
    public string $username;
    public string $email;

    /**
     * @var string[] Array of roles
     */
    public array $roles;
}

#[SoapOperation(description: 'Creates user with roles')]
public function createUser(User $user): bool
{
    foreach ($user->roles as $role) {
        echo "Role: $role\n";
    }
    return true;
}
```

:::warning
**Array Types**

Use PHPDoc `@var` annotations to specify array element types, as PHP doesn't support typed arrays natively.
:::

## Nullable Complex Types

Use nullable types to make complex types optional:

```php
#[SoapOperation(description: 'Updates user optionally with new address')]
public function updateUser(
    int $userId,
    ?Address $newAddress = null
): bool {
    if ($newAddress !== null) {
        // Update address
    }
    return true;
}
```

## Complete Example

```php
<?php

use ByJG\SoapServer\Attributes\{SoapService, SoapOperation, SoapParameter};
use ByJG\SoapServer\SoapAttributeParser;

// Define complex types
class Address
{
    public string $street;
    public string $city;
    public string $zipCode;
    public string $country;
}

class User
{
    public int $id;
    public string $username;
    public string $email;
    public Address $address;

    /**
     * @var string[]
     */
    public array $roles;

    public bool $active;
}

class CreateUserRequest
{
    public string $username;
    public string $email;
    public Address $address;

    /**
     * @var string[]
     */
    public array $roles;
}

// Define service
#[SoapService(
    serviceName: 'UserManagementService',
    namespace: 'http://example.com/users',
    description: 'User management web service'
)]
class UserManagementService
{
    #[SoapOperation(description: 'Creates a new user')]
    public function createUser(CreateUserRequest $request): User
    {
        $user = new User();
        $user->id = rand(1, 1000);
        $user->username = $request->username;
        $user->email = $request->email;
        $user->address = $request->address;
        $user->roles = $request->roles;
        $user->active = true;

        return $user;
    }

    #[SoapOperation(description: 'Gets user by ID')]
    public function getUser(
        #[SoapParameter(description: 'User ID')]
        int $id
    ): ?User {
        // Fetch from database
        if ($id <= 0) {
            return null;
        }

        $user = new User();
        $user->id = $id;
        $user->username = 'john_doe';
        $user->email = 'john@example.com';

        $address = new Address();
        $address->street = '123 Main St';
        $address->city = 'Springfield';
        $address->zipCode = '12345';
        $address->country = 'USA';

        $user->address = $address;
        $user->roles = ['user', 'admin'];
        $user->active = true;

        return $user;
    }

    /**
     * @return User[]
     */
    #[SoapOperation(description: 'Gets all users')]
    public function getAllUsers(): array
    {
        return [
            $this->getUser(1),
            $this->getUser(2),
            $this->getUser(3)
        ];
    }

    #[SoapOperation(description: 'Updates user address')]
    public function updateUserAddress(
        #[SoapParameter(description: 'User ID')]
        int $userId,

        #[SoapParameter(description: 'New address')]
        Address $address
    ): bool {
        // Update address in database
        return true;
    }
}

// Start the service
$parser = new SoapAttributeParser();
$handler = $parser->parse(UserManagementService::class);
$handler->handle();
```

## SOAP Request Example

Here's how a client would send a complex type:

```xml
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <createUser xmlns="http://example.com/users">
      <request>
        <username>john_doe</username>
        <email>john@example.com</email>
        <address>
          <street>123 Main St</street>
          <city>Springfield</city>
          <zipCode>12345</zipCode>
          <country>USA</country>
        </address>
        <roles>user</roles>
        <roles>admin</roles>
      </request>
    </createUser>
  </soap:Body>
</soap:Envelope>
```

## PHP Client Example

```php
<?php

$client = new SoapClient('http://localhost:8080?WSDL');

// Create request object
$request = new stdClass();
$request->username = 'john_doe';
$request->email = 'john@example.com';

$address = new stdClass();
$address->street = '123 Main St';
$address->city = 'Springfield';
$address->zipCode = '12345';
$address->country = 'USA';

$request->address = $address;
$request->roles = ['user', 'admin'];

// Call service
$user = $client->createUser($request);

echo "Created user: {$user->username} with ID: {$user->id}\n";
echo "Lives in: {$user->address->city}, {$user->address->country}\n";
```

## Best Practices

:::tip
**Best Practices**

1. **Use type hints**: Always type hint all public properties
2. **Document arrays**: Use PHPDoc for array types
3. **Create DTOs**: Use separate request/response classes for clarity
4. **Keep it simple**: Avoid deeply nested structures when possible
5. **Validate data**: Validate complex types in your executors
6. **Use nullable types**: For optional complex parameters
:::

## Next Steps

- [Using Attributes](using-attributes.md) - Learn more about attribute configuration
- [Programmatic Configuration](programmatic-configuration.md) - Configure without attributes
