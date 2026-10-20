# Changelog - Version 6.0

## Overview

Version 6.0 represents a major modernization of the SOAP Server library, introducing PHP 8.3+ as the minimum requirement, adopting PSR-7 standards, and providing a complete rewrite with enhanced features and improved developer experience.

## New Features

### Modern PHP 8.3+ Attributes Support
- Full attribute-based configuration using `#[SoapService]`, `#[SoapOperation]`, and `#[SoapParameter]`
- Declarative service definitions directly in your PHP classes
- Type-safe parameter configuration with PHP 8.3+ type hints

### Enhanced Documentation UI
- Beautiful, modern HTML service documentation using Jinja templates
- Responsive, mobile-friendly design
- Interactive service information page
- Customizable templates for branding

### Advanced Parameter Configuration
- Support for `minOccurs` and `maxOccurs` attributes for schema validation
- Better control over optional and required parameters
- Enhanced array and complex type handling
- Improved parameter type casting logic

### PSR-7 Request/Response Handling
- Complete PSR-7 `ServerRequestInterface` integration
- Standardized response handling with `ResponseWriter` class
- Proper HTTP header management
- Better separation of concerns

### Complex Type Support
- Enhanced support for complex types and custom classes
- Improved serialization/deserialization
- Better handling of nested objects and arrays

### Additional Features
- SOAP 1.1 & 1.2 support
- Auto-generated WSDL from service definitions
- Built-in DISCO (Discovery) document generation
- Case-insensitive SOAP method handling
- Content-Type support for custom SOAP operations
- Comprehensive test coverage with PHPUnit 10.5+/11.5+
- Enhanced static analysis with Psalm 5.9+/6.13+

## Breaking Changes

| Before (5.x) | After (6.0) | Description |
|-------------|------------|-------------|
| PHP 7.x/8.0+ | PHP 8.3+ | Minimum PHP version increased to 8.3 |
| Direct `$_SERVER`, `$_REQUEST` access | PSR-7 `ServerRequestInterface` | Request handling now uses PSR-7 standard interfaces |
| Manual response output | `ResponseWriter::output()` | Responses must be output using the ResponseWriter class |
| `SoapParameter` class for config | `SoapParameterConfig` class | The configuration class was renamed for clarity |
| No dependency on `byjg/webrequest` | Requires `byjg/webrequest` ^6.0 | New dependency for enhanced HTTP handling |
| No dependency on `byjg/jinja-php` | Requires `byjg/jinja-php` ^6.0 | New dependency for template rendering |
| No dependency on `byjg/serializer` | Requires `byjg/serializer` ^6.0 | New dependency for object serialization |
| `sizeof()` usage | `count()` | Replaced for PHP standard compliance |
| Basic HTML service info | Jinja-based templates | Service documentation now uses templating engine |
| Mixed method name handling | Case-insensitive method handling | SOAP methods are now handled case-insensitively |

## Migration Path from 5.x to 6.0

### Step 1: Update PHP Version
Ensure your environment is running PHP 8.3 or later:
```bash
php -v  # Should show 8.3.x or higher
```

### Step 2: Update Dependencies
Update your `composer.json`:
```bash
composer require byjg/soap-server:^6.0
```

This will automatically install the required dependencies:
- `byjg/jinja-php` ^6.0
- `byjg/webrequest` ^6.0
- `byjg/serializer` ^6.0

### Step 3: Update Request Handling
Replace direct super-global access with PSR-7:

**Before (5.x):**
```php
// Direct access to $_SERVER, $_REQUEST
$handler = new SoapHandler(...);
$handler->handle();
```

**After (6.0):**
```php
use ByJG\SoapServer\SoapHandler;
use ByJG\SoapServer\ResponseWriter;

$handler = new SoapHandler(...);
$response = $handler->handle();  // Returns PSR-7 ResponseInterface
ResponseWriter::output($response);
```

### Step 4: Update Configuration Class Names
Replace `SoapParameter` with `SoapParameterConfig` in programmatic configurations:

**Before (5.x):**
```php
use ByJG\SoapServer\SoapParameter;

$param = new SoapParameter('paramName', SoapType::String);
```

**After (6.0):**
```php
use ByJG\SoapServer\SoapParameterConfig;

$param = new SoapParameterConfig('paramName', SoapType::String);
```

**Note:** The `#[SoapParameter]` attribute (used in attribute-based configuration) remains unchanged.

### Step 5: Update Type Hints and Return Types
Add strict type hints to your service methods to leverage PHP 8.3+ features:

```php
#[SoapOperation(description: 'Adds two numbers')]
public function add(int $a, int $b): int {
    return $a + $b;
}
```

### Step 6: Adopt Modern Attributes (Recommended)
Consider migrating to attribute-based configuration for better developer experience:

```php
use ByJG\SoapServer\Attributes\{SoapService, SoapOperation, SoapParameter};

#[SoapService(
    serviceName: 'MyService',
    namespace: 'http://example.com/myservice',
    description: 'My SOAP service'
)]
class MyService {
    #[SoapOperation(description: 'My operation')]
    public function myMethod(
        #[SoapParameter(description: 'Input parameter')] string $input
    ): string {
        return $input;
    }
}
```

### Step 7: Update Service Info Templates (Optional)
If you customized service documentation, update to use Jinja templates:
- Service info pages now use Jinja-PHP templating
- See [docs/templates.md](docs/templates.md) for customization guide

### Step 8: Review and Test
1. Run your test suite to ensure compatibility
2. Review static analysis with Psalm:
   ```bash
   composer psalm
   ```
3. Test WSDL generation: `http://your-service?wsdl`
4. Test service documentation UI: `http://your-service`

## Bug Fixes

- Fixed response handling for empty `Content-Type` values
- Improved method name handling with case-insensitive lookup
- Optimized array size calculation in `SoapHandler`
- Enhanced error management and HTTP response handling
- Fixed parameter parsing edge cases in `SoapAttributeParser`

## Development & Tooling

- Updated PHPUnit to version 10.5+/11.5+
- Updated Psalm to version 5.9+/6.13+
- Added comprehensive test coverage for all major features
- Improved GitHub Actions workflows for CI/CD
- Added `ext-soap`, `ext-dom`, and `ext-simplexml` as explicit dependencies
- Enhanced PHPDoc annotations for better IDE support

## Documentation

New comprehensive documentation:
- [Getting Started Guide](docs/getting-started.md)
- [Using Attributes](docs/using-attributes.md)
- [Programmatic Configuration](docs/programmatic-configuration.md)
- [Complex Types Guide](docs/complex-types.md)
- [Template Customization](docs/templates.md)
- [License Acknowledgments](docs/license-acknowledgments.md)

## Links

- [GitHub Repository](https://github.com/byjg/php-soap-server)
- [Report Issues](https://github.com/byjg/php-soap-server/issues)
- [License](LICENSE)

---
Released: 2025-11-25
