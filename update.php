<?php
session_start(); // Start the session

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userID = $_POST['userID'] ?? '';
    $status = $_POST['status'] === 'true';

    // Check if the session ID is available
    if (!isset($_SESSION['bs_session_id'])) {
        die("Error: Please log in first!");
    }

    $sessionID = $_SESSION['bs_session_id'];

    // API URL
    $url = "https://localhost:5002/api/users/" . $userID;

    // Request body
    $data = [
        "User" => [
            "disabled" => $status
        ]
    ];

    // Initialize cURL
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