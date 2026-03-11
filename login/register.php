<?php
session_start();
include '../db.php'; 

// ✅ 1. ต้องประกาศตัวแปรไว้บนสุดแบบนี้ครับ เพื่อป้องกัน Warning Undefined variable
$error = ''; 
$success = ''; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'staff'; 

    // ✅ เงื่อนไขรหัสผ่านจากหน้า change_password
    if ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } 
    elseif (strlen($password) < 8 || strlen($password) > 16) {
        $error = "รหัสผ่านต้องมีความยาว 8-16 ตัว ประกอบด้วยตัวอักษรพิมพ์เล็ก-ใหญ่, ตัวเลข และอักษรพิเศษ เช่น @, #, $, _, -";
    }
    elseif (!preg_match('/[A-Z]/', $password) || 
            !preg_match('/[a-z]/', $password) || 
            !preg_match('/[0-9]/', $password) || 
            !preg_match('/[\W_]/', $password)) { 
        $error = "รหัสผ่านต้องมีตัวอักษรพิมพ์เล็ก-ใหญ่, ตัวเลข และอักษรพิเศษ เช่น @, #, $, _, -";
    } 
    else {
        $check_sql = "SELECT id FROM users WHERE username = '$username'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $error = "ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, fullname, role) 
                    VALUES ('$username', '$hashed_password', '$fullname', '$role')";
            
            if ($conn->query($sql)) {
                $success = "สมัครสมาชิกสำเร็จ! กำลังพากลับไปหน้า Login...";
                header("refresh:2; url=login.php");
            } else {
                $error = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PM System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../login/css/change_password.css"> </head>
<body>
    <div class="login-card">
        <div class="logo" style="font-size: 3rem; color: #000000; margin-bottom: 15px; text-shadow: 0 4px 10px rgba(0,0,0,0.3);">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        
        <h2 style="color: #000000;">Create Account</h2>
        <p style="color: rgba(0, 0, 0, 0.8); margin-bottom:25px; font-size:0.9rem;">ลงทะเบียนเพื่อเข้าใช้งานระบบ PM System</p>

        <?php if($error): ?>
            <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><i class="fa-solid fa-circle-check"></i> <?=$success?></div>
        <?php else: ?>
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="fullname" placeholder="ชื่อ-นามสกุล" required autofocus>
                </div>
                <div class="input-group">
                    <input type="text" name="username" placeholder="ชื่อผู้ใช้งาน (Username)" required>
                </div>
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="รหัสผ่าน (8-16 ตัว)" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePass('password', this)"></i>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="ยืนยันรหัสผ่าน" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePass('confirm_password', this)"></i>
                </div>
                <button type="submit" style="margin-top: 10px;">ลงทะเบียน</button>
            </form>
        <?php endif; ?>
        
        <div class="link-group">
            <a href="login.php" style="color: #ffffff;"><i class="fa-solid fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
    <script src="../login/js/change_password.js"></script>
</body>
</html>