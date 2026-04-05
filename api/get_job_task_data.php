<?php
/**
 * get_job_task_data.php
 * 
 * Returns Job List with computed TASK numbers (TASK1, TASK2, ...) 
 * ordered by job ID ascending (lowest ID = TASK1).
 * 
 * Parameters:
 *   status (GET): 
 *     - 0 or omitted  = all statuses
 *     - 1             = status with display_order 1 (e.g. Pending)
 *     - 2             = status with display_order 2 (e.g. downloading)
 *     - 1,2,3         = multiple statuses by their display_order number
 * 
 * Example Usage:
 *   /api/get_job_task_data.php              -> all jobs
 *   /api/get_job_task_data.php?status=0     -> all jobs
 *   /api/get_job_task_data.php?status=1     -> Pending only
 *   /api/get_job_task_data.php?status=1,2   -> Pending + downloading
 */

header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";

// 1. Load statuses ordered by display_order to map number -> status_name
$statusMap = []; // e.g. 1 => 'Pending', 2 => 'downloading', etc.
$sr = $conn->query("SELECT display_order, status_name FROM job_statuses ORDER BY display_order ASC");
while ($row = $sr->fetch_assoc()) {
    $statusMap[(int)$row['display_order']] = $row['status_name'];
}

// 2. Parse parameters
$statusParam = trim($_GET['status'] ?? '0');
$taskNoParam = trim($_GET['task_no'] ?? '');

$filterNames = []; // list of status_name values to filter by
if ($statusParam !== '0' && $statusParam !== '') {
    $requestedOrders = array_map('trim', explode(',', $statusParam));
    foreach ($requestedOrders as $ord) {
        $ord = (int)$ord;
        if (isset($statusMap[$ord])) {
            $filterNames[] = $statusMap[$ord];
        }
    }
}

$filterTaskNos = []; // list of task_no values to filter by
if ($taskNoParam !== '' && $taskNoParam !== '0') {
    $filterTaskNos = array_map('trim', explode(',', strtoupper($taskNoParam)));
}

// 3. Build the query
$sql = "SELECT id, task_no, job_title, jd_id, status, created_by, created_at
        FROM Job_List
        ORDER BY id ASC";

$result = $conn->query($sql);
$allJobs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allJobs[] = $row;
    }
}

// 4. (Removed manual TASK numbering - it is now stored in DB)

// 5. Apply filters
if (!empty($filterNames) || !empty($filterTaskNos)) {
    $filterNamesLower = array_map('strtolower', $filterNames);
    
    $allJobs = array_filter($allJobs, function($job) use ($filterNamesLower, $filterTaskNos) {
        $statusMatch = true;
        if (!empty($filterNamesLower)) {
            $statusMatch = in_array(strtolower(trim($job['status'])), $filterNamesLower);
        }
        
        $taskMatch = true;
        if (!empty($filterTaskNos)) {
            $taskMatch = in_array(strtoupper(trim($job['task_no'])), $filterTaskNos);
        }
        
        return $statusMatch && $taskMatch;
    });
    $allJobs = array_values($allJobs); // re-index
}

// 6. Return result
$meta = [
    'total' => count($allJobs),
    'status_filter' => $statusParam === '0' || $statusParam === '' ? 'all' : $statusParam,
    'available_statuses' => $statusMap,
];

echo json_encode([
    'status'  => 'success',
    'meta'    => $meta,
    'data'    => $allJobs,
]);

$conn->close();
?>
