<?php
/**
 * Test BioStar 2 User Creation with Different Payloads
 * Debugging HTTP 500 errors
 */

$config = require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Utils\Logger;

$logger = new Logger($config);

$logger->info('============================================================');
$logger->info('Testing BioStar 2 User Creation - Minimal Fields');
$logger->info('============================================================');

// Initialize cURL
$baseUrl = rtrim($config['biostar']['base_url'], '/');

// Step 1: Login
$logger->info('Step 1: Authenticating...');
$ch = curl_init($baseUrl . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'User' => [
        'login_id' => $config['biostar']['login_id'],
        'password' => $config['biostar']['password']
    ]
]));
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

// Extract session ID
preg_match('/bs-session-id:\s*([a-f0-9]+)/i', $headers, $matches);
$sessionId = $matches[1] ?? null;

if (!$sessionId) {
    $logger->error('Failed to authenticate');
    exit(1);
}

$logger->info("Authenticated. Session ID: $sessionId");

// Step 2: Test different user creation payloads
$tests = [
    'Test 1: Minimal with user_group_id as object' => [
        'User' => [
            'user_id' => 'TEST001',
            'name' => 'Test User One',
            'user_group_id' => [
                'id' => 1
            ]
        ]
    ],
    'Test 2: Add required datetime fields' => [
        'User' => [
            'user_id' => 'TEST002',
            'name' => 'Test User Two',
            'user_group_id' => [
                'id' => 1
            ],
            'disabled' => false,
            'start_datetime' => '2001-01-01T00:00:00.00Z',
            'expiry_datetime' => '2030-12-31T23:59:00.00Z'
        ]
    ],
    'Test 3: Add email and phone' => [
        'User' => [
            'user_id' => 'TEST003',
            'name' => 'Test User Three',
            'user_group_id' => [
                'id' => 1
            ],
            'disabled' => false,
            'start_datetime' => '2001-01-01T00:00:00.00Z',
            'expiry_datetime' => '2030-12-31T23:59:00.00Z',
            'email' => 'test@example.com',
            'phone' => '+212600000000'
        ]
    ]
];

foreach ($tests as $testName => $payload) {
    $logger->info('');
    $logger->info("$testName");
    $logger->info("Payload: " . json_encode($payload));
    
    $ch = curl_init($baseUrl . '/users');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'bs-session-id: ' . $sessionId
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $logger->info("HTTP Code: $httpCode");
    $logger->info("Response: $response");
    
    if ($httpCode == 200 || $httpCode == 201) {
        $logger->info("✓ SUCCESS!");
        break; // Stop on first success
    }
}

// Logout
$logger->info('');
$logger->info('Logging out...');
$ch = curl_init($baseUrl . '/logout');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'bs-session-id: ' . $sessionId
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_exec($ch);
curl_close($ch);

$logger->info('Done');
