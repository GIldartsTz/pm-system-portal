<?php
session_start();
include '../db.php'; // ถอย 1 ชั้นเพื่อหาไฟล์ db.php

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'staff'; // ตั้งค่าเริ่มต้นเป็น staff เพื่อความปลอดภัย

    // 1. เช็คว่ารหัสผ่านตรงกันไหม
    if ($password !== $confirm_password) {
        $error = "รหัสผ่านไม่ตรงกัน";
    } else {
        // 2. เช็คว่าชื่อผู้ใช้งานซ้ำไหม
        $check_sql = "SELECT id FROM users WHERE username = '$username'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            $error = "ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว";
        } else {
            // 3. เข้ารหัสผ่านแบบ Bcrypt
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 4. บันทึกลง Database (ใช้ชื่อคอลัมน์ fullname ตามโครงสร้าง DB ของตวง)
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
    <link rel="stylesheet" href="../login/css/login.css"> <style>
        /* ปรับแต่งเพิ่มเติมเล็กน้อยเพื่อให้รองรับฟิลด์ที่มากขึ้น */
        .login-card { max-width: 400px; padding: 40px; }
        .success { background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; text-align: left; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo"><i class="fa-solid fa-user-plus"></i></div>
        <h2>Create Account</h2>
        <p style="color:var(--text-sub); margin-bottom:25px; font-size:0.9rem;">ลงทะเบียนเพื่อเข้าใช้งานระบบ PM System</p>

        <?php if($error): ?>
            <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><i class="fa-solid fa-circle-check"></i> <?=$success?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="fullname" placeholder="Full Name (ชื่อ-นามสกุล)" required autofocus>
            <input type="text" name="username" placeholder="Username (ไอดีเข้าสู่ระบบ)" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            
            <button type="submit" style="margin-top: 10px;">ลงทะเบียน</button>
        </form>
        
        <div class="link-group" style="margin-top: 25px; border-top: 1px solid var(--border); padding-top: 20px;">
            <a href="login.php"><i class="fa-solid fa-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</body>
</html>