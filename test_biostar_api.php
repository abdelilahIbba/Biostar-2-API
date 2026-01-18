<?php

/**
 * Test BioStar 2 User Creation
 * Direct test of API without database
 */

require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Utils\Logger;

$config = require 'config.php';
$logger = new Logger($config['logging']);

$logger->section('Testing BioStar 2 User Creation');

// Authenticate
$ch = curl_init('http://localhost/api/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$loginPayload = json_encode([
    'User' => [
        'login_id' => 'admin',
        'password' => 'admin123'
    ]
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPayload);

// Capture headers
$sessionId = null;
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$sessionId) {
    $len = strlen($header);
    if (preg_match('/bs-session-id:\s*(.+)/i', $header, $matches)) {
        $sessionId = trim($matches[1]);
    }
    return $len;
});

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !$sessionId) {
    $logger->error("Login failed: HTTP $httpCode");
    exit(1);
}

$logger->info("Login successful, Session ID: $sessionId");

// Test 1: Create user with minimal data
$logger->info("Test 1: Creating user with minimal required fields...");

$testUser = [
    'User' => [
        'user_id' => 'TEST001',
        'name' => 'Test User'
    ]
];

$ch = curl_init('http://localhost/api/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'bs-session-id: ' . $sessionId
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testUser));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$logger->info("Response HTTP Code: $httpCode");
$logger->info("Response Body: $response");

if ($httpCode == 200 || $httpCode == 201) {
    $logger->info("✓ Test 1 PASSED: User created successfully");
} else {
    $logger->error("✗ Test 1 FAILED: User creation failed");
}

// Test 2: Create user with all fields
$logger->info("\nTest 2: Creating user with all fields...");

$testUser2 = [
    'User' => [
        'user_id' => 'TEST002',
        'name' => 'Test User Two',
        'email' => 'test@example.com',
        'phone' => '1234567890'
    ]
];

$ch = curl_init('http://localhost/api/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'bs-session-id: ' . $sessionId
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testUser2));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$logger->info("Response HTTP Code: $httpCode");
$logger->info("Response Body: $response");

if ($httpCode == 200 || $httpCode == 201) {
    $logger->info("✓ Test 2 PASSED: User with all fields created successfully");
} else {
    $logger->error("✗ Test 2 FAILED: User creation failed");
}

// Logout
$ch = curl_init('http://localhost/api/logout');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'bs-session-id: ' . $sessionId
]);
curl_exec($ch);
curl_close($ch);

$logger->info("\nTest completed");
