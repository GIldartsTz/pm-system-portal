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

    // 1. เช็ครหัสใหม่ตรงกันไหม
    if ($new_password !== $confirm_password) {
        $error = "รหัสผ่านใหม่ไม่ตรงกัน";
    } else {
        // 2. เช็ค User และรหัสเดิม
        $sql = "SELECT id, password FROM users WHERE username = '$username'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($current_password, $row['password'])) {
                // 3. อัปเดตรหัสใหม่
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
    <title>Change Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ใช้ CSS เดียวกับหน้า Login เป๊ะๆ */
        :root{--primary:#3b82f6;--bg-body:#f8fafc;--bg-card:#ffffff;--text-main:#1e293b;--border:#e2e8f0;}
        body { margin: 0; font-family: 'Inter', 'Prompt', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { background: var(--bg-card); padding: 40px; border-radius: 16px; border: 1px solid var(--border); width: 100%; max-width: 400px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; }
        h2 { margin: 0 0 20px; font-weight: 700; color: var(--text-main); }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid var(--border); border-radius: 8px; font-size: 1rem; box-sizing: border-box; outline: none; transition: 0.2s; }
        input:focus { border-color: var(--primary); }
        button { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        button:hover { opacity: 0.9; }
        .error { color: #ef4444; font-size: 0.9rem; margin-bottom: 15px; text-align: left; background: #fee2e2; padding: 10px; border-radius: 6px; border: 1px solid #fecaca; }
        .success { color: #166534; font-size: 0.9rem; margin-bottom: 15px; text-align: left; background: #dcfce7; padding: 10px; border-radius: 6px; border: 1px solid #bbf7d0; }
        .link-group { margin-top: 20px; font-size: 0.9rem; }
        .link-group a { color: var(--text-main); text-decoration: none; transition: 0.2s; }
        .link-group a:hover { color: var(--primary); }
    </style>
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
                <input type="text" name="username" placeholder="ชื่อผู้ใช้งาน (Username)" required>
                <input type="password" name="current_password" placeholder="รหัสผ่านปัจจุบัน" required>
                <input type="password" name="new_password" placeholder="รหัสผ่านใหม่" required>
                <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required>
                <button type="submit">ยืนยันการเปลี่ยนรหัส</button>
            </form>
            <div class="link-group">
                <a href="login.php"><i class="fa-solid fa-arrow-left"></i> ยกเลิก / กลับหน้า Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>