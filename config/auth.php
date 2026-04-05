<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browser caching for all protected pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

function check_auth($allowed_roles = [])
{
    // Determine the path prefix based on whether we are in the 'view' subdirectory
    $is_in_view = (strpos($_SERVER['PHP_SELF'], '/view/') !== false);
    $root_path = $is_in_view ? '../' : '';

    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $root_path . "login.php");
        exit;
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        // Not authorized for this page, redirect to index.php (the shared dashboard)
        header("Location: " . $root_path . "index.php");
        exit;
    }
}
?>
