//เอาไว้รีเซ็ตรหัสผ่านผู้ใช้ในระบบ
<?php
include 'db.php';

// ตั้งค่ารหัสผ่านใหม่ที่ต้องการ
$new_password = "123456";
$target_user = "admin"; // ตรวจสอบตัวพิมพ์เล็ก-ใหญ่ให้ตรงกับในฐานข้อมูล

// เข้ารหัสรหัสผ่าน
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// อัปเดตลงฐานข้อมูล
$sql = "UPDATE users SET password = '$hashed_password' WHERE username = '$target_user'";

if ($conn->query($sql) === TRUE) {
    echo "<h1>รีเซ็ตรหัสผ่านสำเร็จ!</h1>";
    echo "Username: " . $target_user . "<br>";
    echo "Password: " . $new_password . "<br><br>";
    echo "<a href='login.php'>คลิกที่นี่เพื่อไปหน้า Login</a>";
} else {
    echo "Error updating record: " . $conn->error;
}
?>