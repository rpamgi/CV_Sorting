<?php
header("Content-Type: application/json");
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prompt_text = $data['prompt_text'] ?? null;

    if (!$prompt_text) {
        echo json_encode(["status" => "error", "message" => "Prompt text is required"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO prompts (prompt_text) VALUES (?)");
    $stmt->bind_param("s", $prompt_text);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Prompt saved successfully", "id" => $stmt->insert_id]);
    }
    else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
    $stmt->close();
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Optional: Fetch the latest prompt
    $result = $conn->query("SELECT * FROM prompts ORDER BY created_at DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "data" => $row]);
    }
    else {
        echo json_encode(["status" => "success", "data" => null]);
    }
}

$conn->close();
?>
