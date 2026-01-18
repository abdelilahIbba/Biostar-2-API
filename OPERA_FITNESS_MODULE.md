# Opera Fitness Data Extraction Module

This module extracts client data from Opera Fitness HFSQL database by joining the **Clients** and **Photos** tables, preparing data for BioStar 2 access control system synchronization.

## Database Schema

The module queries the following Opera Fitness tables:

### Clients Table
- `identifiant` - Client name/identifier
- `club` - Club/location
- `code` - Unique client code (e.g., "AGAH000010")
- `email` - Email address
- `tel2` - Telephone number

### Photos Table
- `code` - Links to Clients.code
- `photo` - Photo blob data (JPEG/PNG)
- `exporte` - Export status (0 = not synced, 1 = synced)

## Field Mappings to BioStar 2

| Opera Fitness Field | BioStar 2 Attribute | Transformation |
|---------------------|---------------------|----------------|
| `Clients.identifiant` | `name` | Direct mapping |
| `Clients.club` | `department` | Direct mapping |
| `Clients.code` | `user_id` | Extract numeric part (e.g., "AGAH000010" → "000010") |
| `Clients.email` | `email` | Direct mapping |
| `Clients.tel2` | `telephone` | Direct mapping |
| `Clients.identifiant` | `identifiant` | Direct mapping |
| `Photos.photo` | `photo` | Base64 encoding |

## Module Components

### 1. OperaFitnessExtractor.php
**Location:** `src/Database/OperaFitnessExtractor.php`

**Main Functions:**

```php
getClientsForSync($limit = 10)
```
- Queries Clients and Photos tables with INNER JOIN
- Filters by `Photos.exporte = 0` (unsynced clients)
- Returns array of formatted client records

```php
markPhotoAsExported($code)
```
- Updates `Photos.exporte = 1` after successful sync
- Prevents duplicate syncing

```php
extractNumericId($code)
```
- Extracts numeric portion from client code
- Example: "AGAH000010" → "000010"

```php
getUnsyncedCount()
```
- Returns count of clients pending sync

### 2. PhotoExtractor.php
**Location:** `src/Utils/PhotoExtractor.php`

**Main Functions:**

```php
processPhoto($photoData)
```
- Converts blob/binary photo data to base64 string
- Handles both string and resource (stream) data types

```php
validatePhoto($photoData)
```
- Validates image format (JPEG/PNG only)
- Checks minimum dimensions (100x100)

```php
getPhotoFormat($photoData)
```
- Detects image format from blob data

## Usage Examples

### Standalone Data Extraction

Run `extract_opera_data.php` to test data extraction:

```bash
php extract_opera_data.php
```

**Output:**
```
Client #1
  Code: AGAH000010
  BioStar Name: John Doe
  BioStar Department: Main Club
  BioStar ID: 000010
  BioStar Email: john@example.com
  BioStar Telephone: 1234567890
  BioStar Identifiant: John Doe
  Photo: Present (blob data)
  Photo Base64: /9j/4AAQSkZJRgABAQEAYABgAAD... (52847 chars)
  Photo Format: jpeg
```

### Full BioStar 2 Synchronization

Run `sync_opera.php` to sync with BioStar 2:

```bash
php sync_opera.php
```

This will:
1. Extract unsynced clients from Opera Fitness
2. Process and validate photos
3. Register users in BioStar 2
4. Enroll face images
5. Mark photos as exported (`exporte = 1`)

### Programmatic Usage

```php
<?php
require_once 'src/Database/HFSQLConnection.php';
require_once 'src/Database/OperaFitnessExtractor.php';
require_once 'src/Utils/PhotoExtractor.php';
require_once 'src/Utils/Logger.php';

use BioStarSync\Database\HFSQLConnection;
use BioStarSync\Database\OperaFitnessExtractor;
use BioStarSync\Utils\PhotoExtractor;
use BioStarSync\Utils\Logger;

// Load config
$config = require 'config.php';

// Initialize
$logger = new Logger($config['logging']);
$connection = new HFSQLConnection($config['hfsql'], $logger);
$extractor = new OperaFitnessExtractor($connection, $logger);
$photoExtractor = new PhotoExtractor($logger);

// Connect
$connection->connect();

// Get clients (limit 5)
$clients = $extractor->getClientsForSync(5);

// Process each client
foreach ($clients as $client) {
    echo "Code: {$client['code']}\n";
    echo "Name: {$client['name']}\n";
    echo "Department: {$client['department']}\n";
    echo "ID: {$client['user_id']}\n";
    
    // Process photo
    if ($client['photo']) {
        $base64 = $photoExtractor->processPhoto($client['photo']);
        echo "Photo: " . strlen($base64) . " chars\n";
    }
    
    echo "---\n";
}

// Cleanup
$connection->close();
```

## Output Data Structure

Each client record is an associative array:

```php
[
    'name' => 'John Doe',           // From Clients.identifiant
    'department' => 'Main Club',    // From Clients.club
    'user_id' => '000010',          // Numeric part of Clients.code
    'email' => 'john@example.com',  // From Clients.email
    'telephone' => '1234567890',    // From Clients.tel2
    'identifiant' => 'John Doe',    // From Clients.identifiant
    'photo' => [blob data],         // From Photos.photo (binary)
    'code' => 'AGAH000010'          // Original Clients.code (for reference)
]
```

## SQL Query Used

```sql
SELECT 
    c.identifiant,
    c.club,
    c.code,
    c.email,
    c.tel2,
    p.photo
FROM Clients c
INNER JOIN Photos p ON c.code = p.code
WHERE p.exporte = 0
ORDER BY c.code
LIMIT ?
```

## Configuration

Update `config.php` with your HFSQL connection details:

```php
'hfsql' => [
    'dsn' => 'Driver={HFSQL};Server=localhost;Port=4900;Database=OperaFitness',
    'username' => 'your_username',
    'password' => 'your_password',
    
    'clients_table' => 'Clients',
    'photos_table' => 'Photos',
    
    'identifiant' => 'identifiant',
    'club' => 'club',
    'code' => 'code',
    'email' => 'email',
    'tel2' => 'tel2',
    'photo' => 'photo',
    'exporte' => 'exporte'
]
```

## Error Handling

The module handles:
- Missing photo data (logs warning, continues)
- Invalid photo formats (logs warning, skips photo)
- Database connection errors (throws exception)
- Missing client fields (uses empty strings)
- Photo encoding failures (logs error, continues)

## Logging

All operations are logged to `logs/sync_YYYY-MM-DD.log`:

```
[2026-01-17 14:30:15] [INFO] Connecting to HFSQL database...
[2026-01-17 14:30:15] [INFO] Successfully connected to HFSQL database
[2026-01-17 14:30:15] [INFO] Querying Opera Fitness database for unsynced clients (limit: 10)...
[2026-01-17 14:30:15] [INFO] Found 3 client(s) ready for BioStar 2 sync
[2026-01-17 14:30:15] [INFO] Photo encoded successfully (15234 bytes)
```

## Testing

Test database connection and data extraction:

```bash
php extract_opera_data.php
```

Check the output and logs to verify:
- Database connection successful
- Client data retrieved correctly
- Numeric IDs extracted properly
- Photos encoded to base64
- Field mappings are correct

## Notes

- Only clients with `Photos.exporte = 0` are retrieved
- The `code` field numeric extraction handles alphanumeric codes
- Photos are validated before encoding (JPEG/PNG, min 100x100)
- After successful sync, `Photos.exporte` is set to 1
- All photo data is stored as blob in HFSQL and converted to base64 for BioStar 2
