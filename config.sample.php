<?php

/**
 * Configuration Sample for Opera Fitness to BioStar 2 Sync Service
 * 
 * Copy this file to config.php and fill in your actual credentials
 */

return [
    // HFSQL Database Configuration (Opera Fitness)
    'hfsql' => [
        // ODBC DSN connection string
        'dsn' => 'Driver={HFSQL};Server=localhost;Port=4900;Database=OperaFitness',
        'username' => 'admin',
        'password' => 'password',
        
        // Opera Fitness table structure
        'clients_table' => 'Clients',
        'photos_table' => 'Photos',
        
        // Field mappings to BioStar 2
        // Clients table fields
        'identifiant' => 'identifiant', // Client name
        'club' => 'club',               // Department/club
        'code' => 'code',               // Client code (e.g., AGAH000010)
        'email' => 'email',             // Email address
        'tel2' => 'tel2',               // Phone number
        
        // Photos table fields
        'photo' => 'photo',             // Photo blob data
        'exporte' => 'exporte'          // Export flag (0 = not synced, 1 = synced)
    ],
    
    // BioStar 2 API Configuration
    'biostar' => [
        'base_url' => 'https://your-biostar-server.com/api', // BioStar 2 API base URL
        'api_key' => 'your_api_key_here', // Optional: if using API key authentication
        
        // Admin credentials for login
        'login_id' => 'admin',
        'password' => 'admin123',
        
        // User configuration
        'default_user_group_id' => '', // Optional: default user group ID in BioStar 2
        'access_groups' => [], // Array of access group IDs to assign to new users
        
        // Visual face enrollment settings
        'face_settings' => [
            'face_level' => 'NORMAL', // NORMAL, HIGH, or HIGHEST
            'auto_assign_card' => false
        ]
    ],
    
    // Logging Configuration
    'logging' => [
        'enabled' => true,
        'log_dir' => __DIR__ . '/logs',
        'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'date_format' => 'Y-m-d H:i:s'
    ],
    
    // Sync Configuration
    'sync' => [
        'batch_size' => 10, // Number of clients to process per run
        'retry_failed' => true, // Retry previously failed syncs
        'max_retries' => 3
    ],
    
    // Image Processing
    'image' => [
        'base_path' => 'C:/OperaFitness/Photos/', // Base directory for client photos (if using file paths)
        'allowed_formats' => ['jpg', 'jpeg', 'png'],
        'max_size_mb' => 5 // Maximum file size in MB
    ],
    
    // Opera Fitness specific settings
    'opera' => [
        'extract_numeric_id' => true, // Extract numeric part from code for BioStar ID
        'numeric_pattern' => '/(\d+)/' // Regex pattern to extract numeric ID
    ]
];
