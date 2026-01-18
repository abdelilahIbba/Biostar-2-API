<?php

echo "Testing HFSQL Connection Methods...\n\n";

// Method 1: Using configured DSN
echo "Method 1: Using DSN 'Opera_Fitness_DSN'\n";
try {
    $pdo = new PDO('odbc:Opera_Fitness_DSN', 'admin', '');
    echo "✓ SUCCESS using DSN\n\n";
    $pdo = null;
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

// Method 2: Direct ODBC connection string
echo "Method 2: Direct ODBC connection string\n";
try {
    $dsn = "odbc:Driver={HFSQL};Server=127.0.0.1;Port=4900;Database=GOLDENGYM";
    $pdo = new PDO($dsn, 'admin', '');
    echo "✓ SUCCESS using direct connection\n\n";
    $pdo = null;
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n\n";
}

// Method 3: Check if ODBC drivers are available
echo "Method 3: Checking available PHP extensions\n";
$extensions = get_loaded_extensions();
if (in_array('PDO', $extensions)) {
    echo "✓ PDO is loaded\n";
} else {
    echo "✗ PDO is NOT loaded\n";
}

if (in_array('pdo_odbc', $extensions)) {
    echo "✓ pdo_odbc is loaded\n";
} else {
    echo "✗ pdo_odbc is NOT loaded\n";
}

if (in_array('odbc', $extensions)) {
    echo "✓ odbc is loaded\n";
} else {
    echo "✗ odbc is NOT loaded\n";
}

echo "\n";

// Method 4: List available ODBC drivers
echo "Method 4: Available ODBC drivers\n";
try {
    $drivers = PDO::getAvailableDrivers();
    echo "Available PDO drivers: " . implode(', ', $drivers) . "\n";
    if (in_array('odbc', $drivers)) {
        echo "✓ ODBC driver is available\n";
    } else {
        echo "✗ ODBC driver is NOT available\n";
    }
} catch (Exception $e) {
    echo "✗ Cannot list drivers: " . $e->getMessage() . "\n";
}