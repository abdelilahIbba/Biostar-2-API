<?php

namespace BioStarSync\Database;

/**
 * Opera Fitness Data Extractor
 * 
 * Extracts client data from HFSQL database with Clients and Photos table join
 * for BioStar 2 synchronization
 */
class OperaFitnessExtractor
{
    private $connection;
    private $logger;

    /**
     * Constructor
     * 
     * @param HFSQLConnection $connection Database connection
     * @param object $logger Logger instance
     */
    public function __construct(HFSQLConnection $connection, $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * Get clients ready for BioStar 2 sync
     * Joins Clients and Photos tables where Photos.exporte = 0
     * Photos are loaded from file system using code as filename
     * 
     * @param int $limit Maximum number of clients to retrieve
     * @return array Array of client records mapped for BioStar 2
     */
    public function getClientsForSync($limit = 10)
    {
        $query = "SELECT 
                    c.nom,
                    c.prenom,
                    c.club,
                    c.code,
                    c.email,
                    c.tel2
                  FROM Clients c
                  INNER JOIN Photos p ON c.code = p.code
                  WHERE p.exporte = 0
                  ORDER BY c.code
                  LIMIT ?";

        try {
            $this->logger->info("Querying Opera Fitness database for unsynced clients (limit: {$limit})...");
            $results = $this->connection->query($query, [$limit]);
            
            $count = count($results);
            $this->logger->info("Found {$count} client(s) ready for BioStar 2 sync");
            
            // Format for BioStar 2 API
            return array_map(function($row) {
                return $this->formatForBioStar($row);
            }, $results);
            
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving Opera Fitness clients: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format client data for BioStar 2 API
     * 
     * @param array $row Raw database row
     * @return array Formatted client data
     */
    private function formatForBioStar(array $row)
    {
        // Build full name from nom and prenom
        $nom = trim($row['nom'] ?? '');
        $prenom = trim($row['prenom'] ?? '');
        $fullName = trim($prenom . ' ' . $nom);
        
        return [
            'name' => $fullName,
            'department' => $row['club'] ?? '',
            'user_id' => $this->extractNumericId($row['code'] ?? ''),
            'email' => $row['email'] ?? '',
            'telephone' => $row['tel2'] ?? '',
            'identifiant' => $fullName,
            'photo_path' => $row['code'] ?? '', // Use code as filename reference
            'code' => $row['code'] ?? '' // Keep original code for reference
        ];
    }

    /**
     * Extract numeric part from client code
     * Example: "AGAH000010" -> "000010"
     * 
     * @param string $code Client code
     * @return string Numeric portion of code
     */
    private function extractNumericId($code)
    {
        if (empty($code)) {
            return '';
        }

        // Extract numeric characters from the code
        preg_match('/(\d+)/', $code, $matches);
        
        if (!empty($matches[1])) {
            return $matches[1];
        }

        // If no numeric part found, return empty string
        $this->logger->warning("Could not extract numeric ID from code: {$code}");
        return '';
    }

    /**
     * Mark photo as exported/synced
     * 
     * @param string $code Client code
     * @return bool Success status
     */
    public function markPhotoAsExported($code)
    {
        $query = "UPDATE Photos SET exporte = 1 WHERE code = ?";

        try {
            $affected = $this->connection->execute($query, [$code]);
            
            if ($affected > 0) {
                $this->logger->info("Marked photo as exported for code: {$code}");
                return true;
            }
            
            $this->logger->warning("No photo record found to update for code: {$code}");
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error("Error marking photo as exported for code {$code}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get single client by code
     * 
     * @param string $code Client code
     * @return array|nnom,
                    c.prenom,
                    c.club,
                    c.code,
                    c.email,
                    c.tel2ifiant,
                    c.club,
                    c.code,
                    c.email,
                    c.tel2,
                    p.photo
                  FROM Clients c
                  INNER JOIN Photos p ON c.code = p.code
                  WHERE c.code = ?";

        try {
            $results = $this->connection->query($query, [$code]);
            
            if (empty($results)) {
                $this->logger->info("No client found with code: {$code}");
                return null;
            }
            
            return $this->formatForBioStar($results[0]);
            
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving client by code {$code}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get count of unsynced clients
     * 
     * @return int Number of clients pending sync
     */
    public function getUnsyncedCount()
    {
        $query = "SELECT COUNT(*) as count
                  FROM Clients c
                  INNER JOIN Photos p ON c.code = p.code
                  WHERE p.exporte = 0";

        try {
            $results = $this->connection->query($query);
            
            if (!empty($results)) {
                return (int)($results[0]['count'] ?? 0);
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting unsynced count: ' . $e->getMessage());
            return 0;
        }
    }
}
