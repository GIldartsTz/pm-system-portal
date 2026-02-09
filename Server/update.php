<?php
header('Content-Type: application/json');
include '../db.php';

$in = json_decode(file_get_contents('php://input'), true);

if (!isset($in['table'], $in['name'], $in['column'], $in['month'], $in['year']) || !array_key_exists('value', $in)) {
    exit(json_encode(['success'=>false, 'msg'=>'Invalid input']));
}

$tbl = $in['table'];
$name = $in['name'];
$m = (int)$in['month'];
$y = (int)$in['year'];
$col = $in['column'];
$val = $in['value'];

// Whitelist Tables
if (!in_array($tbl, ['server_logs', 'network_logs', 'hardsoft_logs', 'backup_logs'])) exit(json_encode(['success'=>false]));

// Update Database
$stmt = $conn->prepare("UPDATE $tbl SET $col = ? WHERE equipment_name = ? AND month = ? AND year = ?");
if ($val === null) {
    $null = null;
    $stmt->bind_param("ssii", $null, $name, $m, $y);
} else {
    $v = (int)$val;
    $stmt->bind_param("isii", $v, $name, $m, $y);
}
$success = $stmt->execute();
$stmt->close();

if ($success) {
    // Fetch latest update time for this specific row/context
    // Note: Database must have 'last_updated' column (DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP)
    $q = $conn->query("SELECT MAX(last_updated) as latest FROM $tbl WHERE month = $m AND year = $y");
    $r = $q->fetch_assoc();
    $time = $r['latest'] ? date('d M Y, H:i', strtotime($r['latest'])) : 'Just now';
    
    echo json_encode(['success' => true, 'time' => $time]);
} else {
    echo json_encode(['success' => false]);
}

$conn->close();
?>