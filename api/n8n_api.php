<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include("../config/db.php");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Fetch the webhook URL from config
$stmt = $conn->prepare("SELECT value FROM config WHERE name = 'n8n_webhook_url'");
$stmt->execute();
$stmt->bind_result($webhook_url);
if (!$stmt->fetch()) {
    echo json_encode(["status" => "error", "message" => "Webhook URL 'n8n_webhook_url' not found in config table."]);
    exit;
}
$stmt->close();

// Remove PHP script timeout limits
set_time_limit(0);
ini_set('max_execution_time', 0);

// Fetch any optional inputs (like job_title, jd_id) from query params OR JSON body
$data = json_decode(file_get_contents("php://input"), true) ?? [];
$job_title = $_GET['job_title'] ?? $data['job_title'] ?? '';
$jd_id = $_GET['jd_id'] ?? $data['jd_id'] ?? '';

// Dynamically append jd_id and job_title as query parameters to the webhook URL
$params = [];
if (!empty($jd_id)) $params[] = "JD_ID=" . urlencode($jd_id);
if (!empty($job_title)) $params[] = "job_title=" . urlencode($job_title);

if (!empty($params)) {
    $separator = (strpos($webhook_url, '?') === false) ? '?' : '&';
    $webhook_url .= $separator . implode('&', $params);
}

// Just call the webhook URL as requested (always POST because n8n requires it)
$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true); // Force POST

// Send the exact payload the n8n workflow expects
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "action" => "start_screening", 
    "job_title" => $job_title,
    "jd_id" => $jd_id
])); 

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Disable cURL timeouts to wait infinitely for n8n to finish processing
curl_setopt($ch, CURLOPT_TIMEOUT, 0); 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Decode n8n response to extract file count
$decoded_response = json_decode($response, true);
$file_count = 0;

if (is_array($decoded_response)) {
    // If it's an array, look into the first element for file_count
    if (!empty($decoded_response) && isset($decoded_response[0]['file_count'])) {
        $file_count = $decoded_response[0]['file_count'];
    } elseif (isset($decoded_response['file_count'])) {
        // If n8n returned a single object with file_count
        $file_count = $decoded_response['file_count'];
    }
}

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode([
        "status" => "success", 
        "message" => "Webhook triggered successfully", 
        "n8n_status_code" => $http_code,
        "webhook_url" => $webhook_url,
        "jd_id" => $jd_id,
        "job_title" => $job_title,
        "Filecount" => (int)$file_count,
        "n8n_response" => $decoded_response ?: $response
    ], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to trigger webhook", 
        "n8n_status_code" => $http_code,
        "webhook_url" => $webhook_url,
        "jd_id" => $jd_id,
        "job_title" => $job_title,
        "curl_error" => $error, 
        "n8n_response" => $decoded_response ?: $response
    ], JSON_UNESCAPED_SLASHES);
}

$conn->close();
?>
