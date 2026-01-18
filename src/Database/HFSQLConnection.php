<?php

namespace BioStarSync\Database;

use PDO;
use PDOException;

/**
 * HFSQL Database Connection Handler
 * 
 * Manages connection to HFSQL database via ODBC driver
 */
class HFSQLConnection
{
    private $config;
    private $pdo;
    private $logger;

    /**
     * Constructor
     * 
     * @param array $config HFSQL configuration
     * @param object $logger Logger instance
     */
    public function __construct(array $config, $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Establish database connection
     * 
     * @return bool Connection success status
     */
    public function connect()
    {
        try {
            $this->logger->info('Connecting to HFSQL database...');
            
            $this->pdo = new PDO(
                "odbc:{$this->config['dsn']}",
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            $this->logger->info('Successfully connected to HFSQL database');
            return true;
            
        } catch (PDOException $e) {
            $this->logger->error('HFSQL connection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get PDO instance
     * 
     * @return PDO
     */
    public function getPDO()
    {
        if (!$this->pdo) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Execute a query
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return array Query results
     */
    public function query($query, array $params = [])
    {
        try {
            $stmt = $this->getPDO()->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error('Query execution failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute an update/insert query
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return int Number of affected rows
     */
    public function execute($query, array $params = [])
    {
        try {
            $stmt = $this->getPDO()->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logger->error('Query execution failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Close database connection
     */
    public function close()
    {
        $this->pdo = null;
        $this->logger->info('HFSQL connection closed');
    }

    /**
     * Test database connection
     * 
     * @return bool Connection test result
     */
    public function testConnection()
    {
        try {
            // HFSQL doesn't support SELECT 1, query actual table instead
            $this->getPDO()->query('SELECT TOP 1 * FROM Clients');
            return true;
        } catch (PDOException $e) {
            $this->logger->error('Connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}
