<?php
header('Content-Type: application/json');
error_reporting(0); // ปิดการโชว์ Error ดิบๆ ไม่ให้ JSON พัง
include '../db.php';

$in = json_decode(file_get_contents('php://input'), true);

if (!$in) exit(json_encode(['success'=>false, 'msg'=>'รูปแบบ JSON ไม่ถูกต้อง']));
if (!isset($in['table'], $in['name'], $in['column'], $in['month'], $in['year']) || !array_key_exists('value', $in)) {
    exit(json_encode(['success'=>false, 'msg'=>'ส่งข้อมูลมาไม่ครบ']));
}

$tbl = $in['table'];
$name = $in['name'];
$m = (int)$in['month'];
$y = (int)$in['year'];
$col = $in['column'];
$val = $in['value'];

if (!in_array($tbl, ['server_logs', 'network_logs', 'hardware_logs', 'software_logs', 'backup_logs'])) exit(json_encode(['success'=>false, 'msg'=>'ไม่อนุญาตให้อัปเดตตารางนี้']));
if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) exit(json_encode(['success'=>false, 'msg'=>'ชื่อคอลัมน์ผิดปกติ: ' . $col]));

// 🚨 จุดสำคัญ: เช็คก่อนว่าคอลัมน์มีอยู่จริงไหม
$chk_col = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
if ($chk_col->num_rows == 0) {
    exit(json_encode(['success'=>false, 'msg'=>"ไม่พบคอลัมน์ '$col' ในฐานข้อมูล! (โปรดเช็ค HeidiSQL)"]));
}

$check = $conn->prepare("SELECT id FROM $tbl WHERE equipment_name = ? AND month = ? AND year = ?");
$check->bind_param("sii", $name, $m, $y);
$check->execute();
$check->store_result();
if ($check->num_rows == 0) {
    $ins = $conn->prepare("INSERT INTO $tbl (equipment_name, month, year) VALUES (?, ?, ?)");
    $ins->bind_param("sii", $name, $m, $y);
    $ins->execute();
    $ins->close();
}
$check->close();

$stmt = $conn->prepare("UPDATE $tbl SET $col = ? WHERE equipment_name = ? AND month = ? AND year = ?");
if (!$stmt) exit(json_encode(['success'=>false, 'msg'=>"SQL Prepare Error: " . $conn->error]));

if ($val === null || $val === "") {
    $null = null;
    $stmt->bind_param("ssii", $null, $name, $m, $y);
} else {
    $v = (int)$val;
    $stmt->bind_param("isii", $v, $name, $m, $y);
}

if ($stmt->execute()) {
    $q = $conn->query("SELECT MAX(last_updated) as latest FROM $tbl WHERE month = $m AND year = $y");
    $time = ($q && $r = $q->fetch_assoc()) && $r['latest'] ? date('d M Y, H:i', strtotime($r['latest'])) : 'Just now';
    echo json_encode(['success' => true, 'time' => $time]);
} else {
    echo json_encode(['success' => false, 'msg' => 'SQL Execute Error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>