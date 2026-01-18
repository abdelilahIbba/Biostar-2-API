<?php

namespace BioStarSync\Database;

/**
 * Client Detection Service
 * 
 * Detects newly added clients in HFSQL database that need to be synced
 */
class ClientDetector
{
    private $connection;
    private $config;
    private $logger;

    /**
     * Constructor
     * 
     * @param HFSQLConnection $connection Database connection
     * @param array $config HFSQL configuration
     * @param object $logger Logger instance
     */
    public function __construct(HFSQLConnection $connection, array $config, $logger)
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get new clients that haven't been synced to BioStar
     * 
     * @param int $limit Maximum number of clients to retrieve
     * @return array Array of client records
     */
    public function getNewClients($limit = 10)
    {
        $table = $this->config['Clients'];
        $syncFlag = $this->config['code'];
        $columns = $this->config['columns'];

        $query = "SELECT 
                    {$columns['id']},
                    {$columns['first_name']},
                    {$columns['last_name']},
                    {$columns['email']},
                    {$columns['phone']},
                    {$columns['photo_path']},
                    {$columns['member_id']}
                  FROM {$table}
                  WHERE {$syncFlag} = 0 OR {$syncFlag} IS NULL
                  ORDER BY {$columns['id']}
                  LIMIT ?";

        try {
            $this->logger->info("Searching for new clients (limit: {$limit})...");
            $results = $this->connection->query($query, [$limit]);
            
            $count = count($results);
            $this->logger->info("Found {$count} new client(s) to sync");
            
            // Normalize column names
            return array_map(function($row) {
                return $this->normalizeClientData($row);
            }, $results);
            
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving new clients: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark client as synced
     * 
     * @param string $clientId Client ID
     * @return bool Success status
     */
    public function markAsSynced($clientId)
    {
        $table = $this->config['client_table'];
        $syncFlag = $this->config['sync_flag_column'];
        $idColumn = $this->config['columns']['id'];

        $query = "UPDATE {$table} SET {$syncFlag} = 1 WHERE {$idColumn} = ?";

        try {
            $affected = $this->connection->execute($query, [$clientId]);
            
            if ($affected > 0) {
                $this->logger->info("Marked client {$clientId} as synced");
                return true;
            }
            
            $this->logger->warning("No rows updated for client {$clientId}");
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error("Error marking client {$clientId} as synced: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Normalize client data with standard field names
     * 
     * @param array $row Raw database row
     * @return array Normalized client data
     */
    private function normalizeClientData(array $row)
    {
        $columns = $this->config['columns'];
        
        return [
            'id' => $row[$columns['id']] ?? null,
            'first_name' => $row[$columns['first_name']] ?? '',
            'last_name' => $row[$columns['last_name']] ?? '',
            'email' => $row[$columns['email']] ?? '',
            'phone' => $row[$columns['phone']] ?? '',
            'photo_path' => $row[$columns['photo_path']] ?? '',
            'member_id' => $row[$columns['member_id']] ?? ''
        ];
    }

    /**
     * Get client by ID
     * 
     * @param string $clientId Client ID
     * @return array|null Client data or null if not found
     */
    public function getClientById($clientId)
    {
        $table = $this->config['client_table'];
        $idColumn = $this->config['columns']['id'];
        $columns = $this->config['columns'];

        $query = "SELECT 
                    {$columns['id']},
                    {$columns['first_name']},
                    {$columns['last_name']},
                    {$columns['email']},
                    {$columns['phone']},
                    {$columns['photo_path']},
                    {$columns['member_id']}
                  FROM {$table}
                  WHERE {$idColumn} = ?";

        try {
            $results = $this->connection->query($query, [$clientId]);
            
            if (empty($results)) {
                return null;
            }
            
            return $this->normalizeClientData($results[0]);
            
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving client {$clientId}: " . $e->getMessage());
            return null;
        }
    }
}
