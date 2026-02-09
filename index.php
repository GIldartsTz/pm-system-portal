<?php
session_start();

// --- 1. Session Timeout (1 Hour) ---
$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();

if (!isset($_SESSION['user_id'])) { header("Location: login/login.php"); exit(); }

include 'db.php'; 

// Dashboard Highlight (สีแดง #f00c2a)
$dashboard_module = [
    'name' => 'Dashboard Overview', 
    'link' => 'dashboard.php', 
    'icon' => 'fa-chart-pie', 
    'color' => '#f00c2a', // สีแดงตามขอ
    'desc' => 'View overall statistics'
];

// Sub Modules
$sub_modules = [
    ['name' => 'Backup Log', 'link' => 'Backup_log/index.php', 'icon' => 'fa-database', 'color' => '#3b82f6', 'desc' => 'Daily backup checklist'],
    ['name' => 'Server Check', 'link' => 'Server/index.php', 'icon' => 'fa-server', 'color' => '#10b981', 'desc' => 'Server room maintenance'],
    ['name' => 'Network', 'link' => 'Network/index.php', 'icon' => 'fa-network-wired', 'color' => '#f59e0b', 'desc' => 'Network status & devices'],
    ['name' => 'H/W & S/W', 'link' => 'HardSoft/index.php', 'icon' => 'fa-microchip', 'color' => '#8b5cf6', 'desc' => 'H/W & S/W maintenance']
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
                <h1>Hello, <span><?=htmlspecialchars($_SESSION['fullname'])?></span> 👋</h1>
                <p>Preventive Maintenance System Portal</p>
                <a href="manage_config.php" class="config-btn"><i class="fa-solid fa-gear"></i> System Config</a>
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