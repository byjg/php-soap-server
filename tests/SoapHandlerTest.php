<?php

declare(strict_types=1);

namespace Test;

use ByJG\SoapServer\SoapHandler;
use ByJG\SoapServer\SoapOperationConfig;
use ByJG\SoapServer\SoapParameterConfig;
use ByJG\SoapServer\SoapType;
use ByJG\Util\Uri;
use ByJG\WebRequest\Psr7\MemoryStream;
use ByJG\WebRequest\Psr7\ServerRequest;
use Exception;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Test\Fixtures\SoapHandlerMock;

/**
 * Simple model for testing JSON/XML responses
 */
class TestUserModel
{
    public string $name;
    public int $age;
    public string $email;
}

/**
 * Test suite for SoapHandler
 */
class SoapHandlerTest extends TestCase
{
    private SoapHandler $handler;
    private array $soapItems;

    #[Override]
    protected function setUp(): void
    {
        // Create a simple set of SOAP operations for testing
        $addOperation = new SoapOperationConfig();
        $addOperation->description = 'Adds two numbers';
        $addOperation->args = [
            new SoapParameterConfig('a', SoapType::Integer, 1, 1),
            new SoapParameterConfig('b', SoapType::Integer, 1, 1)
        ];
        $addOperation->returnType = SoapType::Integer;
        $addOperation->executor = function (array $params) {
            return $params['a'] + $params['b'];
        };

        $greetOperation = new SoapOperationConfig();
        $greetOperation->description = 'Greets a person';
        $greetOperation->args = [
            new SoapParameterConfig('name', SoapType::String, 0, 1) // Optional
        ];
        $greetOperation->returnType = SoapType::String;
        $greetOperation->executor = function (array $params) {
            $name = $params['name'] ?? 'World';
            return "Hello, $name!";
        };

        // Operation that returns an array (for JSON serialization)
        $arrayOperation = new SoapOperationConfig();
        $arrayOperation->description = 'Returns array as JSON';
        $arrayOperation->args = [
            new SoapParameterConfig('name', SoapType::String, 1, 1)
        ];
        $arrayOperation->returnType = SoapType::ArrayOfString;
        $arrayOperation->contentType = 'application/json';
        $arrayOperation->executor = function (array $params) {
            return [
                'name' => $params['name'],
                'status' => 'active',
                'timestamp' => '2025-10-31T00:00:00Z'
            ];
        };

        // Operation that returns a model (for JSON/XML serialization)
        $modelOperation = new SoapOperationConfig();
        $modelOperation->description = 'Returns user model as JSON';
        $modelOperation->args = [
            new SoapParameterConfig('name', SoapType::String, 1, 1),
            new SoapParameterConfig('age', SoapType::Integer, 1, 1)
        ];
        $modelOperation->returnType = TestUserModel::class;
        $modelOperation->contentType = 'application/json';
        $modelOperation->executor = function (array $params) {
            $user = new TestUserModel();
            $user->name = $params['name'];
            $user->age = $params['age'];
            $user->email = strtolower($params['name']) . '@example.com';
            return $user;
        };

        // Operation that returns XML
        $xmlOperation = new SoapOperationConfig();
        $xmlOperation->description = 'Returns user model as XML';
        $xmlOperation->args = [
            new SoapParameterConfig('name', SoapType::String, 1, 1)
        ];
        $xmlOperation->returnType = TestUserModel::class;
        $xmlOperation->contentType = 'text/xml';
        $xmlOperation->executor = function (array $params) {
            $user = new TestUserModel();
            $user->name = $params['name'];
            $user->age = 25;
            $user->email = strtolower($params['name']) . '@example.com';
            return $user;
        };

        $this->soapItems = [
            'add' => $addOperation,
            'greet' => $greetOperation,
            'getArray' => $arrayOperation,
            'getUserJson' => $modelOperation,
            'getUserXml' => $xmlOperation
        ];
    }

    /**
     * Helper method to create a mock ServerRequest
     *
     * @param string $uri Full URI with query string
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $headers Additional headers
     * @param string $body Request body
     * @return ServerRequestInterface
     */
    private function createRequest(
        string $uri = 'http://localhost/service.php',
        string $method = 'GET',
        array  $headers = [],
        string $body = ''
    ): ServerRequestInterface
    {
        $uriObj = new Uri($uri);
        $request = new ServerRequest($uriObj);
        $request = $request->withMethod($method);

        // Add headers
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Add body if provided
        if (!empty($body)) {
            $request = $request->withBody(new MemoryStream($body));
        }

        return $request;
    }

    /**
     * Test WSDL generation when ?wsdl query parameter is present
     */
    public function testHandleWSDL(): void
    {
        // Setup: Create request with ?wsdl query parameter
        $request = $this->createRequest('http://localhost/service.php?wsdl');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            namespace: 'http://example.com/test',
            description: 'Test SOAP Service',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/xml'], $response->getHeader('Content-Type'));

        // Validate WSDL content
        $body = $response->getBody()->getContents();
        $expected = file_get_contents(__DIR__ . "/Fixtures/test_handle_wsdl.xml.txt");
        $this->assertEquals($expected, $body);
    }

    /**
     * Test DISCO generation when ?disco query parameter is present
     */
    public function testHandleDISCO(): void
    {
        // Setup: Create request with ?disco query parameter
        $request = $this->createRequest('http://localhost/service.php?disco');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            namespace: 'http://example.com/test',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/xml'], $response->getHeader('Content-Type'));

        // Validate DISCO content
        $body = $response->getBody()->getContents();
        $expected = file_get_contents(__DIR__ . "/Fixtures/test_handle_disco.xml.txt");
        $this->assertEquals($expected, $body);
    }

    /**
     * Test INFO page when no query parameters are present
     */
    public function testHandleINFO(): void
    {
        // Setup: Create request without query parameters
        $request = $this->createRequest('http://localhost/service.php');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            namespace: 'http://example.com/test',
            description: 'Test SOAP Service',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/html'], $response->getHeader('Content-Type'));

        // Validate HTML content
        $body = $response->getBody()->getContents();
        $this->assertStringContainsString('TestService', $body);
        $this->assertStringContainsString('Test SOAP Service', $body);
        $this->assertStringContainsString('add', $body);
        $this->assertStringContainsString('greet', $body);
    }

    /**
     * Test HTTP method handling with valid parameters
     */
    public function testHandleHTTPWithValidParameters(): void
    {
        // Setup: Create request with ?httpmethod=add&a=10&b=5
        $request = $this->createRequest('http://localhost/service.php?httpmethod=add&a=10&b=5');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/plain'], $response->getHeader('Content-Type'));

        // Validate response body
        $body = $response->getBody()->getContents();
        $this->assertEquals('OK|15', $body);
    }

    /**
     * Test HTTP method handling with optional parameters
     */
    public function testHandleHTTPWithOptionalParameters(): void
    {
        // Setup: Create request with ?httpmethod=greet (no name parameter)
        $request = $this->createRequest('http://localhost/service.php?httpmethod=greet');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());

        // Validate response body contains default greeting
        $body = $response->getBody()->getContents();
        $this->assertEquals('OK|Hello, World!', $body);
    }

    /**
     * Test HTTP method handling with missing required parameters
     */
    public function testHandleHTTPWithMissingParameters(): void
    {
        // Setup: Create request with ?httpmethod=add&a=10 (missing 'b')
        $request = $this->createRequest('http://localhost/service.php?httpmethod=add&a=10');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(['text/plain'], $response->getHeader('Content-Type'));

        // Validate error message
        $body = $response->getBody()->getContents();
        $this->assertEquals('ERR|Missing params b', $body);
    }

    /**
     * Test HTTP method handling with non-existent method
     */
    public function testHandleHTTPWithInvalidMethod(): void
    {
        // Setup: Create request with ?httpmethod=nonExistent
        $request = $this->createRequest('http://localhost/service.php?httpmethod=nonExistent');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(400, $response->getStatusCode());

        // Validate error message
        $body = $response->getBody()->getContents();
        $this->assertEquals('ERR|Method does not exists', $body);
    }

    /**
     * Test SOAP request with JSON array response
     */
    public function testHandleSOAPWithJSONArrayResponse(): void
    {
        // Setup: Create SOAP request with proper envelope
        $soapBody = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <getArray>
            <name>John</name>
        </getArray>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        $request = $this->createRequest(
            uri: 'http://localhost/service.php',
            method: 'POST',
            headers: [
                'SOAPAction' => '"urn:TestServiceAction"',
                'Content-Type' => 'text/xml; charset=utf-8'
            ],
            body: $soapBody
        );

        // Create mock handler to avoid SoapServer complexity
        $handler = new SoapHandlerMock(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            namespace: 'urn:TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response - should be SOAP XML response wrapping JSON data
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/xml'], $response->getHeader('Content-Type'));

        // The response should be XML with SOAP envelope
        $body = $response->getBody()->getContents();
        $expected = file_get_contents(__DIR__ . "/Fixtures/test_handle_soap_get_array.xml.txt");
        $this->assertEquals($expected, $body);
    }

    /**
     * Test HTTP method handling with JSON array response
     */
    public function testHandleHTTPWithJSONArrayResponse(): void
    {
        // Setup: Create request with ?httpmethod=getArray&name=test
        $request = $this->createRequest('http://localhost/service.php?httpmethod=getArray&name=John');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['application/json'], $response->getHeader('Content-Type'));

        // Validate JSON response
        $body = $response->getBody()->getContents();
        $this->assertJson($body);

        $data = json_decode($body, true);
        $this->assertEquals('John', $data['name']);
        $this->assertEquals('active', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    /**
     * Test HTTP method handling with JSON model response
     */
    public function testHandleHTTPWithJSONModelResponse(): void
    {
        // Setup: Create request with ?httpmethod=getUserJson&name=Alice&age=30
        $request = $this->createRequest('http://localhost/service.php?httpmethod=getUserJson&name=Alice&age=30');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['application/json'], $response->getHeader('Content-Type'));

        // Validate JSON response
        $body = $response->getBody()->getContents();
        $this->assertJson($body);

        $data = json_decode($body, true);
        $this->assertEquals('Alice', $data['name']);
        $this->assertEquals(30, $data['age']);
        $this->assertEquals('alice@example.com', $data['email']);
    }

    /**
     * Test HTTP method handling with XML model response
     */
    public function testHandleHTTPWithXMLModelResponse(): void
    {
        // Setup: Create request with ?httpmethod=getUserXml&name=Bob
        $request = $this->createRequest('http://localhost/service.php?httpmethod=getUserXml&name=Bob');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/xml'], $response->getHeader('Content-Type'));

        // Validate XML response
        $body = $response->getBody()->getContents();
        $body = $response->getBody()->getContents();
        $expected = file_get_contents(__DIR__ . "/Fixtures/test_handle_http_with_xml_model_response.xml.txt");
        $this->assertEquals($expected, $body);
    }

    /**
     * Test that SOAPAction header triggers SOAP server path
     *
     * This test uses SoapHandlerMock to avoid PHP's SoapServer complexity
     * and directly test the magic __call() method execution.
     */
    public function testHandleDetectsSOAPAction(): void
    {
        // Setup: Create SOAP request with proper envelope
        $soapBody = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <add>
            <a>10</a>
            <b>5</b>
        </add>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        $request = $this->createRequest(
            uri: 'http://localhost/service.php',
            method: 'POST',
            headers: [
                'SOAPAction' => '"urn:TestServiceAction"',
                'Content-Type' => 'text/xml; charset=utf-8'
            ],
            body: $soapBody
        );

        // Create mock handler to avoid SoapServer complexity
        $handler = new SoapHandlerMock(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            namespace: 'urn:TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Should get a successful XML response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/xml'], $response->getHeader('Content-Type'));

        // The response should be XML
        $body = $response->getBody()->getContents();
        $this->assertStringStartsWith('<?xml', $body);
        $this->assertStringContainsString('SOAP-ENV:Envelope', $body);
        $this->assertStringContainsString('SOAP-ENV:Body', $body);
        $this->assertStringContainsString('addResponse', $body);
        $this->assertStringContainsString('15', $body);
    }

    /**
     * Test handler with HTTPS protocol
     */
    public function testHandleWithHTTPS(): void
    {
        // Setup: Create request with https scheme
        $request = $this->createRequest('https://localhost/service.php?wsdl');

        // Manually set HTTPS in server params
        $serverParams = ['HTTPS' => 'on', 'HTTP_HOST' => 'localhost'];
        $uri = new Uri('https://localhost/service.php?wsdl');
        $request = new ServerRequest($uri, $serverParams);
        $request = $request->withMethod('GET');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());

        // Validate WSDL contains https URLs
        $body = $response->getBody()->getContents();
        $this->assertStringContainsString('https://', $body);
    }

    /**
     * Test handler with custom namespace
     */
    public function testHandlerWithCustomNamespace(): void
    {
        // Setup: Create request
        $request = $this->createRequest('http://localhost/service.php?wsdl');

        // Create handler with custom namespace
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'CustomService',
            namespace: 'http://custom.example.com/api/v1',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response contains custom namespace
        $body = $response->getBody()->getContents();
        $this->assertStringContainsString('CustomService', $body);
    }

    /**
     * Test handler without namespace (should use default)
     */
    public function testHandlerWithoutNamespace(): void
    {
        // Setup: Create request
        $request = $this->createRequest('http://localhost/service.php');

        // Create handler without namespace
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Should return HTML INFO page
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['text/html'], $response->getHeader('Content-Type'));

        // Validate: Handler should use default namespace
        $this->assertEquals('http://example.org/', $handler->namespace);

        // Validate: HTML body should contain warning about namespace
        // (The INFO page template shows a warning when warningNamespace is true)
        $body = $response->getBody()->getContents();
        $this->assertStringContainsString('TestService', $body);

        // The page should be HTML
        $this->assertStringContainsString('<html', strtolower($body));
    }

    /**
     * Test handler with POST request body parameters
     */
    public function testHandleHTTPWithPOSTParameters(): void
    {
        // Setup: Create POST request with form data
        $request = $this->createRequest(
            uri: 'http://localhost/service.php?httpmethod=add',
            method: 'POST',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: 'a=20&b=30'
        );

        // Parse body
        $request = $request->withParsedBody(['a' => '20', 'b' => '30']);

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());

        // Validate response contains sum
        $body = $response->getBody()->getContents();
        $this->assertEquals('OK|50', $body);
    }

    /**
     * Test type casting for integer parameters
     */
    public function testHandleHTTPWithTypeCasting(): void
    {
        // Setup: Create request with string values that should be cast to integers
        $request = $this->createRequest('http://localhost/service.php?httpmethod=add&a=7&b=8');

        // Create handler with mock request
        $handler = new SoapHandler(
            soapItems: $this->soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check response
        $this->assertEquals(200, $response->getStatusCode());

        // Validate correct integer addition
        $body = $response->getBody()->getContents();
        $this->assertEquals('OK|15', $body);
    }

    /**
     * Test handler with executor exception
     */
    public function testHandleHTTPWithExecutorException(): void
    {
        // Setup: Create operation that throws exception
        $errorOperation = new SoapOperationConfig();
        $errorOperation->description = 'Throws error';
        $errorOperation->args = [];
        $errorOperation->returnType = SoapType::String;
        $errorOperation->executor = function (array $params) {
            throw new Exception('Test exception');
        };

        $soapItems = ['error' => $errorOperation];

        $request = $this->createRequest('http://localhost/service.php?httpmethod=error');

        // Create handler
        $handler = new SoapHandler(
            soapItems: $soapItems,
            serviceName: 'TestService',
            request: $request
        );

        // Execute: Call handle()
        $response = $handler->handle();

        // Validate: Check error response
        $this->assertEquals(500, $response->getStatusCode());

        // Validate error message
        $body = $response->getBody()->getContents();
        $this->assertEquals('ERR|Test exception', $body);
    }
}
