<?php

namespace BioStarSync\Utils;

/**
 * Simple File Logger
 * 
 * Provides logging functionality with different log levels
 */
class Logger
{
    private $config;
    private $logFile;

    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 4;

    /**
     * Constructor
     * 
     * @param array $config Logging configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        
        if ($config['enabled']) {
            $this->initLogFile();
        }
    }

    /**
     * Initialize log file
     */
    private function initLogFile()
    {
        $logDir = $this->config['log_dir'];
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Create daily log file
        $date = date('Y-m-d');
        $this->logFile = $logDir . '/sync_' . $date . '.log';
    }

    /**
     * Log debug message
     * 
     * @param string $message Log message
     */
    public function debug($message)
    {
        $this->log($message, 'DEBUG', self::LEVEL_DEBUG);
    }

    /**
     * Log info message
     * 
     * @param string $message Log message
     */
    public function info($message)
    {
        $this->log($message, 'INFO', self::LEVEL_INFO);
    }

    /**
     * Log warning message
     * 
     * @param string $message Log message
     */
    public function warning($message)
    {
        $this->log($message, 'WARNING', self::LEVEL_WARNING);
    }

    /**
     * Log error message
     * 
     * @param string $message Log message
     */
    public function error($message)
    {
        $this->log($message, 'ERROR', self::LEVEL_ERROR);
    }

    /**
     * Write log message
     * 
     * @param string $message Log message
     * @param string $level Log level name
     * @param int $levelValue Log level value
     */
    private function log($message, $level, $levelValue)
    {
        if (!$this->config['enabled']) {
            return;
        }

        // Check if this level should be logged
        $configuredLevel = $this->getConfiguredLevel();
        
        if ($levelValue < $configuredLevel) {
            return;
        }

        // Format log entry
        $timestamp = date($this->config['date_format']);
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Write to file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);

        // Also output to console if running in CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }

    /**
     * Get configured log level value
     * 
     * @return int Log level value
     */
    private function getConfiguredLevel()
    {
        $levelMap = [
            'DEBUG' => self::LEVEL_DEBUG,
            'INFO' => self::LEVEL_INFO,
            'WARNING' => self::LEVEL_WARNING,
            'ERROR' => self::LEVEL_ERROR
        ];

        $configLevel = $this->config['log_level'] ?? 'INFO';
        
        return $levelMap[$configLevel] ?? self::LEVEL_INFO;
    }

    /**
     * Log section separator
     * 
     * @param string $title Section title
     */
    public function section($title)
    {
        $separator = str_repeat('=', 60);
        $this->info($separator);
        $this->info($title);
        $this->info($separator);
    }
}
