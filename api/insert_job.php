<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['job_title']) || empty(trim($data['job_title']))) {
    echo json_encode(["status" => "error", "message" => "Job title is required."]);
    exit;
}

if (!isset($data['jd_id']) || empty(trim($data['jd_id']))) {
    echo json_encode(["status" => "error", "message" => "JD ID is required."]);
    exit;
}

$job_title = trim($data['job_title']);
$jd_id = trim($data['jd_id']);

// Check if jd_id already exists
$check_stmt = $conn->prepare("SELECT id FROM job_list WHERE jd_id = ?");
$check_stmt->bind_param("s", $jd_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "JD ID already exists."]);
    exit;
}

// Calculate next task_no
$task_res = $conn->query("SELECT task_no FROM Job_List ORDER BY id DESC LIMIT 1");
$next_task_no = "TASK1";
if ($task_res && $task_row = $task_res->fetch_assoc()) {
    $last_task_no = $task_row['task_no'];
    $last_num = (int)str_replace('TASK', '', $last_task_no);
    $next_task_no = "TASK" . ($last_num + 1);
}

$created_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';

// Insert new job
$stmt = $conn->prepare("INSERT INTO Job_List (job_title, jd_id, created_by, task_no) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $job_title, $jd_id, $created_by, $next_task_no);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Job title added successfully.", "id" => $stmt->insert_id, "task_no" => $next_task_no]);
}
else {
    echo json_encode(["status" => "error", "message" => "Failed to add job title: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>
