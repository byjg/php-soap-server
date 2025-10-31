<?php

namespace ByJG\SoapServer;

use Psr\Http\Message\ResponseInterface;

/**
 * ResponseWriter - Outputs PSR-7 ResponseInterface to standard output
 *
 * This class takes a PSR-7 ResponseInterface object and sends it to the client
 * by setting HTTP headers and outputting the body content.
 *
 * Example:
 * ```php
 * $handler = new SoapHandler(...);
 * $response = $handler->handle();
 * ResponseWriter::output($response);
 * ```
 */
class ResponseWriter
{
    /**
     * Output a PSR-7 Response to standard output
     *
     * This method:
     * 1. Sets the HTTP status code
     * 2. Sets all response headers
     * 3. Outputs the response body
     *
     * @param ResponseInterface $response The PSR-7 response to output
     * @return void
     */
    public static function output(ResponseInterface $response): void
    {
        // Set HTTP status code
        http_response_code($response->getStatusCode());

        // Set all headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // Output the body
        echo $response->getBody();
    }
}
