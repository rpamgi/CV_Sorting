<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");
// check_auth(); // Enable this if you want to restrict to logged in users

$action = $_GET['action'] ?? 'list';
$data = json_decode(file_get_contents("php://input"), true);

switch ($action) {
    case 'all':
        $query = "SELECT * FROM rpa_config ORDER BY category, `key` ASC";
        $result = $conn->query($query);
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $configs]);
        break;

    case 'list':
        $query = "SELECT `key`, `value` FROM rpa_config";
        $result = $conn->query($query);
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[$row['key']] = $row['value'];
        }
        echo json_encode($configs);
        break;

    case 'create':
    case 'update':
        $key = $data['key'] ?? '';
        $value = $data['value'] ?? '';
        $category = $data['category'] ?? '';
        $project = $data['project'] ?? '';
        $id = $data['id'] ?? null;

        if (!$key || !$project) {
            echo json_encode(["status" => "error", "message" => "Key and Project are required"]);
            exit;
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE rpa_config SET `key`=?, `value`=?, category=?, project=? WHERE id=?");
            $stmt->bind_param("ssssi", $key, $value, $category, $project, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO rpa_config (`key`, `value`, category, project) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), category=VALUES(category)");
            $stmt->bind_param("ssss", $key, $value, $category, $project);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Configuration saved"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    case 'update_by_key':
        $key = $_GET['key'] ?? ($data['key'] ?? '');
        $value = $_GET['value'] ?? ($data['value'] ?? '');

        if (!$key) {
            echo json_encode(["status" => "error", "message" => "Key is required"]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE rpa_config SET `value`=? WHERE `key`=?");
        $stmt->bind_param("ss", $value, $key);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Configuration updated"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    case 'update_multiple':
        $payload = $data;
        if (empty($payload) || !is_array($payload)) {
            // If JSON body is empty, try to get from query parameters
            $payload = $_GET;
            unset($payload['action']); // Don't try to update a key named 'action'
        }

        if (empty($payload)) {
            echo json_encode(["status" => "error", "message" => "Valid JSON payload or query parameters of key-value pairs is required"]);
            exit;
        }

        $success_count = 0;
        $errors = [];

        $stmt = $conn->prepare("UPDATE rpa_config SET `value`=? WHERE `key`=?");

        foreach ($payload as $k => $v) {
            // Skip if the key is 'action' (in case it was sent in the JSON body)
            if ($k === 'action') continue;

            // Make sure value is a string or number
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v);
            } else {
                $v = (string)$v;
            }

            $stmt->bind_param("ss", $v, $k);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
                    $success_count++;
                }
            } else {
                $errors[$k] = $stmt->error;
            }
        }
        $stmt->close();

        if (empty($errors)) {
            echo json_encode(["status" => "success", "message" => "Successfully updated $success_count configuration(s)"]);
        } else {
            echo json_encode(["status" => "partial_success", "message" => "Updated $success_count configuration(s) with some errors.", "errors" => $errors]);
        }
        break;

    case 'delete':
        $id = $data['id'] ?? null;
        if (!$id) {
            echo json_encode(["status" => "error", "message" => "ID required"]);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM rpa_config WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Configuration deleted"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$conn->close();
?>
