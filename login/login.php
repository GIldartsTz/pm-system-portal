<?php
session_start();
include '../db.php'; // ถอย 1 ชั้นเพื่อหาไฟล์ db.php

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) { 
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];
            
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
    <style>
        :root{--primary:#4f46e5;--text-main:#1e293b;--border:#e2e8f0;}
        
        body { 
            margin: 0; 
            font-family: 'Inter', 'Prompt', sans-serif; 
            
            /* --- ส่วนเปลี่ยนพื้นหลังเป็นรูปภาพ --- */
            background-image: url('bg-login.jpg'); /* เปลี่ยนชื่อไฟล์ตรงนี้ */
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            background-size: cover;
            /* ---------------------------------- */

            color: var(--text-main); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
        }

        /* --- Glassmorphism Card Style --- */
        .login-card { 
            /* พื้นหลังโปร่งแสงและเบลอ */
            background: rgba(255, 255, 255, 0.15); 
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            
            /* ขอบบางๆ เพิ่มมิติ */
            border: 1px solid rgba(255, 255, 255, 0.4);
            
            padding: 40px; 
            border-radius: 20px; 
            width: 100%; 
            max-width: 400px; 
            
            /* เงาฟุ้งๆ ให้ดูลอยออกมา */
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            text-align: center; 
        }

        .logo { font-size: 2.5rem; color: #fff; margin-bottom: 10px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        h2 { margin: 0 0 20px; font-weight: 700; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        
        input { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 15px; 
            /* ช่องกรอกข้อมูลแบบใส */
            background: rgba(255, 255, 255, 0.5); 
            border: 1px solid rgba(255, 255, 255, 0.3); 
            border-radius: 8px; 
            font-size: 1rem; 
            color: #0f172a; 
            box-sizing: border-box; 
            outline: none; 
            transition: 0.2s; 
        }
        input::placeholder { color: #475569; }
        
        input:focus { 
            background: rgba(255, 255, 255, 0.9); 
            border-color: var(--primary); 
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        
        button { 
            width: 100%; 
            padding: 12px; 
            /* ปุ่มไล่เฉดสี */
            background: linear-gradient(90deg, #4f46e5 0%, #3b82f6 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 1rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: 0.2s; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        button:hover { transform: scale(1.02); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3); }
        
        .error { color: #ef4444; font-size: 0.9rem; margin-bottom: 15px; text-align: left; background: rgba(254, 226, 226, 0.9); padding: 10px; border-radius: 6px; border: 1px solid #fecaca; }
        
        .link-group { margin-top: 20px; font-size: 0.9rem; }
        .link-group a { color: #f1f5f9; text-decoration: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
        .link-group a:hover { color: #fff; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo"><i class="fa-solid fa-cube"></i></div>
        <h2>PM System</h2>
        <?php if($error): ?>
            <div class="error"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
        
        <div class="link-group">
            <a href="change_password.php"><i class="fa-solid fa-key"></i> เปลี่ยนรหัสผ่าน</a>
        </div>
    </div>
</body>
</html>