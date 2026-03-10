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
    
    $type = $data['type']; 
    $table = mysqli_real_escape_string($conn, $data['table']);
    $is_custom = isset($data['is_custom']) ? intval($data['is_custom']) : 0;
    
    // ✅ 1. ดึง ID ของคนที่กำลัง Login
    $user_id = $_SESSION['user_id'];
    $user_name = "User " . $user_id; // ตั้งค่าเริ่มต้นเผื่อหาไม่เจอ
    
    // ✅ 2. วิ่งไปค้นหาชื่อจริงๆ จากตาราง users
    $u_query = $conn->query("SELECT * FROM users WHERE id = '$user_id'");
    if ($u_query && $u_query->num_rows > 0) {
        $u_data = $u_query->fetch_assoc();
        
        // 🚨 ตรงนี้สำคัญ: ถ้าตวงเก็บชื่อในคอลัมน์อื่นที่ไม่ใช่ 'username' หรือ 'name' ให้แก้ชื่อคอลัมน์ตรงนี้นะครับ
        $user_name = $u_data['username'] ?? $u_data['name'] ?? $u_data['full_name'] ?? $u_data['firstname'] ?? "User " . $user_id;
    }

    $sql = ""; 

    // ✅ 3. บันทึกข้อมูลพร้อมชื่อที่หามาได้
    if ($is_custom === 1) {
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
    } 
    else {
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

    if (empty($sql)) {
        echo json_encode(['success' => false, 'error' => 'ไม่รู้จักคำสั่ง Action นี้: ' . $type]);
        exit;
    }

    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}