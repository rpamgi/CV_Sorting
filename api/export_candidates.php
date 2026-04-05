<?php
include("../config/db.php");

$search = isset($_GET['search']) ? $_GET['search'] : '';
$shortlisted_filter = isset($_GET['shortlisted']) ? $_GET['shortlisted'] : '';
$confirmation_filter = isset($_GET['confirmation']) ? $_GET['confirmation'] : '';
$jd_id = isset($_GET['jd_id']) ? $_GET['jd_id'] : '';
$job_title = isset($_GET['job_title']) ? $_GET['job_title'] : '';

$where_clauses = [];
$params = [];
$types = "";

if ($jd_id !== '') {
    $where_clauses[] = "jd_id = ?";
    $params[] = $jd_id;
    $types .= "s";
} elseif ($job_title !== '') {
    $where_clauses[] = "job_title = ?";
    $params[] = $job_title;
    $types .= "s";
}

if ($search !== '') {
    $where_clauses[] = "(name LIKE ? OR email_id LIKE ? OR skills LIKE ? OR organization LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($shortlisted_filter !== '') {
    $where_clauses[] = "shortlisted = ?";
    $params[] = (int)$shortlisted_filter;
    $types .= "i";
}

if ($confirmation_filter !== '') {
    $where_clauses[] = "confirmation = ?";
    $params[] = (int)$confirmation_filter;
    $types .= "i";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

$query = "SELECT * FROM candidates" . $where_sql . " ORDER BY created_at DESC";
$stmt = $conn->prepare($query);

if ($types) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$filename = "candidates_export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Set CSV headers
fputcsv($output, [
    'ID', 'N8N ID', 'Name', 'Organization', 'Education', 'Institute',
    'Experience', 'Expected Salary', 'DOB', 'Location', 'Phone',
    'Email', 'Skills', 'Strength', 'Weakness', 'Rating', 'Match', 'Reason', 'Shortlisted', 'Confirmation', 'Created At'
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'], $row['n8n_id'], $row['name'], $row['organization'], $row['education'],
        $row['educational_institute'], $row['total_experience'], $row['expected_salary'],
        $row['date_of_birth'], $row['location'], $row['phone'], $row['email_id'],
        $row['skills'], $row['strength'], $row['weakness'], $row['rating'], $row['match'],
        $row['reason_for_rating'], $row['shortlisted'] ? 'Yes' : 'No',
        $row['confirmation'] ? 'Yes' : 'No', $row['created_at']
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
?>
