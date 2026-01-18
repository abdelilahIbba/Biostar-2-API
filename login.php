<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loginUrl = "https://localhost:5002/api/login";
    $loginData = [
        "User" => [
            "login_id" => $_POST['username'],
            "password" => $_POST['password']
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    curl_close($ch);

    if (preg_match('/bs-session-id:\s*(\S+)/i', $header, $matches)) {
        $_SESSION['bs_session_id'] = trim($matches[1]);
        $redirect = $_GET['redirect'] ?? 'index.html';
        header("Location: " . $redirect);
        exit();
    } else {
        $error = "Login failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Biostar 2 Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .login-form { max-width: 300px; margin: 0 auto; }
        input { width: 100%; padding: 8px; margin: 8px 0; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; }
        .error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-form">
        <h2>Biostar 2 Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>