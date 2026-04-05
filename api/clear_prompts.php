<?php
header("Content-Type: application/json");
include("../config/db.php");

// This API clears all entries from the prompts table
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($conn->query("TRUNCATE TABLE prompts")) {
        echo json_encode(["status" => "success", "message" => "Prompts cleared successfully"]);
    }
    else {
        echo json_encode(["status" => "error", "message" => "Failed to clear prompts: " . $conn->error]);
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}

$conn->close();
?>
