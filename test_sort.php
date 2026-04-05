<?php
include __DIR__ . '/config/db.php';
$q = $conn->query("SELECT j.id, j.status, COALESCE(js.display_order, 9999) as ord FROM Job_List j LEFT JOIN job_statuses js ON LOWER(TRIM(j.status)) = LOWER(TRIM(js.status_name)) ORDER BY ord ASC, j.created_at DESC");
while ($r = $q->fetch_assoc()) {
    echo $r['id'] . ' | ' . $r['status'] . ' | order:' . $r['ord'] . PHP_EOL;
}
?>
