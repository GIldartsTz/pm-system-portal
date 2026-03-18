<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (file_exists('../db.php')) {
    include '../db.php';
} elseif (file_exists('db.php')) {
    include 'db.php';
} else {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Permission denied: เฉพาะ Admin เท่านั้น']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$delete_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;

if ($delete_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

if ($conn->query("DELETE FROM workflow_comment_history WHERE id = $delete_id")) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
exit;
