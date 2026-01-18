# Opera Fitness to BioStar 2 Client Sync Service

This service automatically syncs newly added clients from Opera Fitness (HFSQL database) to BioStar 2 access control system via REST API.

## Features

- Detects new clients in HFSQL database via ODBC
- Registers clients in BioStar 2 with personal information
- Uploads face images using BioStar 2's "Enroll Visual Face" feature
- Marks synced clients to prevent duplication
- Modular architecture with clear separation of concerns
- Comprehensive logging

## Requirements

- PHP 7.4 or higher
- PHP extensions:
  - `pdo_odbc` (for HFSQL database connection)
  - `curl` (for BioStar 2 API calls)
  - `gd` or `imagick` (for image processing)
- HFSQL ODBC driver installed and configured
- BioStar 2 server with API access

## Installation

1. Clone or download this project
2. Copy `config.sample.php` to `config.php`
3. Edit `config.php` with your database and API credentials
4. Ensure the `logs` directory is writable

## Configuration

Edit `config.php` with your settings:

### HFSQL Database Configuration
```php
'hfsql' => [
    'dsn' => 'Driver={HFSQL};Server=localhost;Port=4900;Database=OperaFitness',
    'username' => 'your_username',
    'password' => 'your_password',
    'client_table' => 'CLIENTS',
    'sync_flag_column' => 'SYNCED_TO_BIOSTAR'
]
```

### BioStar 2 API Configuration
```php
'biostar' => [
    'base_url' => 'https://your-biostar-server.com/api',
    'api_key' => 'your_api_key',
    'login_id' => 'admin',
    'password' => 'admin123'
]
```

### Client Field Mapping
Map your HFSQL columns to BioStar 2 user fields in the configuration file.

## Usage

Run the sync script:

```bash
php sync.php
```

### Scheduling (Windows)

Create a scheduled task to run every 5 minutes:
```
schtasks /create /tn "BioStarSync" /tr "C:\php\php.exe C:\path\to\sync.php" /sc minute /mo 5
```

### Scheduling (Linux/cron)

```
*/5 * * * * /usr/bin/php /path/to/sync.php
```

## Project Structure

```
biostar/
├── config.sample.php       # Sample configuration
├── config.php              # Your configuration (not in git)
├── sync.php                # Main entry point
├── src/
│   ├── Database/
│   │   ├── HFSQLConnection.php    # HFSQL database connection
│   │   └── ClientDetector.php     # Detect new clients
│   ├── BioStar/
│   │   └── BioStarAPI.php         # BioStar 2 API client
│   └── Utils/
│       ├── ImageEncoder.php       # Image encoding utilities
│       └── Logger.php             # Logging functionality
├── logs/                   # Log files directory
└── README.md              # This file
```

## Logging

Logs are stored in the `logs/` directory with daily rotation:
- `sync_YYYY-MM-DD.log` - General sync operations
- Logs include timestamps, levels (INFO, WARNING, ERROR), and messages

## Troubleshooting

### ODBC Connection Issues
- Verify HFSQL ODBC driver is installed: `odbcad32.exe` (Windows)
- Test DSN connection using ODBC Data Source Administrator
- Check server address, port, and credentials

### BioStar 2 API Issues
- Verify API endpoint URL is correct
- Check API key and login credentials
- Ensure BioStar 2 server is accessible from this machine
- Review API documentation for required user fields

### Image Upload Issues
- Ensure images are JPEG or PNG format
- Verify image file paths in HFSQL database are correct
- Check image file permissions
- BioStar 2 requires base64-encoded images

## Security Notes

- Never commit `config.php` to version control
- Store API credentials securely
- Use HTTPS for BioStar 2 API connections
- Restrict file permissions on config.php (chmod 600 on Linux)

## License

Proprietary - Internal use only
