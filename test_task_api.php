<?php
include __DIR__ . '/config/db.php';

echo "=== Test: All Jobs (status=0) ===\n";
$url = "http://localhost/CV_Sorting/api/get_job_task_data.php?status=0";
$r = json_decode(file_get_contents($url), true);
if ($r && $r['status'] === 'success') {
    foreach ($r['data'] as $job) {
        echo $job['task_no'] . " | " . $job['status'] . " | " . $job['job_title'] . "\n";
    }
} else {
    echo "Error: " . json_encode($r) . "\n";
}

echo "\n=== Test: Pending Only (status=1) ===\n";
$url2 = "http://localhost/CV_Sorting/api/get_job_task_data.php?status=1";
$r2 = json_decode(file_get_contents($url2), true);
if ($r2 && $r2['status'] === 'success') {
    foreach ($r2['data'] as $job) {
        echo $job['task_no'] . " | " . $job['status'] . " | " . $job['job_title'] . "\n";
    }
}

echo "\n=== Test: Pending+Downloading (status=1,2) ===\n";
$url3 = "http://localhost/CV_Sorting/api/get_job_task_data.php?status=1,2";
$r3 = json_decode(file_get_contents($url3), true);
if ($r3 && $r3['status'] === 'success') {
    foreach ($r3['data'] as $job) {
        echo $job['task_no'] . " | " . $job['status'] . " | " . $job['job_title'] . "\n";
    }
}
?>
