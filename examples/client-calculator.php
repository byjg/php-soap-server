<?php

declare(strict_types=1);

/**
 * SOAP Client Example - Calculator Service
 *
 * This example demonstrates how to call a SOAP service created with
 * byjg/soap-server attributes.
 *
 * Prerequisites:
 * 1. The SOAP server must be running (simple-calculator.php)
 * 2. Access the WSDL at: http://localhost:8080?wsdl
 */

try {
    // Create SOAP client pointing to the WSDL
    $wsdlUrl = 'http://localhost:8080?wsdl';

    echo "Connecting to SOAP service at: $wsdlUrl\n";
    echo str_repeat("=", 60) . "\n\n";

    $client = new SoapClient($wsdlUrl, [
        'trace' => 1,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE, // Disable cache for development
    ]);

    echo "✓ Connected successfully!\n\n";

    // Call the add operation
    echo "Calling add(10, 5)...\n";
    $result = $client->add(10, 5);
    echo "Result: $result\n\n";

    // Call the subtract operation
    echo "Calling subtract(20, 8)...\n";
    $result = $client->subtract(20, 8);
    echo "Result: $result\n\n";

    // Call the multiply operation
    echo "Calling multiply(3.5, 2.0)...\n";
    $result = $client->multiply(3.5, 2.0);
    echo "Result: $result\n\n";

    // Call the divide operation
    echo "Calling divide(15.0, 3.0)...\n";
    $result = $client->divide(15.0, 3.0);
    echo "Result: $result\n\n";

//    // Call the greet operation (with default parameter)
//    echo "Calling greet()...\n";
//    $result = $client->greet();
//    echo "Result: $result\n\n";
//
//    // Call the greet operation (with custom name)
//    echo "Calling greet('Alice')...\n";
//    $result = $client->greet('Alice');
//    echo "Result: $result\n\n";

    echo str_repeat("=", 60) . "\n";
    echo "✓ All operations completed successfully!\n";

} catch (SoapFault $e) {
    echo "SOAP Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";

    if (isset($e->faultcode)) {
        echo "Fault Code: " . $e->faultcode . "\n";
    }
    if (isset($e->faultstring)) {
        echo "Fault String: " . $e->faultstring . "\n";
    }

    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
