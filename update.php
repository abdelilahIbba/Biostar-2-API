<?php
if (function_exists('session_start')) {
    session_start(); // Start the session
} else {
    die("Error: session support is not enabled in your PHP configuration.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Automatically perform login if session ID is not available
    if (!isset($_SESSION['bs_session_id'])) {
        $loginUrl = "https://localhost:5002/api/login";
        $loginData = [
            "User" => [
                "login_id" => "admin",
                "password" => "admin098"
            ]
        ];

        $loginCh = curl_init();
        curl_setopt($loginCh, CURLOPT_URL, $loginUrl);
        curl_setopt($loginCh, CURLOPT_POST, true);
        curl_setopt($loginCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($loginCh, CURLOPT_SSL_VERIFYHOST, 0); // For local development
        curl_setopt($loginCh, CURLOPT_SSL_VERIFYPEER, 0); // For local development
        curl_setopt($loginCh, CURLOPT_HEADER, true); // Enable header output
        curl_setopt($loginCh, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);
        curl_setopt($loginCh, CURLOPT_POSTFIELDS, json_encode($loginData));

        $loginResponse = curl_exec($loginCh);
        $headerSize = curl_getinfo($loginCh, CURLINFO_HEADER_SIZE);
        $loginHeader = substr($loginResponse, 0, $headerSize);
        curl_close($loginCh);

        if (preg_match('/bs-session-id:\s*(\S+)/i', $loginHeader, $matches)) {
            $_SESSION['bs_session_id'] = trim($matches[1]);
        } else {
            die("Automatic login failed!");
        }
    }

    $userID = $_POST['userID'] ?? '';
    $status = $_POST['status'] === 'true';
    $sessionID = $_SESSION['bs_session_id'];

    // API URL
    $url = "https://localhost:5002/api/users/" . $userID;

    // Request body
    $data = [
        "User" => [
            "disabled" => $status
        ]
    ];

    // Initialize cURL for update request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // For local development
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // For local development
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "bs-session-id: " . $sessionID
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check if the request was successful
    if ($httpCode === 200) {
        header("Location: index.html?response=" . urlencode($response));
        exit();
    } else {
        header("Location: index.html?response=" . urlencode("Error: " . $response));
        exit();
    }
} else {
    die("Error: Only POST method is allowed!");
}
?>