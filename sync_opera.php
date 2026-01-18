<?php

/**
 * Opera Fitness to BioStar 2 Sync - Updated for Opera Database Structure
 * 
 * Uses OperaFitnessExtractor to query Clients and Photos tables
 */

require_once __DIR__ . '/src/Database/HFSQLConnection.php';
require_once __DIR__ . '/src/Database/OperaFitnessExtractor.php';
require_once __DIR__ . '/src/BioStar/BioStarAPI.php';
require_once __DIR__ . '/src/Utils/PhotoExtractor.php';
require_once __DIR__ . '/src/Utils/ImageEncoder.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Database\HFSQLConnection;
use BioStarSync\Database\OperaFitnessExtractor;
use BioStarSync\BioStar\BioStarAPI;
use BioStarSync\Utils\PhotoExtractor;
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
$logger->section('BioStar Sync Service Started - Opera Fitness Integration');

// Initialize components
$hfsqlConnection = new HFSQLConnection($config['hfsql'], $logger);
$extractor = new OperaFitnessExtractor($hfsqlConnection, $logger);
$photoExtractor = new PhotoExtractor($logger);
$imageEncoder = new ImageEncoder($config['image'], $logger);
$biostarAPI = new BioStarAPI($config['biostar'], $logger);

// Sync statistics
$stats = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'skipped' => 0
];

$unsyncedCount = 0;

try {
    // Connect to HFSQL database
    if (!$hfsqlConnection->connect()) {
        throw new Exception('Failed to connect to HFSQL database');
    }

    // Test connection
    if (!$hfsqlConnection->testConnection()) {
        throw new Exception('HFSQL database connection test failed');
    }

    // Get unsynced count
    $unsyncedCount = $extractor->getUnsyncedCount();
    $logger->info("Total unsynced clients: {$unsyncedCount}");

    // Authenticate with BioStar 2
    if (!$biostarAPI->authenticate()) {
        throw new Exception('Failed to authenticate with BioStar 2 API');
    }

    // Get clients to sync
    $batchSize = $config['sync']['batch_size'] ?? 10;
    $clients = $extractor->getClientsForSync($batchSize);
    $stats['total'] = count($clients);

    if (empty($clients)) {
        $logger->info('No new clients to sync');
    } else {
        // Process each client
        foreach ($clients as $client) {
            $logger->info("Processing client: {$client['code']} - {$client['name']}");

            // Skip clients with missing required data
            if (empty($client['code']) || empty($client['user_id'])) {
                $logger->warning("Skipping client with missing code or ID");
                $stats['skipped']++;
                continue;
            }

            // Skip clients with empty name
            if (empty($client['name'])) {
                $logger->warning("Skipping client {$client['code']} - missing name (identifiant)");
                $stats['skipped']++;
                continue;
            }

            try {
                // Process photo from file system
                $photoBase64 = null;
                
                if (!empty($client['photo_path'])) {
                    // Construct photo file path: C:\tmp\{code}.jpeg
                    $photoFile = $client['photo_path'] . '.jpeg';
                    $logger->info("Looking for photo file: {$photoFile}");
                    
                    // Try to encode photo from file
                    $photoBase64 = $imageEncoder->encodeImage($photoFile);
                    
                    if ($photoBase64) {
                        $logger->info("Photo file encoded successfully");
                    } else {
                        $logger->warning("Photo file not found or encoding failed: {$photoFile}");
                    }
                } else {
                    $logger->warning('No photo path provided');
                }

                // Prepare user data for BioStar 2
                $userData = [
                    'member_id' => $client['user_id'],
                    'first_name' => $client['name'],
                    'last_name' => '', // Opera Fitness uses single name field
                    'email' => !empty($client['email']) ? $client['email'] : '',
                    'phone' => !empty($client['telephone']) ? $client['telephone'] : ''
                ];

                // Validate user data before API call
                if (empty($userData['member_id']) || empty($userData['first_name'])) {
                    $logger->error("Invalid user data for client {$client['code']}: missing ID or name");
                    $stats['failed']++;
                    continue;
                }

                // Register client in BioStar 2
                $result = $biostarAPI->registerClientWithFace($userData, $photoBase64);

                if ($result) {
                    // Mark as exported in Photos table
                    if ($extractor->markPhotoAsExported($client['code'])) {
                        $stats['success']++;
                        $logger->info("Client {$client['code']} synced successfully");
                    } else {
                        $logger->error("Failed to mark client {$client['code']} as exported");
                        $stats['failed']++;
                    }
                } else {
                    $logger->error("Failed to register client {$client['code']} in BioStar 2");
                    $stats['failed']++;
                }

            } catch (Exception $e) {
                $logger->error("Error processing client {$client['code']}: " . $e->getMessage());
                $stats['failed']++;
            }

            $logger->info('---');
        }
    }

} catch (Exception $e) {
    $logger->error('Sync process error: ' . $e->getMessage());
    
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
    $logger->info("Remaining unsynced: " . ($unsyncedCount - $stats['success']));
    $logger->section('BioStar Sync Service Completed');
}

exit(0);
