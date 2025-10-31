<?php

namespace Test\Fixtures;

use ByJG\SoapServer\SoapHandler;
use ByJG\WebRequest\Exception\MessageException;
use ByJG\WebRequest\Psr7\MemoryStream;
use ByJG\WebRequest\Psr7\Response;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

class SoapHandlerMock extends SoapHandler
{
    /**
     * Override createServer to avoid SoapServer complexity in tests
     *
     * This mock implementation simulates SOAP request handling by:
     * 1. Parsing XML body to extract method name and parameters
     * 2. Calling the magic __call() method directly
     * 3. Returning a SOAP-like XML response
     *
     * This is generic and works with any SOAP operation defined in the handler.
     *
     * @return ResponseInterface
     * @throws MessageException
     */
    protected function createServer(): ResponseInterface
    {
        try {
            $body = $this->getRequest()->getBody()->getContents();

            // Parse the SOAP body to extract method name and parameters
            $parsedData = $this->parseSOAPBody($body);
            $methodName = $parsedData['method'];
            $params = $parsedData['params'];

            // Call the magic __call method directly
            $result = $this->__call($methodName, $params);

            // Create a SOAP response
            return $this->createSOAPResponse($methodName, $result);

        } catch (Exception $e) {
            // Return SOAP fault for any error
            return $this->createSOAPFault($e->getMessage());
        }
    }

    /**
     * Parse SOAP body to extract method name and parameters
     *
     * @param string $body SOAP XML body
     * @return array{method: string, params: array} Method name and parameters
     */
    private function parseSOAPBody(string $body): array
    {
        // Load XML
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            throw new Exception('Invalid SOAP XML');
        }

        // Register SOAP namespace
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        // Get the first element inside SOAP Body (this is the method call)
        $bodyElements = $xml->xpath('//soap:Body/*');
        if (empty($bodyElements)) {
            throw new Exception('No method found in SOAP Body');
        }

        $methodElement = $bodyElements[0];
        $methodName = $methodElement->getName();

        // Extract parameters as array
        $params = [];
        foreach ($methodElement->children() as $param) {
            $params[] = (string)$param;
        }

        return [
            'method' => $methodName,
            'params' => $params
        ];
    }

    /**
     * Create a SOAP success response
     *
     * @param string $methodName Method name
     * @param mixed $result Result value
     * @return ResponseInterface
     * @throws MessageException
     */
    private function createSOAPResponse(string $methodName, mixed $result): ResponseInterface
    {
        $resultString = is_scalar($result) ? (string)$result : json_encode($result);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <ns1:' . htmlspecialchars($methodName) . 'Response xmlns:ns1="' . htmlspecialchars($this->namespace) . '">
            <return>' . htmlspecialchars($resultString) . '</return>
        </ns1:' . htmlspecialchars($methodName) . 'Response>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        return Response::getInstance(200)
            ->withHeader('Content-Type', 'text/xml')
            ->withBody(new MemoryStream($xml));
    }

    /**
     * Create a SOAP fault response
     *
     * @param string $message Fault message
     * @return ResponseInterface
     * @throws MessageException
     */
    private function createSOAPFault(string $message): ResponseInterface
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
    <SOAP-ENV:Body>
        <SOAP-ENV:Fault>
            <faultcode>SOAP-ENV:Server</faultcode>
            <faultstring>' . htmlspecialchars($message) . '</faultstring>
        </SOAP-ENV:Fault>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

        return Response::getInstance(200)
            ->withHeader('Content-Type', 'text/xml')
            ->withBody(new MemoryStream($xml));
    }

    /**
     * Expose getRequest for testing
     */
    public function getRequest(): ServerRequestInterface
    {
        // Use reflection to access private $request property from parent class
        $reflection = new ReflectionClass(parent::class);
        $property = $reflection->getProperty('request');
        return $property->getValue($this);
    }
}