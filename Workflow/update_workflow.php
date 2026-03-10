<?php
session_start();

// ระบบ Auto-Detect Path ป้องกันหา db.php ไม่เจอ
if (file_exists('../db.php')) { 
    include '../db.php'; 
} elseif (file_exists('db.php')) { 
    include 'db.php'; 
} else { 
    die(json_encode(['success' => false, 'error' => 'Database connection failed.'])); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $type = $data['type']; 
    $table = mysqli_real_escape_string($conn, $data['table']);
    $is_custom = isset($data['is_custom']) ? (int)$data['is_custom'] : 0;
    
    // ดึงชื่อเต็มของคนที่ Login อยู่
    $user_id = $_SESSION['user_id'];
    $u_query = $conn->query("SELECT fullname FROM users WHERE id = '$user_id'");
    $u_data = $u_query->fetch_assoc();
    $user_name = $u_data['fullname'] ?? "User " . $user_id;

    $sql = ""; 

    if ($is_custom === 1) {
        // สำหรับ Section OTHER (Custom Pages)
        $page_id = intval($data['page_id']);
        if ($type === 'submit') {
            $sql = "UPDATE $table SET sub_by='$user_name', sub_at=NOW() WHERE id=$page_id";
        } elseif ($type === 'approve') {
            $sql = "UPDATE $table SET app_by='$user_name', app_at=NOW() WHERE id=$page_id";
        } elseif ($type === 'cancel_submit') {
            $sql = "UPDATE $table SET sub_by=NULL, sub_at=NULL WHERE id=$page_id";
        } elseif ($type === 'cancel_approve') {
            $sql = "UPDATE $table SET app_by=NULL, app_at=NULL WHERE id=$page_id";
        }
    } else {
        // สำหรับ Section ICT (รายเดือน)
        $month = intval($data['month']);
        $year = intval($data['year']);
        if ($type === 'submit') {
            $sql = "UPDATE $table SET sub_by='$user_name', sub_at=NOW() WHERE month=$month AND year=$year";
        } elseif ($type === 'approve') {
            $sql = "UPDATE $table SET app_by='$user_name', app_at=NOW() WHERE month=$month AND year=$year";
        } elseif ($type === 'cancel_submit') {
            $sql = "UPDATE $table SET sub_by=NULL, sub_at=NULL WHERE month=$month AND year=$year";
        } elseif ($type === 'cancel_approve') {
            $sql = "UPDATE $table SET app_by=NULL, app_at=NULL WHERE month=$month AND year=$year";
        }
    }

    if ($sql != "" && $conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}