<?php

namespace BioStarSync\Utils;

/**
 * Photo Extraction Utility
 * 
 * Handles extraction and encoding of photo data from HFSQL Photos table
 */
class PhotoExtractor
{
    private $logger;

    /**
     * Constructor
     * 
     * @param object $logger Logger instance
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Process photo blob data to base64 encoded string
     * 
     * @param mixed $photoData Raw photo data from database (blob/binary)
     * @return string|null Base64 encoded photo or null on failure
     */
    public function processPhoto($photoData)
    {
        if (empty($photoData)) {
            $this->logger->warning('Photo data is empty');
            return null;
        }

        try {
            // If photo data is already a string (binary data), encode it
            if (is_string($photoData)) {
                $base64 = base64_encode($photoData);
                $this->logger->info('Photo encoded successfully (' . strlen($photoData) . ' bytes)');
                return $base64;
            }

            // If it's a resource (blob stream), read and encode
            if (is_resource($photoData)) {
                $binaryData = stream_get_contents($photoData);
                
                if ($binaryData === false) {
                    $this->logger->error('Failed to read photo stream');
                    return null;
                }

                $base64 = base64_encode($binaryData);
                $this->logger->info('Photo stream encoded successfully (' . strlen($binaryData) . ' bytes)');
                return $base64;
            }

            $this->logger->warning('Unexpected photo data type: ' . gettype($photoData));
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing photo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate photo data
     * 
     * @param mixed $photoData Raw photo data
     * @return bool Validation result
     */
    public function validatePhoto($photoData)
    {
        if (empty($photoData)) {
            return false;
        }

        try {
            // Check if it's a valid image format by attempting to get image info
            if (is_string($photoData)) {
                $imageInfo = @getimagesizefromstring($photoData);
                
                if ($imageInfo === false) {
                    $this->logger->warning('Photo data is not a valid image');
                    return false;
                }

                // Check if it's JPEG or PNG
                $mimeType = $imageInfo['mime'];
                $validTypes = ['image/jpeg', 'image/png'];
                
                if (!in_array($mimeType, $validTypes)) {
                    $this->logger->warning("Invalid image type: {$mimeType}");
                    return false;
                }

                // Check dimensions
                list($width, $height) = $imageInfo;
                
                if ($width < 100 || $height < 100) {
                    $this->logger->warning("Image dimensions too small: {$width}x{$height}");
                    return false;
                }

                $this->logger->info("Photo validated: {$mimeType}, {$width}x{$height}");
                return true;
            }

            return true; // Skip validation for non-string types (will be processed later)
            
        } catch (\Exception $e) {
            $this->logger->error('Photo validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get photo format/mime type
     * 
     * @param mixed $photoData Raw photo data
     * @return string|null Format (jpeg/png) or null
     */
    public function getPhotoFormat($photoData)
    {
        if (empty($photoData) || !is_string($photoData)) {
            return null;
        }

        try {
            $imageInfo = @getimagesizefromstring($photoData);
            
            if ($imageInfo === false) {
                return null;
            }

            $mimeType = $imageInfo['mime'];
            
            switch ($mimeType) {
                case 'image/jpeg':
                    return 'jpeg';
                case 'image/png':
                    return 'png';
                default:
                    return null;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error detecting photo format: ' . $e->getMessage());
            return null;
        }
    }
}
