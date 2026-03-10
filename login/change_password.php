<?php
session_start();
include '../db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // --- ส่วนที่แก้ไข: ล็อคเงื่อนไขตามมาตรฐานเว็บทั่วไป ---
    if ($new_password !== $confirm_password) {
        $error = "รหัสผ่านใหม่ไม่ตรงกัน";
    } 
    // 1. บังคับความยาว 8-16 ตัวอักษร
    elseif (strlen($new_password) < 8 || strlen($new_password) > 16) {
        $error = "รหัสผ่านต้องมีความยาว 8-16 ตัว ประกอบด้วยตัวอักษรพิมพ์เล็ก-ใหญ่, ตัวเลข และอักษรพิเศษ เช่น @, #, $, _, -";
    }
    // 2. บังคับต้องมีอักษรพิเศษอย่างน้อย 1 ตัว (และตัวเลข/พิมพ์ใหญ่/เล็ก)
    elseif (!preg_match('/[A-Z]/', $new_password) || 
            !preg_match('/[a-z]/', $new_password) || 
            !preg_match('/[0-9]/', $new_password) || 
            !preg_match('/[\W_]/', $new_password)) { // [\W_] คืออักษรพิเศษรวม underscore
        $error = "รหัสผ่านต้องมีตัวอักษรพิมพ์เล็ก-ใหญ่, ตัวเลข และอักษรพิเศษ เช่น @, #, $, _, -";
    } 
    else {
        // --- ส่วนเดิมของตวง (ไม่แก้) ---
        $sql = "SELECT id, password FROM users WHERE username = '$username'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($current_password, $row['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $user_id = $row['id'];
                $update_sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
                
                if ($conn->query($update_sql) === TRUE) {
                    $message = "เปลี่ยนรหัสผ่านสำเร็จแล้ว!";
                } else {
                    $error = "เกิดข้อผิดพลาด: " . $conn->error;
                }
            } else {
                $error = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
            }
        } else {
            $error = "ไม่พบชื่อผู้ใช้งานนี้";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - PM System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../login/css/change_password.css">
    
</head>
<body>
    <div class="login-card">
        <h2 style="font-size: 1.5rem;"><i class="fa-solid fa-key"></i> เปลี่ยนรหัสผ่าน</h2>
        
        <?php if($error): ?>
            <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>
        
        <?php if($message): ?>
            <div class="success"><i class="fa-solid fa-check-circle"></i> <?=$message?></div>
            <div class="link-group">
                <a href="login.php" style="color:var(--primary); font-weight:600;">กลับไปหน้าเข้าสู่ระบบ</a>
            </div>
        <?php else: ?>
            <form method="POST">
    <div class="input-group">
        <input type="text" name="username" placeholder="ชื่อผู้ใช้งาน (Username)" required>
    </div>
    
    <div class="input-group">
        <input type="password" name="current_password" id="current_password" placeholder="รหัสผ่านปัจจุบัน" required>
        <i class="fa-solid fa-eye toggle-password" onclick="togglePass('current_password', this)"></i>
    </div>

    <div class="input-group">
        <input type="password" name="new_password" id="new_password" placeholder="รหัสผ่านใหม่ (8-16 ตัว)" required>
        <i class="fa-solid fa-eye toggle-password" onclick="togglePass('new_password', this)"></i>
    </div>

    <div class="input-group">
        <input type="password" name="confirm_password" id="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required>
        <i class="fa-solid fa-eye toggle-password" onclick="togglePass('confirm_password', this)"></i>
    </div>

    <button type="submit">ยืนยันการเปลี่ยนรหัส</button>
</form>
            <div class="link-group">
                <a href="login.php"><i class="fa-solid fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="../login/js/change_password.js"></script>
</body>
</html>