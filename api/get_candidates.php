<?php
header("Content-Type: application/json");
include("../config/db.php");

$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$shortlisted_filter = isset($_GET['shortlisted']) ? $_GET['shortlisted'] : '';
$confirmation_filter = isset($_GET['confirmation']) ? $_GET['confirmation'] : '';
$job_title_filter = isset($_GET['job_title']) ? $_GET['job_title'] : '';
$jd_id_filter = isset($_GET['jd_id']) ? $_GET['jd_id'] : '';

$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Validate sort field and order to prevent SQL injection
$allowed_sort_fields = ['id', 'total_experience', 'expected_salary', 'rating', 'match', 'created_at'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}
if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'ASC';
}

$where_clauses = [];
$params = [];
$types = "";

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

if ($job_title_filter !== '') {
    $where_clauses[] = "job_title = ?";
    $params[] = $job_title_filter;
    $types .= "s";
}

if ($jd_id_filter !== '') {
    $where_clauses[] = "jd_id = ?";
    $params[] = $jd_id_filter;
    $types .= "s";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM candidates" . $where_sql;
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get paginated data
$query = "SELECT * FROM candidates" . $where_sql . " ORDER BY $sort_by $sort_order LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

$final_params = array_merge($params, [$limit, $offset]);
$final_types = $types . "ii";

$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$result = $stmt->get_result();

$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $candidates,
    "pagination" => [
        "current_page" => $page,
        "total_pages" => $total_pages,
        "total_records" => $total_rows,
        "limit" => $limit
    ]
]);

$stmt->close();
$conn->close();
?>
