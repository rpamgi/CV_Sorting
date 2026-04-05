<?php
/**
 * update_task_status.php
 * 
 * Updates a job's status using its jd_id.
 * Accepts POST data (JSON or Form-data).
 * 
 * Parameters:
 *   jd_id (string): The unique JD ID of the job.
 *   status (string|int): The new status name (e.g., 'downloading') 
 *                        OR the display_order number (e.g., 2).
 */

header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";

// 1. Get Inputs (Checks JSON, POST, and GET)
$input = json_decode(file_get_contents('php://input'), true);
$jd_id = trim($input['jd_id'] ?? $_POST['jd_id'] ?? $_GET['jd_id'] ?? '');
$status_input = trim($input['status'] ?? $_POST['status'] ?? $_GET['status'] ?? '');

if (!$jd_id || $status_input === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing jd_id or status.']);
    exit;
}

// 2. Resolve Status Name if input is numeric
$final_status = $status_input;
if (is_numeric($status_input)) {
    $stmt = $conn->prepare("SELECT status_name FROM job_statuses WHERE display_order = ?");
    $display_order = (int)$status_input;
    $stmt->bind_param("i", $display_order);
    $stmt->execute();
    $stmt->bind_result($status_name);
    if ($stmt->fetch()) {
        $final_status = $status_name;
    } else {
        echo json_encode(['status' => 'error', 'message' => "Status with order '$status_input' not found."]);
        $stmt->close();
        exit;
    }
    $stmt->close();
} else {
    // Validate that the status name exists in our allowed list
    $stmt = $conn->prepare("SELECT id FROM job_statuses WHERE LOWER(status_name) = LOWER(?)");
    $stmt->bind_param("s", $status_input);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => "Status name '$status_input' is not valid."]);
        $stmt->close();
        exit;
    }
    $stmt->close();
}

// 3. Update the Job
$update = $conn->prepare("UPDATE Job_List SET status = ? WHERE jd_id = ?");
$update->bind_param("ss", $final_status, $jd_id);

if ($update->execute()) {
    if ($update->affected_rows > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => "Job $jd_id updated to '$final_status'.",
            'updated_jd_id' => $jd_id,
            'new_status' => $final_status
        ]);
    } else {
        // Check if JD_ID actually exists
        $check = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $check->bind_param("s", $jd_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => "Job with JD ID '$jd_id' not found."]);
        } else {
            echo json_encode(['status' => 'success', 'message' => "Job $jd_id is already set to '$final_status'. No changes made."]);
        }
        $check->close();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $conn->error]);
}

$update->close();
$conn->close();
?>
