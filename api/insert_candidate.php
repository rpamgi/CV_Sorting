<?php
header("Content-Type: application/json");
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "No data provided"]);
    exit;
}

// Extract fields with defaults
$n8n_id = $data['n8n_id'] ?? null;
$job_title = $data['job_title'] ?? null;
$jd_id = $data['jd_id'] ?? null;

// Normalize / to - in job_title if it comes in that way
if ($job_title) {
    $job_title = str_replace(' / ', ' - ', $job_title);
}

// If we have jd_id, let's try to get the official title from Job_List to ensure consistency
if ($jd_id) {
    $job_stmt = $conn->prepare("SELECT job_title FROM Job_List WHERE jd_id = ?");
    $job_stmt->bind_param("s", $jd_id);
    $job_stmt->execute();
    $job_stmt->bind_result($official_title);
    if ($job_stmt->fetch()) {
        $job_title = $official_title;
    }
    $job_stmt->close();
}
$name = $data['name'] ?? null;
$organization = $data['organization'] ?? null;
$education = $data['education'] ?? null;
$educational_institute = $data['educational_institute'] ?? null;
$total_experience = $data['total_experience'] ?? 0;
$expected_salary = $data['expected_salary'] ?? 0;
$date_of_birth = $data['date_of_birth'] ?? null;
if ($date_of_birth) {
    // Attempt to parse flexible date formats like "15 Dec, 2000"
    $clean_date = str_replace(',', '', $date_of_birth);
    $timestamp = strtotime($clean_date);
    if ($timestamp) {
        $date_of_birth = date("Y-m-d", $timestamp);
    }
}
$location = $data['location'] ?? null;
$phone = $data['phone'] ?? null;
$email_id = $data['email_id'] ?? null;
$skills = $data['skills'] ?? null;
$strength = $data['strength'] ?? null;
$weakness = $data['weakness'] ?? null;
$rating = $data['rating'] ?? 0;
$reason_for_rating = $data['reason_for_rating'] ?? null;
$match = $data['match'] ?? null;
$shortlisted = isset($data['shortlisted']) ? ($data['shortlisted'] ? 1 : 0) : 0;
$confirmation = isset($data['confirmation']) ? ($data['confirmation'] ? 1 : 0) : 0;

if (!$n8n_id) {
    echo json_encode(["status" => "error", "message" => "n8n_id is required"]);
    exit;
}

// Prepare statement for security with ON DUPLICATE KEY UPDATE
$sql = "INSERT INTO candidates 
    (n8n_id, job_title, jd_id, name, organization, education, educational_institute, total_experience, expected_salary, date_of_birth, location, phone, email_id, skills, strength, weakness, rating, reason_for_rating, shortlisted, confirmation, `match`) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    job_title = VALUES(job_title),
    jd_id = VALUES(jd_id),
    name = VALUES(name),
    organization = VALUES(organization),
    education = VALUES(education),
    educational_institute = VALUES(educational_institute),
    total_experience = VALUES(total_experience),
    expected_salary = VALUES(expected_salary),
    date_of_birth = VALUES(date_of_birth),
    location = VALUES(location),
    phone = VALUES(phone),
    email_id = VALUES(email_id),
    skills = VALUES(skills),
    strength = VALUES(strength),
    weakness = VALUES(weakness),
    rating = VALUES(rating),
    reason_for_rating = VALUES(reason_for_rating),
    shortlisted = VALUES(shortlisted),
    confirmation = VALUES(confirmation),
    `match` = VALUES(`match`)";

$stmt = $conn->prepare($sql);

$stmt->bind_param("sssssssddsssssssdsiis",
    $n8n_id, $job_title, $jd_id, $name, $organization, $education, $educational_institute,
    $total_experience, $expected_salary, $date_of_birth, $location, $phone,
    $email_id, $skills, $strength, $weakness, $rating, $reason_for_rating,
    $shortlisted, $confirmation, $match
);

if ($stmt->execute()) {
    $inserted_id = $stmt->insert_id;
    // If it was an update, insert_id might be 0 unless we handle it, 
    // but usually with AI it returns the ID.
    // If it was an update, we might want to fetch the ID via n8n_id if needed.

    if ($inserted_id === 0) {
        $id_stmt = $conn->prepare("SELECT id FROM candidates WHERE n8n_id = ?");
        $id_stmt->bind_param("s", $n8n_id);
        $id_stmt->execute();
        $id_stmt->bind_result($existing_id);
        $id_stmt->fetch();
        $inserted_id = $existing_id;
        $id_stmt->close();
    }

    // Update total_candidate count and status in job_list
    if ($jd_id) {
        $update_job_sql = "UPDATE Job_List 
                          SET total_candidate = (SELECT COUNT(*) FROM candidates WHERE jd_id = ?),
                              status = CASE 
                                  WHEN (status = 'Pending' OR status = '' OR status IS NULL) THEN 'In Progress' 
                                  ELSE status 
                              END
                          WHERE jd_id = ?";
        $count_stmt = $conn->prepare($update_job_sql);
        $count_stmt->bind_param("ss", $jd_id, $jd_id);
        $count_stmt->execute();
        $count_stmt->close();
    }

    echo json_encode([
        "status" => "success",
        "id" => $inserted_id,
        "candidate_name" => $name,
        "job_title" => $job_title,
        "jd_id" => $jd_id,
        "action" => ($stmt->affected_rows == 2 ? "updated" : "inserted")
    ]);
}
else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
