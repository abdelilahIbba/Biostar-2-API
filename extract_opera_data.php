<?php

/**
 * Opera Fitness Data Extraction Example
 * 
 * Demonstrates how to extract client data from HFSQL database
 * with Clients and Photos table join for BioStar 2 synchronization
 */

require_once __DIR__ . '/src/Database/HFSQLConnection.php';
require_once __DIR__ . '/src/Database/OperaFitnessExtractor.php';
require_once __DIR__ . '/src/Utils/PhotoExtractor.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Database\HFSQLConnection;
use BioStarSync\Database\OperaFitnessExtractor;
use BioStarSync\Utils\PhotoExtractor;
use BioStarSync\Utils\Logger;

// Load configuration
$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    die("Configuration file not found. Please copy config.sample.php to config.php and configure it.\n");
}

$config = require $configFile;

// Initialize logger
$logger = new Logger($config['logging']);
$logger->section('Opera Fitness Data Extraction');

// Initialize components
$hfsqlConnection = new HFSQLConnection($config['hfsql'], $logger);
$extractor = new OperaFitnessExtractor($hfsqlConnection, $logger);
$photoExtractor = new PhotoExtractor($logger);

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
    $logger->info("Total unsynced clients in database: {$unsyncedCount}");

    // Extract clients for sync
    $batchSize = $config['sync']['batch_size'] ?? 10;
    $clients = $extractor->getClientsForSync($batchSize);

    if (empty($clients)) {
        $logger->info('No clients found for synchronization');
    } else {
        $logger->info('Extracted client data:');
        $logger->info(str_repeat('-', 80));

        foreach ($clients as $index => $client) {
            $logger->info("Client #" . ($index + 1));
            $logger->info("  Code: {$client['code']}");
            $logger->info("  BioStar Name: {$client['name']}");
            $logger->info("  BioStar Department: {$client['department']}");
            $logger->info("  BioStar ID: {$client['user_id']}");
            $logger->info("  BioStar Email: {$client['email']}");
            $logger->info("  BioStar Telephone: {$client['telephone']}");
            $logger->info("  BioStar Identifiant: {$client['identifiant']}");

            // Process photo
            if (!empty($client['photo'])) {
                $logger->info("  Photo: Present (blob data)");
                
                // Extract and encode photo
                $base64Photo = $photoExtractor->processPhoto($client['photo']);
                
                if ($base64Photo) {
                    $logger->info("  Photo Base64: " . substr($base64Photo, 0, 50) . "... (" . strlen($base64Photo) . " chars)");
                    
                    // Validate photo
                    $photoFormat = $photoExtractor->getPhotoFormat($client['photo']);
                    if ($photoFormat) {
                        $logger->info("  Photo Format: {$photoFormat}");
                    }
                } else {
                    $logger->warning("  Photo encoding failed");
                }
            } else {
                $logger->warning("  Photo: Not available");
            }

            $logger->info(str_repeat('-', 80));
        }

        // Output array structure for reference
        $logger->section('Output Array Structure');
        $logger->info('Each client record contains:');
        $logger->info('  - name: Client identifiant (for BioStar name)');
        $logger->info('  - department: Client club (for BioStar department)');
        $logger->info('  - user_id: Numeric part of code (for BioStar ID)');
        $logger->info('  - email: Client email (for BioStar email)');
        $logger->info('  - telephone: Client tel2 (for BioStar telephone)');
        $logger->info('  - identifiant: Client identifiant (for BioStar identifiant)');
        $logger->info('  - photo: Photo blob data (ready for base64 encoding)');
        $logger->info('  - code: Original client code (for reference/marking as synced)');

        // Example: Access the clean array
        $logger->section('Ready for BioStar 2 API Sync');
        $logger->info('Total records extracted: ' . count($clients));
        $logger->info('Data structure is ready for BioStar 2 API integration');
    }

} catch (Exception $e) {
    $logger->error('Extraction error: ' . $e->getMessage());
    
} finally {
    // Cleanup
    $hfsqlConnection->close();
    $logger->section('Extraction Completed');
}

// Return clean array (useful if included in another script)
return $clients ?? [];
