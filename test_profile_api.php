<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Mock user session if not set - using the newly created admin 097727
// Note: In a real scenario, the user IS logged in, so we check if sessions are persisting correctly.
if (!isset($_SESSION['user_id'])) {
    echo "SESSION NOT FOUND. Attempting to find user 097727...\n";
    include "config/db.php";
    $res = $conn->query("SELECT id FROM users WHERE employee_id = '097727'");
    if ($row = $res->fetch_assoc()) {
        $_SESSION['user_id'] = $row['id'];
        echo "Mocked session for user ID: " . $row['id'] . "\n";
    } else {
        die("User 097727 not found in DB.");
    }
}

echo "Calling API get_profile...\n";
$_GET['action'] = 'get_profile';
// Use output buffering to catch any stray output
ob_start();
include "api/user_api.php";
$output = ob_get_clean();

echo "API Output:\n";
echo "-----------\n";
echo $output;
echo "\n-----------\n";

$json = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Decode Error: " . json_last_error_msg() . "\n";
} else {
    echo "Decoded JSON successfully.\n";
    print_r($json);
}
?>
