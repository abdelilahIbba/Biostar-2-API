# BioStar 2 API Login Process Documentation

This document explains how to authenticate with BioStar 2 REST API and use the session ID for subsequent requests.

## Login Endpoint

**Endpoint:** `POST /api/login`

**Request Format:**

```http
POST http://localhost/api/login
Content-Type: application/json
Accept: application/json

{
  "User": {
    "login_id": "admin",
    "password": "admin123"
  }
}
```

**Important:** The credentials must be wrapped in a `"User"` object.

## cURL Example

```bash
curl -X POST http://localhost/api/login \
  -H "accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "User": {
      "login_id": "admin",
      "password": "admin123"
    }
  }'
```

## Response

The API returns:
1. **Response Body** - JSON with user information
2. **Response Header** - `bs-session-id` header containing the session ID

**Example Response Header:**
```
bs-session-id: 66ebbd6379d046a28d01f46ad6c9924a
```

**Example Response Body:**
```json
{
  "User": {
    "id": "1",
    "login_id": "admin",
    "name": "Administrator",
    "email": "admin@example.com"
  }
}
```

## Session ID Usage

**Critical:** The `bs-session-id` header value must be included in **ALL subsequent API requests**.

### Example: Creating a User (with session ID)

```bash
curl -X POST http://localhost/api/users \
  -H "accept: application/json" \
  -H "Content-Type: application/json" \
  -H "bs-session-id: 66ebbd6379d046a28d01f46ad6c9924a" \
  -d '{
    "User": {
      "user_id": "000010",
      "name": "John Doe",
      "email": "john@example.com"
    }
  }'
```

### Example: Enrolling a Face (with session ID)

```bash
curl -X POST http://localhost/api/users/123/face \
  -H "accept: application/json" \
  -H "Content-Type: application/json" \
  -H "bs-session-id: 66ebbd6379d046a28d01f46ad6c9924a" \
  -d '{
    "face_data": "<base64_encoded_image>",
    "face_level": "NORMAL"
  }'
```

---

## PHP Implementation

### 1. Login Function

```php
<?php

function biostarLogin($baseUrl, $loginId, $password) {
    $ch = curl_init($baseUrl . '/api/login');
    
    // Prepare request
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // BioStar 2 requires "User" wrapper
    $payload = json_encode([
        'User' => [
            'login_id' => $loginId,
            'password' => $password
        ]
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    // Capture response headers (for bs-session-id)
    $headers = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers) {
        $len = strlen($header);
        $parts = explode(':', $header, 2);
        if (count($parts) == 2) {
            $key = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            $headers[$key] = $value;
        }
        return $len;
    });
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200) {
        throw new Exception("Login failed: HTTP $httpCode");
    }
    
    // Extract session ID from headers
    if (!isset($headers['bs-session-id'])) {
        throw new Exception("No session ID returned");
    }
    
    $sessionId = $headers['bs-session-id'];
    $userData = json_decode($response, true);
    
    return [
        'session_id' => $sessionId,
        'user' => $userData['User'] ?? []
    ];
}
```

### 2. Using Session ID in Subsequent Requests

```php
<?php

function biostarCreateUser($baseUrl, $sessionId, $userData) {
    $ch = curl_init($baseUrl . '/api/users');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'bs-session-id: ' . $sessionId  // CRITICAL: Include session ID
    ]);
    
    $payload = json_encode([
        'User' => $userData
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        throw new Exception("Create user failed: HTTP $httpCode - $response");
    }
    
    return json_decode($response, true);
}

function biostarEnrollFace($baseUrl, $sessionId, $userId, $faceDataBase64) {
    $ch = curl_init($baseUrl . "/api/users/$userId/face");
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'bs-session-id: ' . $sessionId  // CRITICAL: Include session ID
    ]);
    
    $payload = json_encode([
        'face_data' => $faceDataBase64,
        'face_level' => 'NORMAL'
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        throw new Exception("Face enrollment failed: HTTP $httpCode - $response");
    }
    
    return json_decode($response, true);
}
```

### 3. Complete Example

```php
<?php

// Step 1: Login to BioStar 2
$baseUrl = 'http://localhost';
$auth = biostarLogin($baseUrl, 'admin', 'admin123');

echo "Logged in successfully!\n";
echo "Session ID: {$auth['session_id']}\n";
echo "User: {$auth['user']['name']}\n\n";

// Step 2: Create a new user (using session ID)
$newUser = [
    'user_id' => '000010',
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '1234567890'
];

$result = biostarCreateUser($baseUrl, $auth['session_id'], $newUser);
echo "User created with ID: {$result['User']['id']}\n\n";

// Step 3: Enroll face for the user (using session ID)
$faceImage = file_get_contents('john_doe.jpg');
$faceBase64 = base64_encode($faceImage);

$faceResult = biostarEnrollFace(
    $baseUrl, 
    $auth['session_id'], 
    $result['User']['id'], 
    $faceBase64
);

echo "Face enrolled successfully!\n";
```

---

## Key Points

1. **Login Request Format:**
   - Wrap credentials in `"User"` object
   - Use POST to `/api/login`

2. **Session ID Extraction:**
   - Returned in `bs-session-id` response **header** (not body)
   - Must be captured using `CURLOPT_HEADERFUNCTION`

3. **Session ID Usage:**
   - Include in **all subsequent requests** as header: `bs-session-id: <value>`
   - Without this header, authenticated endpoints will return 401/403

4. **Session Management:**
   - Store session ID for the duration of sync operations
   - Logout when done: `POST /api/logout` (with session ID header)

---

## BioStarAPI Class Usage

The provided `BioStarAPI` class handles all of this automatically:

```php
<?php

use BioStarSync\BioStar\BioStarAPI;

$config = [
    'base_url' => 'http://localhost/api',
    'login_id' => 'admin',
    'password' => 'admin123',
    'ssl_verify' => false
];

$api = new BioStarAPI($config, $logger);

// Login (captures session ID automatically)
$api->authenticate();

// Create user (session ID included automatically)
$user = $api->createUser([
    'member_id' => '000010',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
    'phone' => '1234567890'
]);

// Enroll face (session ID included automatically)
$api->enrollVisualFace($user['id'], $faceBase64);

// Logout (clears session)
$api->logout();
```

The class automatically:
- Wraps credentials in `"User"` object during login
- Captures `bs-session-id` from response headers
- Includes session ID in all subsequent request headers
- Handles errors and logging
