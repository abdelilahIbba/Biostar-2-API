<?php

/**
 * Debug Script - Check actual data from Opera Fitness
 */

require_once __DIR__ . '/src/Database/HFSQLConnection.php';
require_once __DIR__ . '/src/Database/OperaFitnessExtractor.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use BioStarSync\Database\HFSQLConnection;
use BioStarSync\Database\OperaFitnessExtractor;
use BioStarSync\Utils\Logger;

$config = require 'config.php';
$logger = new Logger($config['logging']);

$logger->section('Debug: Raw Data from Opera Fitness Database');

$connection = new HFSQLConnection($config['hfsql'], $logger);
$connection->connect();

// Query raw data - SELECT ALL columns
$query = "SELECT TOP 3 * FROM Clients c
          INNER JOIN Photos p ON c.code = p.code
          WHERE p.exporte = 0";

try {
    $results = $connection->query($query);
    
    $logger->info("Found " . count($results) . " records");
    $logger->info(str_repeat('-', 80));
    
    foreach ($results as $index => $row) {
        $logger->info("Record #" . ($index + 1));
        $logger->info("  Raw Data:");
        foreach ($row as $key => $value) {
            $displayValue = is_null($value) ? '(NULL)' : (empty($value) ? '(EMPTY)' : $value);
            $logger->info("    $key: $displayValue");
        }
        $logger->info(str_repeat('-', 80));
    }
    
} catch (Exception $e) {
    $logger->error("Query failed: " . $e->getMessage());
}

$connection->close();
