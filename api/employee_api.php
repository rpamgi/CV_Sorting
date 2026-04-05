<?php
header("Content-Type: application/json");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../config/db.php");
include("../config/auth.php");
check_auth(['admin', 'sub-admin', 'super-admin']); // Only admins can manage employees

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        $result = $conn->query("SELECT * FROM employees ORDER BY employee_id ASC");
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $employees]);
        break;

    case 'create':
    case 'update':
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = trim($data['employee_id']);
        $full_name = trim($data['full_name']);
        $email = trim($data['email']);
        $mobile = trim($data['mobile_no']);
        $designation = trim($data['designation']);
        $department = trim($data['department']);
        $ip_no = trim($data['ip_no']);
        $floor = trim($data['floor']);

        if (empty($emp_id) || empty($full_name)) {
            echo json_encode(["status" => "error", "message" => "Employee ID and Full Name are required."]);
            exit;
        }

        if ($action === 'create') {
            // Check if already exists
            $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
            $stmt->bind_param("s", $emp_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(["status" => "error", "message" => "Employee ID already exists."]);
                exit;
            }
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO employees (employee_id, full_name, email, mobile_no, designation, department, ip_no, floor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $emp_id, $full_name, $email, $mobile, $designation, $department, $ip_no, $floor);
        } else {
            $stmt = $conn->prepare("UPDATE employees SET full_name = ?, email = ?, mobile_no = ?, designation = ?, department = ?, ip_no = ?, floor = ? WHERE employee_id = ?");
            $stmt->bind_param("ssssssss", $full_name, $email, $mobile, $designation, $department, $ip_no, $floor, $emp_id);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Employee saved successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
        break;

    case 'delete':
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = $data['employee_id'];

        // Check if user exists for this employee
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Cannot delete employee as a user account is linked to it."]);
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Employee deleted."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Delete failed."]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}

$conn->close();
?>
