<?php

use ByJG\SoapServer\SoapHandler;
use ByJG\SoapServer\SoapOperationConfig;
use ByJG\SoapServer\SoapParameter;
use ByJG\SoapServer\SoapType;
use ByJG\SoapServer\SoapTest;

require_once "vendor/autoload.php";

// Method 1: greet - using simple types
$greetItem = new SoapOperationConfig();
$greetItem->description = 'Greets a user';
$greetItem->args = [
    new SoapParameter('name', SoapType::String),
    new SoapParameter('age', SoapType::Integer, 0),
    new SoapParameter('title', SoapType::String, 0)
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
    new SoapParameter('user', SoapTest::class),
    new SoapParameter('action', SoapType::String, 0)
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

$processor->handle();