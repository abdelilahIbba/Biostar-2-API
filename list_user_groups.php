<?php

/**
 * List BioStar 2 User Groups
 */

require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Utils\Logger;

$config = require 'config.php';
$logger = new Logger($config['logging']);

$logger->section('Listing BioStar 2 User Groups');

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

$sessionId = null;
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$sessionId) {
    $len = strlen($header);
    if (preg_match('/bs-session-id:\s*(.+)/i', $header, $matches)) {
        $sessionId = trim($matches[1]);
    }
    return $len;
});

curl_exec($ch);
curl_close($ch);

if (!$sessionId) {
    $logger->error("Login failed");
    exit(1);
}

$logger->info("Authenticated successfully");

// Get user groups
$ch = curl_init('http://localhost/api/user_groups');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'bs-session-id: ' . $sessionId
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$logger->info("HTTP Code: $httpCode");

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    if (isset($data['UserGroupCollection']['rows'])) {
        $groups = $data['UserGroupCollection']['rows'];
        $logger->info("Found " . count($groups) . " user group(s):");
        $logger->info(str_repeat('-', 80));
        
        foreach ($groups as $group) {
            $logger->info("ID: {$group['id']} | Name: {$group['name']}");
        }
        
        $logger->info(str_repeat('-', 80));
        
        if (!empty($groups)) {
            $logger->info("\nTo fix the sync, update config.php:");
            $logger->info("'default_user_group_id' => '{$groups[0]['id']}'");
        }
    } else {
        $logger->info("Response: $response");
    }
} else {
    $logger->error("Failed to get user groups: $response");
}

// Logout
$ch = curl_init('http://localhost/api/logout');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['bs-session-id: ' . $sessionId]);
curl_exec($ch);
curl_close($ch);
