<?php
session_start();
include '../db.php'; // ถอย 1 ชั้นเพื่อหาไฟล์ db.php

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) { 
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['role'] = $row['role'];
            
            // ** แก้จุดที่ 1: เปลี่ยนให้เด้งไปหน้า index.php (Home) **
            header("Location: ../index.php"); 
            exit();
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้งานนี้";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PM System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../login/css/login.css">
    
</head>
<body>
    <div class="login-card">
        <div class="logo" style="font-size: 3rem; color: #000000; margin-bottom: 15px; text-shadow: 0 4px 10px rgba(0,0,0,0.3);">
            <i class="fa-solid fa-cube"></i>
        </div>
        <h2 style="color: #000000;">PM System</h2>
        <?php if($error): ?>
            <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
        
        <div class="link-group">
            <a href="change_password.php" style="color: #ffffff;"><i class="fa-solid fa-key"></i> เปลี่ยนรหัสผ่าน</a>
            <span style="color: #ccc; margin: 0 10px;">|</span>
            <a href="register.php" style="color: #ffffff; font-weight: 500;"><i class="fa-solid fa-user-plus"></i> สมัครสมาชิก</a>
        </div>
    </div>
</body>
</html>