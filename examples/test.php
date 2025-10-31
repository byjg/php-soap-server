<?php

use ByJG\SoapServer\ResponseWriter;
use ByJG\SoapServer\SoapHandler;
use ByJG\SoapServer\SoapOperationConfig;
use ByJG\SoapServer\SoapParameterConfig;
use ByJG\SoapServer\SoapTest;
use ByJG\SoapServer\SoapType;

require_once "vendor/autoload.php";

// Method 1: greet - using simple types
$greetItem = new SoapOperationConfig();
$greetItem->description = 'Greets a user';
$greetItem->args = [
    new SoapParameterConfig('name', SoapType::String),
    new SoapParameterConfig('age', SoapType::Integer, 0),
    new SoapParameterConfig('title', SoapType::String, 0)
];
$greetItem->returnType = SoapType::String;
$greetItem->executor = function(array $params) {
    $age = $params['age'] ?? 'unknown';
    $title = $params['title'] ?? '';
    return "{$title} {$params['name']}, age: {$age}";
};

// Method 2: processUser - using complex type (SoapTest class)
$processUserItem = new SoapOperationConfig();
$processUserItem->description = 'Processes user information';
$processUserItem->args = [
    new SoapParameterConfig('user', SoapTest::class),
    new SoapParameterConfig('action', SoapType::String, 0)
];
$processUserItem->returnType = SoapType::String;
$processUserItem->executor = function(array $params) {
    $user = $params['user'];
    $action = $params['action'] ?? 'process';

    // In real SOAP, $user would be a SoapTest object
    // For now, just return a message
    return "Processing user with action: {$action}";
};

$processor = new SoapHandler([
    'greet' => $greetItem,
    'processUser' => $processUserItem
], 'MyService');

$response = $processor->handle();
ResponseWriter::output($response);