<?php
// session_start(); // Start the session

// if ($_SERVER['REQUEST_METHOD'] == 'POST') {
//     $login_id = $_POST['login_id'] ?? '';
//     $password = $_POST['password'] ?? '';

//     if (empty($login_id) || empty($password)) {
//         die("Error: Missing login_id or password!");
//     }

//     // Create POST data
//     $postData = json_encode([
//         "User" => [
//             "login_id" => $login_id,
//             "password" => $password
//         ]
//     ]);

//     // Initialize cURL
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, "https://localhost:5002/api/login");
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // For local development
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // For local development
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         "Content-Type: application/json",
//         "accept: application/json"
//     ]);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
//     curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in the output

//     // Execute request
//     $response = curl_exec($ch);

//     if (curl_errno($ch)) {
//         die("cURL error: " . curl_error($ch));
//     }

//     // Get the HTTP status code
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//     // Separate headers and body
//     $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
//     $headers = substr($response, 0, $headerSize);
//     $body = substr($response, $headerSize);

//     curl_close($ch);

//     // Check if the request was successful
//     if ($httpCode === 200) {
//         // Extract the bs-session-id from the headers
//         preg_match('/bs-session-id: (.+)/i', $headers, $matches);
//         if (isset($matches[1])) {
//             $sessionID = trim($matches[1]);

//             // Store the session ID in a session variable
//             $_SESSION['bs_session_id'] = $sessionID;

//             // Redirect to index.php with a success message
//             header("Location: index.php?response=" . urlencode("Login successful! Session ID: " . $sessionID));
//             exit();
//         } else {
//             die("Error: bs-session-id not found in headers!");
//         }
//     } else {
//         // Redirect to index.php with an error message
//         header("Location: index.php?response=" . urlencode("Login failed! Response: " . $body));
//         exit();
//     }
// } else {
//     die("Error: Only POST method is allowed!");
// }

?>
