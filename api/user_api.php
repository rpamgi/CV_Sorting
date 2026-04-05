<?php
header("Content-Type: application/json");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../config/db.php");

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'register':
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = trim($data['employee_id']);
        $password = $data['password'];

        if (empty($emp_id) || empty($password)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // Check if employee exists
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Invalid Employee ID. Please contact Admin."]);
            exit;
        }
        $stmt->close();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ? OR username = ?");
        $stmt->bind_param("ss", $emp_id, $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Registration already exists for this Employee ID."]);
            exit;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, status) VALUES (?, ?, ?, 'user', 'pending')");
        $stmt->bind_param("sss", $emp_id, $emp_id, $hashed_password);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Registration successful! Awaiting Admin approval."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Registration error: " . $conn->error]);
        }
        $stmt->close();
        break;

    case 'create':
        include("../config/auth.php");
        check_auth(['admin', 'sub-admin', 'super-admin']);
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = trim($data['username']); // The frontend uses 'username' field for EMP ID
        $password = $data['password'];
        $role = isset($data['role']) ? $data['role'] : 'user';

        if (empty($emp_id) || empty($password)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // Check if employee exists
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Invalid Employee ID. Not found in master list."]);
            exit;
        }
        $stmt->close();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ? OR username = ?");
        $stmt->bind_param("ss", $emp_id, $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "User already exists with this ID."]);
            exit;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Admin creates users as 'active' by default
        $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $emp_id, $emp_id, $hashed_password, $role);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User created successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
        break;

    case 'list':
        include("../config/auth.php");
        check_auth(['admin', 'sub-admin', 'super-admin']);
        $result = $conn->query("SELECT u.id, u.username, u.employee_id, u.role, u.status, u.created_at, e.full_name, e.designation, e.department 
                                FROM users u 
                                LEFT JOIN employees e ON u.employee_id = e.employee_id 
                                ORDER BY FIELD(u.status, 'pending', 'active', 'blocked'), u.created_at DESC");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $users]);
        break;

    case 'approve':
        include("../config/auth.php");
        check_auth(['admin', 'sub-admin', 'super-admin']);
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User approved successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Approval error."]);
        }
        $stmt->close();
        break;

    case 'update_status':
        include("../config/auth.php");
        check_auth(['admin', 'sub-admin', 'super-admin']);
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        $status = $data['status'];
        if ($id == $_SESSION['user_id']) {
            echo json_encode(["status" => "error", "message" => "Action not allowed on self."]);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Status updated."]);
        }
        $stmt->close();
        break;

    case 'delete':
        include("../config/auth.php");
        check_auth(['admin', 'sub-admin', 'super-admin']);
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        if ($id == $_SESSION['user_id']) {
            echo json_encode(["status" => "error", "message" => "Action not allowed on self."]);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User deleted."]);
        }
        $stmt->close();
        break;

    case 'get_profile':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["status" => "error", "message" => "Not logged in."]);
            exit;
        }
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT u.employee_id, u.username, u.role, u.profile_pic, e.* 
                                FROM users u 
                                LEFT JOIN employees e ON u.employee_id = e.employee_id 
                                WHERE u.id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        
        if (!$profile) {
            echo json_encode(["status" => "error", "message" => "User not found. Please log out and back in."]);
        } else {
            echo json_encode(["status" => "success", "data" => $profile]);
        }
        break;

    case 'update_profile_pic':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["status" => "error", "message" => "Not logged in."]);
            exit;
        }
        if (!isset($_FILES['profile_pic'])) {
            echo json_encode(["status" => "error", "message" => "No file uploaded."]);
            exit;
        }

        $target_dir = "../uploads/profile_pics/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        $target_file = $target_dir . $_SESSION['user_id'] . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $db_path = str_replace("../", "", $target_file);
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $db_path, $_SESSION['user_id']);
            $stmt->execute();
            echo json_encode(["status" => "success", "message" => "Profile picture updated!", "path" => $db_path]);
        } else {
            echo json_encode(["status" => "error", "message" => "Upload failed."]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}

$conn->close();
?>
