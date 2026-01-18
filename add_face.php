<?php
/**
 * BioStar 2 - Add Face to Existing User
 * 
 * This script demonstrates how to add a face image to an existing user
 * in BioStar 2 using the REST API.
 * 
 * Based on BioStar 2 API Documentation:
 * - Visual Face uses: credentials.visualFaces with template_ex_picture (base64)
 * - Regular Face uses: credentials.faces with raw_image (base64)
 * 
 * Method: PUT /api/users/{user_id} to update user with face credentials
 */

$config = require __DIR__ . '/config.php';
$baseUrl = rtrim($config['biostar']['base_url'], '/');

// Configuration
$USER_ID = '440';  // The user_id in BioStar 2 (without leading zeros)
$IMAGE_PATH = 'C:/tmp/WELF000440.jpeg';

echo "============================================================\n";
echo "BioStar 2 - Add Face to Existing User\n";
echo "============================================================\n\n";

// Step 1: Verify image exists
if (!file_exists($IMAGE_PATH)) {
    die("ERROR: Image not found at: $IMAGE_PATH\n");
}
$imageData = base64_encode(file_get_contents($IMAGE_PATH));
$imageSize = strlen($imageData);
echo "Image loaded: $IMAGE_PATH\n";
echo "Base64 size: $imageSize bytes\n\n";

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

// Step 3: First, get the user to verify it exists
echo "Step 2: Verifying user exists...\n";
$ch = curl_init($baseUrl . '/users/' . $USER_ID);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'bs-session-id: ' . $sessionId
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    die("ERROR: User $USER_ID not found. HTTP $httpCode: $response\n");
}
$userData = json_decode($response, true);
echo "User found: " . ($userData['User']['name'] ?? 'Unknown') . " (ID: $USER_ID)\n\n";

// Step 4: Add face using PUT /api/users/{user_id}
echo "Step 3: Adding face to user...\n";

// Method 1: Using visualFaces (for FaceStation F2, BioStation 3)
$facePayload = [
    'User' => [
        'credentials' => [
            'visualFaces' => [
                [
                    'template_ex_picture' => $imageData  // Base64 encoded image
                ]
            ]
        ]
    ]
];

echo "Sending PUT request to /users/$USER_ID\n";
echo "Payload structure: User.credentials.visualFaces[0].template_ex_picture\n\n";

$ch = curl_init($baseUrl . '/users/' . $USER_ID);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
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

echo "Response (HTTP $httpCode): $response\n\n";

if ($httpCode == 200) {
    echo "✓ SUCCESS: Face added to user $USER_ID\n";
} else {
    echo "Method 1 failed, trying Method 2...\n\n";
    
    // Method 2: Using faces with raw_image
    $facePayload2 = [
        'User' => [
            'credentials' => [
                'faces' => [
                    [
                        'raw_image' => $imageData,
                        'index' => 0
                    ]
                ]
            ]
        ]
    ];
    
    echo "Trying: User.credentials.faces[0].raw_image\n";
    
    $ch = curl_init($baseUrl . '/users/' . $USER_ID);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'bs-session-id: ' . $sessionId
        ],
        CURLOPT_POSTFIELDS => json_encode($facePayload2),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Response (HTTP $httpCode): $response\n\n";
    
    if ($httpCode == 200) {
        echo "✓ SUCCESS: Face added using Method 2\n";
    } else {
        echo "Method 2 failed, trying Method 3 (POST to /faces endpoint)...\n\n";
        
        // Method 3: POST to /users/{user_id}/credentials/faces
        $facePayload3 = [
            'FaceCredential' => [
                'raw_image' => $imageData
            ]
        ];
        
        $ch = curl_init($baseUrl . '/users/' . $USER_ID . '/credentials/faces');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'bs-session-id: ' . $sessionId
            ],
            CURLOPT_POSTFIELDS => json_encode($facePayload3),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Response (HTTP $httpCode): $response\n\n";
        
        if ($httpCode == 200 || $httpCode == 201) {
            echo "✓ SUCCESS: Face added using Method 3\n";
        } else {
            echo "✗ All methods failed. Check BioStar 2 logs for details.\n";
            echo "\nPossible issues:\n";
            echo "- Visual Face license not enabled\n";
            echo "- No face recognition device connected\n";
            echo "- Image format/size not supported\n";
            echo "- User ID format incorrect\n";
        }
    }
}

// Step 5: Verify face was added
echo "\nStep 4: Verifying face was added...\n";
$ch = curl_init($baseUrl . '/users/' . $USER_ID);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'bs-session-id: ' . $sessionId
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$response = curl_exec($ch);
curl_close($ch);

$userData = json_decode($response, true);
$faceCount = $userData['User']['face_count'] ?? 0;
echo "User face_count: $faceCount\n";

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
