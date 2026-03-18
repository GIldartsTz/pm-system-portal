<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'error' => 'Permission Denied: เฉพาะ Admin เท่านั้นที่สามารถทำรายการได้']));
}
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
        // ✅ ใช้ custom_pages_workflow table เพื่อเก็บ workflow data แยกต่อ month/year
        $page_id = intval($data['page_id']);
        $month = intval($data['month'] ?? date('m'));
        $year = intval($data['year'] ?? date('Y'));
        
        // ✅ ตรวจสอบว่า table มีอยู่ไหม ถ้าไม่มีให้สร้าง
        $table_check = $conn->query("SHOW TABLES LIKE 'custom_pages_workflow'");
        if(!$table_check || $table_check->num_rows == 0) {
            // สร้าง table ใหม่
            $conn->query("
                CREATE TABLE custom_pages_workflow (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    page_id INT NOT NULL,
                    month INT NOT NULL,
                    year INT NOT NULL,
                    sub_by VARCHAR(255),
                    sub_at TIMESTAMP NULL,
                    sub_note LONGTEXT,
                    app_by VARCHAR(255),
                    app_at TIMESTAMP NULL,
                    app_comment LONGTEXT,
                    UNIQUE KEY unique_page_month_year (page_id, month, year)
                )
            ");
        }
        
        if ($type === 'submit') {
            $sub_note = isset($data['sub_note']) ? mysqli_real_escape_string($conn, $data['sub_note']) : '';
            $workflow_table = 'custom_pages_workflow';
            
            // ✅ ดึง page_name จาก custom_pages
            $page_info_q = $conn->query("SELECT page_name FROM custom_pages WHERE id=$page_id LIMIT 1");
            $page_name = '';
            if($page_info_q && $page_info_q->num_rows > 0) {
                $page_info = $page_info_q->fetch_assoc();
                $page_name = mysqli_real_escape_string($conn, $page_info['page_name']);
            }
            
            // ตรวจสอบ record นี้มีหรือไม่
            $check = $conn->query("SELECT * FROM $workflow_table WHERE page_id=$page_id AND month=$month AND year=$year LIMIT 1");
            
            if($check && $check->num_rows > 0) {
                // มี record แล้ว ให้ update
                $sql = "UPDATE $workflow_table SET page_name='$page_name', sub_by='$user_name', sub_at=NOW(), sub_note='$sub_note' 
                        WHERE page_id=$page_id AND month=$month AND year=$year";
            } else {
                // ยังไม่มี record ให้ insert
                $sql = "INSERT INTO $workflow_table (page_id, page_name, month, year, sub_by, sub_at, sub_note) 
                        VALUES ($page_id, '$page_name', $month, $year, '$user_name', NOW(), '$sub_note')";
            }
        } elseif ($type === 'approve') {
            $app_comment = isset($data['app_comment']) ? mysqli_real_escape_string($conn, $data['app_comment']) : '';
            $workflow_table = 'custom_pages_workflow';
            $sql = "UPDATE $workflow_table SET app_by='$user_name', app_at=NOW(), app_comment='$app_comment' 
                    WHERE page_id=$page_id AND month=$month AND year=$year";
        } elseif ($type === 'cancel_submit') {
            $workflow_table = 'custom_pages_workflow';
            // ✅ ถ้าเป็น cancel_submit เลย ให้ DELETE เลย (ยังไม่ approve)
            $check_app = $conn->query("SELECT app_by FROM $workflow_table WHERE page_id=$page_id AND month=$month AND year=$year LIMIT 1");
            if($check_app && $check_app->num_rows > 0) {
                $app_data = $check_app->fetch_assoc();
                if($app_data['app_by'] == null) {
                    // ยังไม่ approve ให้ DELETE record เลย
                    $sql = "DELETE FROM $workflow_table WHERE page_id=$page_id AND month=$month AND year=$year";
                } else {
                    // มี approve แล้ว ให้ SET sub เป็น NULL เท่านั้น
                    $sql = "UPDATE $workflow_table SET sub_by=NULL, sub_at=NULL, sub_note=NULL 
                            WHERE page_id=$page_id AND month=$month AND year=$year";
                }
            } else {
                $sql = "";
            }
        } elseif ($type === 'cancel_approve') {
            $workflow_table = 'custom_pages_workflow';
            // ✅ ยกเลิก approve ให้ SET app เป็น NULL (keep submit data)
            $sql = "UPDATE $workflow_table SET app_by=NULL, app_at=NULL, app_comment=NULL 
                    WHERE page_id=$page_id AND month=$month AND year=$year";
        }
    } else {
        // สำหรับ Section ICT (รายเดือน)
        $month = intval($data['month']);
        $year = intval($data['year']);
        // ใช้ last_updated = last_updated เพื่อป้องกัน ON UPDATE CURRENT_TIMESTAMP trigger
        if ($type === 'submit') {
            $sub_note = isset($data['sub_note']) ? mysqli_real_escape_string($conn, $data['sub_note']) : '';
            $sql = "UPDATE $table SET sub_by='$user_name', sub_at=NOW(), sub_note='$sub_note', last_updated=last_updated WHERE month=$month AND year=$year";
        } elseif ($type === 'approve') {
            $app_comment = isset($data['app_comment']) ? mysqli_real_escape_string($conn, $data['app_comment']) : '';
            $sql = "UPDATE $table SET app_by='$user_name', app_at=NOW(), app_comment='$app_comment', last_updated=last_updated WHERE month=$month AND year=$year";
        } elseif ($type === 'cancel_submit') {
            $sql = "UPDATE $table SET sub_by=NULL, sub_at=NULL, sub_note=NULL, last_updated=last_updated WHERE month=$month AND year=$year";
        } elseif ($type === 'cancel_approve') {
            $sql = "UPDATE $table SET app_by=NULL, app_at=NULL, app_comment=NULL, last_updated=last_updated WHERE month=$month AND year=$year";
        }
    }

    // ดึง log_name สำหรับ ICT logs
    $log_names_map = [
        'server_logs'   => 'Server Logs',
        'network_logs'  => 'Network Logs',
        'backup_logs'   => 'Backup Logs',
        'hardware_logs' => 'Hardware Logs',
        'software_logs' => 'Software Logs',
    ];

    $hist_month = isset($month) ? (int)$month : (int)date('m');
    $hist_year  = isset($year)  ? (int)$year  : (int)date('Y');

    // ✅ ดึงชื่อก่อน run SQL เสมอ (เพราะ cancel_submit จะ DELETE record ใน workflow table)
    if ($is_custom === 1) {
        // ดึงจาก custom_pages (ตารางหลัก) ไม่ใช่ custom_pages_workflow
        $pname_q = $conn->query("SELECT page_name FROM custom_pages WHERE id=$page_id LIMIT 1");
        $hist_log_name = ($pname_q && $pname_q->num_rows > 0) ? $pname_q->fetch_assoc()['page_name'] : "Custom Page #$page_id";
        $hist_table = 'custom_pages_workflow';
    } else {
        $hist_log_name = $log_names_map[$table] ?? $table;
        $hist_table = $table;
    }

    if ($sql != "" && $conn->query($sql)) {
        // ✅ บันทึก Comment History ทุกครั้งที่มีการ action

        // กำหนด comment text ตาม action type
        $hist_comment = '';
        if ($type === 'submit') {
            $hist_comment = isset($data['sub_note']) ? $data['sub_note'] : '';
        } elseif ($type === 'approve') {
            $hist_comment = isset($data['app_comment']) ? $data['app_comment'] : '';
        }

        $hist_table_esc   = mysqli_real_escape_string($conn, $hist_table);
        $hist_log_esc     = mysqli_real_escape_string($conn, $hist_log_name);
        $hist_type_esc    = mysqli_real_escape_string($conn, $type);
        $hist_comment_esc = mysqli_real_escape_string($conn, $hist_comment);
        $hist_user_esc    = mysqli_real_escape_string($conn, $user_name);

        $conn->query("
            INSERT INTO workflow_comment_history 
                (log_table, log_name, month, year, action_type, comment_text, done_by)
            VALUES 
                ('$hist_table_esc', '$hist_log_esc', $hist_month, $hist_year, '$hist_type_esc', '$hist_comment_esc', '$hist_user_esc')
        ");

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}