# TESTING CHECKLIST

## Project Analysis

✅ **PROJECT IS COMPLETE**

All required components are in place:
- HFSQL database connection (ODBC)
- Opera Fitness data extraction (Clients + Photos join)
- BioStar 2 API integration (login with session ID)
- Photo processing (blob to base64)
- Sync marking (Photos.exporte flag)
- Logging system
- Configuration files
- Documentation

---

## Step-by-Step Testing Guide

### STEP 1: Check ODBC DSN Configuration

**Connection Details:**
- Server: 127.0.0.1
- Port: 4900
- Database: GOLDENGYM
- Username: admin
- Password: (empty)

**Note:** Connection uses direct ODBC driver string, no DSN configuration needed

---

### STEP 2: Test HFSQL Database Connection

**Run this command:**
```bash
php -r "require 'config.php'; $config = require 'config.php'; try { $pdo = new PDO('odbc:' . $config['hfsql']['dsn'], $config['hfsql']['username'], $config['hfsql']['password']); echo 'HFSQL Connection: SUCCESS\n'; } catch (Exception $e) { echo 'HFSQL Connection: FAILED - ' . $e->getMessage() . '\n'; }"
```

**Expected Result:**
```
HFSQL Connection: SUCCESS
```

**If Failed:**
- Check HFSQL server is running on 127.0.0.1:4900
- Verify database name: GOLDENGYM
- Verify username: admin (password is empty)
- Check HFSQL ODBC driver is installed
- Check firewall allows port 4900

---

### STEP 3: Test BioStar 2 API Connection

**Run this command:**
```bash
php -r "$ch = curl_init('http://localhost/api/login'); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); curl_setopt($ch, CURLOPT_POSTFIELDS, '{\"User\":{\"login_id\":\"admin\",\"password\":\"admin123\"}}'); $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); echo ($code == 200) ? 'BioStar API: SUCCESS\n' : 'BioStar API: FAILED (HTTP ' . $code . ')\n';"
```

**Expected Result:**
```
BioStar API: SUCCESS
```

**If Failed:**
- Check BioStar 2 server is running
- Verify URL in config.php (should be `http://localhost/api`)
- Verify login credentials (admin/admin123)
- Check firewall settings

---

### STEP 4: Test Opera Fitness Data Extraction

**Run this command:**
```bash
php extract_opera_data.php
```

**Expected Output:**
```
==================== Opera Fitness Data Extraction ====================
[2026-01-17 14:30:15] [INFO] Connecting to HFSQL database...
[2026-01-17 14:30:15] [INFO] Successfully connected to HFSQL database
[2026-01-17 14:30:15] [INFO] Total unsynced clients in database: 5
[2026-01-17 14:30:15] [INFO] Querying Opera Fitness database...
[2026-01-17 14:30:15] [INFO] Found 5 client(s) ready for BioStar 2 sync

Client #1
  Code: AGAH000010
  BioStar Name: John Doe
  BioStar ID: 000010
  Photo: Present (blob data)
```

**What This Tests:**
- HFSQL connection works
- Tables "Clients" and "Photos" exist
- JOIN query works
- Data can be extracted
- Photos exist with exporte=0

**If No Clients Found:**
- Check Photos table has records with `exporte = 0`
- Verify table names: "Clients" and "Photos" (case-sensitive)
- Verify column names match config.php

---

### STEP 5: Test Full Synchronization (DRY RUN)

**Edit config.php temporarily:**
```php
'batch_size' => 1,  // Change to 1 for testing
```

**Run this command:**
```bash
php sync_opera.php
```

**Expected Output:**
```
==================== BioStar Sync Service Started ====================
[2026-01-17 14:35:20] [INFO] Connecting to HFSQL database...
[2026-01-17 14:35:20] [INFO] Successfully connected
[2026-01-17 14:35:20] [INFO] Authenticating with BioStar 2 API...
[2026-01-17 14:35:20] [INFO] Successfully authenticated with BioStar 2
[2026-01-17 14:35:20] [INFO] Total unsynced clients: 5
[2026-01-17 14:35:20] [INFO] Processing client: AGAH000010 - John Doe
[2026-01-17 14:35:20] [INFO] Photo encoded successfully (format: jpeg)
[2026-01-17 14:35:21] [INFO] Creating user: 000010 - John Doe
[2026-01-17 14:35:21] [INFO] User created successfully with ID: 123
[2026-01-17 14:35:21] [INFO] Enrolling visual face for user ID: 123
[2026-01-17 14:35:22] [INFO] Visual face enrolled successfully
[2026-01-17 14:35:22] [INFO] Marked photo as exported for code: AGAH000010
[2026-01-17 14:35:22] [INFO] Client AGAH000010 synced successfully

==================== Sync Summary ====================
Total clients: 1
Successfully synced: 1
Failed: 0
```

**What This Tests:**
- Full end-to-end sync
- Photo extraction from blob
- BioStar 2 user creation
- Face enrollment
- Database update (exporte flag)

---

### STEP 6: Verify in BioStar 2

**Check the synced user:**
1. Open BioStar 2 web interface
2. Go to User Management
3. Look for the newly created user (e.g., "John Doe" with ID "000010")
4. Check if face image is enrolled
5. Verify user details (name, email, phone, department)

---

### STEP 7: Verify in HFSQL Database

**Check the Photos table:**
```sql
SELECT code, exporte FROM Photos WHERE code = 'AGAH000010'
```

**Expected Result:**
```
code         | exporte
AGAH000010   | 1
```

The `exporte` flag should be `1` (synced).

---

## Quick Test Commands

**Test Everything:**
```bash
# 1. Test extraction only (no sync)
php extract_opera_data.php

# 2. Test sync (1 client)
php sync_opera.php
```

**Check Logs:**
```bash
# View today's log
type logs\sync_2026-01-17.log
```

---

## Common Issues

### Issue: "ODBC Driver not found"
**Solution:** Install HFSQL ODBC driver

### Issue: "No session ID in response"
**Solution:** Check BioStar 2 URL (should end with `/api`, not `/swagger`)

### Issue: "No clients found"
**Solution:** Check `Photos.exporte = 0` records exist

### Issue: "Photo encoding failed"
**Solution:** Check Photos.photo column contains valid JPEG/PNG blob data

---

## Production Deployment

Once testing is complete:

1. **Set batch size to desired value:**
   ```php
   'batch_size' => 10,  // In config.php
   ```

2. **Schedule automatic sync (Windows):**
   ```cmd
   schtasks /create /tn "OperaBioStarSync" /tr "C:\php\php.exe C:\Users\T490s Ha\Desktop\biostar\sync_opera.php" /sc minute /mo 5
   ```

3. **Monitor logs daily:**
   ```
   logs\sync_YYYY-MM-DD.log
   ```

---

## Success Criteria

✅ HFSQL connection successful  
✅ BioStar 2 authentication successful  
✅ Clients extracted from Opera Fitness  
✅ Photos processed and encoded  
✅ Users created in BioStar 2  
✅ Faces enrolled successfully  
✅ Photos marked as exported (exporte=1)  
✅ No errors in logs  

**If all checks pass: SERVICE IS READY FOR PRODUCTION**
