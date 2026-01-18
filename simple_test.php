<?php
$config = require __DIR__ . '/config.php';
$baseUrl = rtrim($config['biostar']['base_url'], '/');

// Step 1: Get data from HFSQL
try {
    $pdo = new PDO("odbc:Opera_Fitness_DSN", "admin", "");
    $stmt = $pdo->query("SELECT TOP 1 c.code, c.nom, c.prenom FROM Clients c
                         JOIN Photos p ON c.code = p.code
                         WHERE p.exporte = 0 AND c.code = 'WELF000440'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "No clients to export.\n";
        exit;
    }
    $row['full_name'] = trim($row['prenom'] . ' ' . $row['nom']);
    print_r($row);
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
    exit;
}

// Step 2: Login
$ch = curl_init($baseUrl . '/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'User' => [
            'login_id' => $config['biostar']['login_id'],
            'password' => $config['biostar']['password']
        ]
    ]),
    CURLOPT_HEADER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
curl_close($ch);
preg_match('/bs-session-id:\s*([a-f0-9]+)/i', $headers, $matches);
$sessionId = $matches[1] ?? null;
if (!$sessionId) {
    echo "ERROR: Failed to authenticate\n";
    exit;
}
echo "Authenticated. Session ID: $sessionId\n";

// Step 3: Create user
preg_match('/\d+/', $row['code'], $matches);
// Remove leading zeros - BioStar requires valid number format
$numericId = isset($matches[0]) ? ltrim($matches[0], '0') : (string)rand(1000,9999);
if ($numericId === '') $numericId = '1';  // Handle edge case if all zeros

$userPayload = [
    'User' => [
        'name' => $row['full_name'],
        'user_id' => $numericId,  // "440" not "000440"
        'user_group_id' => [
            'id' => 1,
            'name' => 'All Users'
        ],
        'disabled' => 'false',
        'start_datetime' => '2001-01-01T00:00:00.00Z',
        'expiry_datetime' => '2030-12-31T23:59:00.00Z'
    ]
];

echo "Sending payload:\n" . json_encode($userPayload, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($baseUrl . '/users');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'bs-session-id: ' . $sessionId
    ],
    CURLOPT_POSTFIELDS => json_encode($userPayload),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "User Creation Response ($httpCode): $response\n";
if ($httpCode != 200 && $httpCode != 201) {
    exit("✗ FAILED to create user\n");
}
echo "✓ User created\n";

// Step 4: Upload face image
$imagePath = "C:/tmp/{$row['code']}.jpeg";
if (!file_exists($imagePath)) {
    exit("✗ Image not found: $imagePath\n");
}
$imageData = base64_encode(file_get_contents($imagePath));

$facePayload = [
    'User' => [
        'user_id' => $numericId,  // Same ID without leading zeros
        'credentials' => [
            'faces' => [
                [
                    'raw_image' => $imageData,
                    'useProfile' => true,
                    'index' => 0
                ]
            ]
        ]
    ]
];

$ch = curl_init($baseUrl . '/faces');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'bs-session-id: ' . $sessionId
    ],
    CURLOPT_POSTFIELDS => json_encode($facePayload),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Face Upload Response ($httpCode): $response\n";

// Step 5: Mark as synced
$update = $pdo->prepare("UPDATE Photos SET exporte = 1 WHERE code = ?");
$update->execute([$row['code']]);
echo "✓ Sync complete for code: {$row['code']}\n";
