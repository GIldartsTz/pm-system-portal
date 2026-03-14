<?php
session_start();


$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();

if (!isset($_SESSION['user_id'])) { header("Location: login/login.php"); exit(); }

include 'db.php'; 


$u_id_update = $_SESSION['user_id'];
$conn->query("UPDATE users SET last_login = NOW() WHERE id = '$u_id_update'");



$dashboard_module = [
    'name' => 'Dashboard Overview', 
    'link' => 'dashboard.php', 
    'icon' => 'fa-chart-pie', 
    'color' => '#f00c2a', 
    'desc' => 'View overall statistics'
];

// Sub Modules
$sub_modules = [
    ['name' => 'Backup Logs', 'link' => 'Backup_log/backup.php', 'icon' => 'fa-database', 'color' => '#3b82f6', 'desc' => 'Daily backup checklist'],
    ['name' => 'Server Logs', 'link' => 'Server_log/server.php', 'icon' => 'fa-server', 'color' => '#10b981', 'desc' => 'Server maintenance'],
    ['name' => 'Network Logs', 'link' => 'Network_log/network.php', 'icon' => 'fa-network-wired', 'color' => '#f59e0b', 'desc' => 'Network status & devices'],
    ['name' => 'Hardware Logs', 'link' => 'Hardware_log/hardware.php', 'icon' => 'fa-microchip', 'color' => '#4f46e5', 'desc' => 'Hardware maintenance'],
    ['name' => 'Software Logs', 'link' => 'Software_log/software.php', 'icon' => 'fa-brands fa-windows', 'color' => '#9333ea', 'desc' => 'Software maintenance']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Portal - PM System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/home.css">
    
</head>
<body>
    <header class="header">
        <div class="logo"><i class="fa-solid fa-cube"></i> PM System</div>
        <div style="display:flex; gap:15px; align-items:center;">
            <button onclick="toggleTheme()" style="background:none;border:none;cursor:pointer;color:var(--text-sub);font-size:1.2rem"><i class="fa-solid fa-moon" id="themeIcon"></i></button>
            <span style="color:var(--border)">|</span>
            <a href="login/logout.php" style="color:#ef4444; font-weight:600;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="welcome-sec">
                <h1>Preventive Maintenance System <span><?=htmlspecialchars($_SESSION['fullname'])?></span> 👋</h1>
                <p>Welcome to the Preventive Maintenance System. Manage your maintenance tasks efficiently.</p>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="manage_config.php" class="config-btn"><i class="fa-solid fa-gear"></i> System Config</a>
                <?php endif; ?>
                
            </div>

            <div class="grid-layout">
                <a href="<?=$dashboard_module['link']?>" class="dashboard-card">
                    <div class="dashboard-content">
                        <h2><?=$dashboard_module['name']?></h2>
                        <p><?=$dashboard_module['desc']?></p>
                        <span class="dashboard-btn">View Analytics <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                    <i class="fa-solid <?=$dashboard_module['icon']?> dashboard-icon"></i>
                </a>

                <?php foreach($sub_modules as $m): ?>
                <a href="<?=$m['link']?>" class="module-card" style="color: <?=$m['color']?>">
                    <div class="mod-icon" style="background: <?=$m['color']?>15;">
                        <i class="fa-solid <?=$m['icon']?>"></i>
                    </div>
                    <div class="mod-info">
                        <h3><?=$m['name']?></h3>
                        <p><?=$m['desc']?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script src="js/index.js"></script>
</body>
</html>