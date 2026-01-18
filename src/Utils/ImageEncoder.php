<?php

namespace BioStarSync\Utils;

/**
 * Image Encoding Utility
 * 
 * Handles image file reading and base64 encoding for BioStar 2
 */
class ImageEncoder
{
    private $config;
    private $logger;

    /**
     * Constructor
     * 
     * @param array $config Image configuration
     * @param object $logger Logger instance
     */
    public function __construct(array $config, $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Encode image file to base64
     * 
     * @param string $imagePath Path to image file (relative or absolute)
     * @return string|null Base64-encoded image or null on failure
     */
    public function encodeImage($imagePath)
    {
        // Resolve full path
        $fullPath = $this->resolvePath($imagePath);
        
        if (!$fullPath) {
            $this->logger->error("Image file not found: {$imagePath}");
            return null;
        }

        // Check file exists
        if (!file_exists($fullPath)) {
            $this->logger->error("Image file does not exist: {$fullPath}");
            return null;
        }

        // Check file size
        $fileSize = filesize($fullPath);
        $maxSize = ($this->config['max_size_mb'] ?? 5) * 1024 * 1024;
        
        if ($fileSize > $maxSize) {
            $this->logger->error("Image file too large: {$fullPath} ({$fileSize} bytes)");
            return null;
        }

        // Check file format
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $allowedFormats = $this->config['allowed_formats'] ?? ['jpg', 'jpeg', 'png'];
        
        if (!in_array($extension, $allowedFormats)) {
            $this->logger->error("Unsupported image format: {$extension}");
            return null;
        }

        try {
            $imageData = file_get_contents($fullPath);
            
            if ($imageData === false) {
                $this->logger->error("Failed to read image file: {$fullPath}");
                return null;
            }

            $base64 = base64_encode($imageData);
            
            $this->logger->info("Image encoded successfully: {$fullPath} ({$fileSize} bytes)");
            
            return $base64;
            
        } catch (\Exception $e) {
            $this->logger->error("Error encoding image {$fullPath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve image path (handle relative and absolute paths)
     * 
     * @param string $imagePath Image path
     * @return string|null Resolved absolute path or null
     */
    private function resolvePath($imagePath)
    {
        if (empty($imagePath)) {
            return null;
        }

        // If absolute path, return as-is
        if ($this->isAbsolutePath($imagePath)) {
            return $imagePath;
        }

        // Try with base path
        $basePath = $this->config['base_path'] ?? '';
        
        if (!empty($basePath)) {
            $fullPath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $imagePath;
            
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Return original path if base path doesn't work
        return $imagePath;
    }

    /**
     * Check if path is absolute
     * 
     * @param string $path File path
     * @return bool True if absolute path
     */
    private function isAbsolutePath($path)
    {
        // Windows: C:\, D:\, etc.
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }

        // Unix: /path/to/file
        if (substr($path, 0, 1) === '/') {
            return true;
        }

        // UNC path: \\server\share
        if (substr($path, 0, 2) === '\\\\') {
            return true;
        }

        return false;
    }

    /**
     * Validate image file
     * 
     * @param string $imagePath Path to image file
     * @return bool Validation result
     */
    public function validateImage($imagePath)
    {
        $fullPath = $this->resolvePath($imagePath);
        
        if (!$fullPath || !file_exists($fullPath)) {
            return false;
        }

        // Check if it's a valid image
        $imageInfo = @getimagesize($fullPath);
        
        if ($imageInfo === false) {
            $this->logger->warning("Invalid image file: {$fullPath}");
            return false;
        }

        // Check dimensions (optional)
        list($width, $height) = $imageInfo;
        
        if ($width < 100 || $height < 100) {
            $this->logger->warning("Image dimensions too small: {$width}x{$height} for {$fullPath}");
            return false;
        }

        return true;
    }
}
