<?php
/**
 * Add User 440 with Visual Face to BioStar 2
 */

$config = require __DIR__ . '/config.php';
$baseUrl = rtrim($config['biostar']['base_url'], '/');

// Configuration
$USER_ID = '440';
$USER_NAME = 'AZIZ BENBOUHAGGA';
$IMAGE_PATH = 'C:/tmp/WELF000440.jpeg';

echo "============================================================\n";
echo "Add User with Visual Face to BioStar 2\n";
echo "============================================================\n\n";

// Step 1: Load image
if (!file_exists($IMAGE_PATH)) {
    die("ERROR: Image not found at: $IMAGE_PATH\n");
}
$imageData = base64_encode(file_get_contents($IMAGE_PATH));
echo "Image loaded: $IMAGE_PATH\n\n";

// Step 2: Login
echo "Step 1: Authenticating...\n";
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
    die("ERROR: Failed to authenticate\n");
}
echo "Authenticated. Session ID: $sessionId\n\n";

// Step 3: Create user with visual face in one request
echo "Step 2: Creating user with visual face...\n";

$userPayload = [
    'User' => [
        'name' => $USER_NAME,
        'user_id' => $USER_ID,
        'user_group_id' => [
            'id' => 1,
            'name' => 'All Users'
        ],
        'disabled' => 'false',
        'start_datetime' => '2001-01-01T00:00:00.00Z',
        'expiry_datetime' => '2030-12-31T23:59:00.00Z',
        'credentials' => [
            'visualFaces' => [
                [
                    'template_ex_picture' => $imageData
                ]
            ]
        ]
    ]
];

echo "Payload: Creating user $USER_ID with visual face\n\n";

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

echo "Response (HTTP $httpCode): $response\n\n";

if ($httpCode == 200 || $httpCode == 201) {
    echo "✓ SUCCESS: User created with visual face!\n";
} else {
    // Check if user already exists (code 202)
    $resp = json_decode($response, true);
    if (isset($resp['Response']['code']) && $resp['Response']['code'] == '202') {
        echo "User already exists. Updating with visual face...\n\n";
        
        // Update existing user with face
        $updatePayload = [
            'User' => [
                'credentials' => [
                    'visualFaces' => [
                        [
                            'template_ex_picture' => $imageData
                        ]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init($baseUrl . '/users/' . $USER_ID);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'bs-session-id: ' . $sessionId
            ],
            CURLOPT_POSTFIELDS => json_encode($updatePayload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Update Response (HTTP $httpCode): $response\n\n";
        
        if ($httpCode == 200) {
            echo "✓ SUCCESS: Visual face added to existing user!\n";
        } else {
            echo "✗ FAILED to add visual face\n";
        }
    } else {
        echo "✗ FAILED to create user\n";
    }
}

// Logout
echo "\nLogging out...\n";
$ch = curl_init($baseUrl . '/logout');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['bs-session-id: ' . $sessionId],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
curl_exec($ch);
curl_close($ch);

echo "Done.\n";
