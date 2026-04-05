<?php
header("Content-Type: application/json");
include("../config/db.php");

// Proactively recalibrate counts to handle manual deletions
$conn->query("UPDATE Job_List jl SET total_candidate = (SELECT COUNT(*) FROM candidates c WHERE c.jd_id = jl.jd_id)");

// Sort by the display_order in job_statuses table, then by created_at DESC
$query = "SELECT j.*, e.full_name as creator_name
          FROM Job_List j
          LEFT JOIN users u ON j.created_by = u.username
          LEFT JOIN employees e ON u.employee_id = e.employee_id
          LEFT JOIN job_statuses js ON LOWER(TRIM(j.status)) = LOWER(TRIM(js.status_name))
          ORDER BY COALESCE(js.display_order, 9999) ASC, j.created_at DESC";

$result = $conn->query($query);

$jobs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $jobs
]);

$conn->close();
?>
