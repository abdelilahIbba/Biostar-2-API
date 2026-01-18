<?php

/**
 * Opera Fitness to BioStar 2 Client Sync Service
 * Main Entry Point
 * 
 * This script detects new clients from Opera Fitness HFSQL database
 * and syncs them to BioStar 2 access control system with face enrollment
 */

require_once __DIR__ . '/src/Database/HFSQLConnection.php';
require_once __DIR__ . '/src/Database/ClientDetector.php';
require_once __DIR__ . '/src/BioStar/BioStarAPI.php';
require_once __DIR__ . '/src/Utils/ImageEncoder.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Database\HFSQLConnection;
use BioStarSync\Database\ClientDetector;
use BioStarSync\BioStar\BioStarAPI;
use BioStarSync\Utils\ImageEncoder;
use BioStarSync\Utils\Logger;

// Load configuration
$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    die("Configuration file not found. Please copy config.sample.php to config.php and configure it.\n");
}

$config = require $configFile;

// Initialize logger
$logger = new Logger($config['logging']);
$logger->section('BioStar Sync Service Started');

// Initialize components
$hfsqlConnection = new HFSQLConnection($config['hfsql'], $logger);
$clientDetector = new ClientDetector($hfsqlConnection, $config['hfsql'], $logger);
$biostarAPI = new BioStarAPI($config['biostar'], $logger);
$imageEncoder = new ImageEncoder($config['image'], $logger);

// Sync statistics
$stats = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'skipped' => 0
];

try {
    // Connect to HFSQL database
    if (!$hfsqlConnection->connect()) {
        throw new Exception('Failed to connect to HFSQL database');
    }

    // Test connection
    if (!$hfsqlConnection->testConnection()) {
        throw new Exception('HFSQL database connection test failed');
    }

    // Authenticate with BioStar 2
    if (!$biostarAPI->authenticate()) {
        throw new Exception('Failed to authenticate with BioStar 2 API');
    }

    // Get new clients
    $batchSize = $config['sync']['batch_size'] ?? 10;
    $newClients = $clientDetector->getNewClients($batchSize);
    $stats['total'] = count($newClients);

    if (empty($newClients)) {
        $logger->info('No new clients to sync');
    } else {
        // Process each client
        foreach ($newClients as $client) {
            $logger->info("Processing client: {$client['id']} - {$client['first_name']} {$client['last_name']}");

            try {
                // Encode face image if available
                $imageBase64 = null;
                
                if (!empty($client['photo_path'])) {
                    $logger->info("Processing face image: {$client['photo_path']}");
                    
                    if ($imageEncoder->validateImage($client['photo_path'])) {
                        $imageBase64 = $imageEncoder->encodeImage($client['photo_path']);
                        
                        if ($imageBase64) {
                            $logger->info('Face image encoded successfully');
                        } else {
                            $logger->warning('Failed to encode face image');
                        }
                    } else {
                        $logger->warning('Face image validation failed');
                    }
                } else {
                    $logger->warning('No face image path provided for client');
                }

                // Register client in BioStar 2
                $result = $biostarAPI->registerClientWithFace($client, $imageBase64);

                if ($result) {
                    // Mark as synced in HFSQL
                    if ($clientDetector->markAsSynced($client['id'])) {
                        $stats['success']++;
                        $logger->info("Client {$client['id']} synced successfully");
                    } else {
                        $logger->error("Failed to mark client {$client['id']} as synced");
                        $stats['failed']++;
                    }
                } else {
                    $logger->error("Failed to register client {$client['id']} in BioStar 2");
                    $stats['failed']++;
                }

            } catch (Exception $e) {
                $logger->error("Error processing client {$client['id']}: " . $e->getMessage());
                $stats['failed']++;
            }

            $logger->info('---');
        }
    }

} catch (Exception $e) {
    $logger->error('Sync process error: ' . $e->getMessage());
    $stats['failed']++;
    
} finally {
    // Cleanup
    $biostarAPI->logout();
    $hfsqlConnection->close();
    
    // Log statistics
    $logger->section('Sync Summary');
    $logger->info("Total clients: {$stats['total']}");
    $logger->info("Successfully synced: {$stats['success']}");
    $logger->info("Failed: {$stats['failed']}");
    $logger->info("Skipped: {$stats['skipped']}");
    $logger->section('BioStar Sync Service Completed');
}

exit(0);
