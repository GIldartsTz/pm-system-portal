<?php
// รับค่า path จากหน้าที่เรียกใช้ (ถ้าไม่มีให้เป็นค่าว่าง)
$p = isset($path) ? $path : '../'; 

// ป้องกัน Error หากหน้าใดไม่ได้ประกาศตัวแปร $current_page
$current_page = isset($current_page) ? $current_page : '';

// เชื่อมต่อฐานข้อมูลเพื่อดึงรายการหน้าใหม่
if(!isset($conn)) {
    include_once 'db.php';
}
?>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<header class="header">
    <div class="header-left">
        <button class="menu-btn" id="toggleBtn" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="logo">
            <i class="fa-solid fa-cube"></i> PM System
        </div>
    </div>
    <div class="header-right" style="display:flex;align-items:center;gap:15px">
        <span style="font-size:0.85rem; font-weight:600; color:var(--text-sub); display:flex; align-items:center; gap:6px;">
            <i class="fa-regular fa-clock"></i> <span id="clock">--:--</span>
        </span>
        <button class="menu-btn" id="themeBtn" onclick="toggleTheme()">
            <i class="fa-solid fa-moon"></i>
        </button>
        <div style="width:36px; height:36px; background:var(--primary); border-radius:50%; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem;">
            <?=strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1))?>
        </div>
    </div>
</header>

<nav class="sidebar" id="sidebar">
    <div class="nav">
        <div class="nav-title">Main Menu</div>
        <a href="<?=$p?>index.php" class="nav-link <?=($current_page=='home')?'active':''?>">
            <i class="fa-solid fa-house"></i> <span>Home</span>
        </a>
        <a href="<?=$p?>dashboard.php" class="nav-link <?=($current_page=='dashboard')?'active':''?>">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        
        <div class="nav-title">ICT STAFF</div>
        <a href="<?=$p?>Backup_log/backup.php" class="nav-link <?=($current_page=='backup')?'active':''?>">
            <i class="fa-solid fa-database"></i> <span>Backup Logs</span>
        </a>
        <a href="<?=$p?>Server_log/server.php" class="nav-link <?=($current_page=='server')?'active':''?>">
            <i class="fa-solid fa-server"></i> <span>Server Logs</span>
        </a>
        <a href="<?=$p?>Network_log/network.php" class="nav-link <?=($current_page=='network')?'active':''?>">
            <i class="fa-solid fa-network-wired"></i> <span>Network Logs</span>
        </a>
        <a href="<?=$p?>HardSoft_log/hardsoft.php" class="nav-link <?=($current_page=='hardsoft')?'active':''?>">
            <i class="fa-solid fa-microchip"></i> <span>Hardware/Software</span>
        </a>

        <div class="nav-title">OTHER</div>
        

        <?php
        $nav_pages = $conn->query("SELECT * FROM custom_pages ORDER BY id ASC");
        $is_on_custom_view = (basename($_SERVER['PHP_SELF']) == 'custom_page_view.php');
        
        while($np = $nav_pages->fetch_assoc()):
            // เช็ค active ให้แม่นยำขึ้น โดยดูทั้งชื่อไฟล์และ ID
            $is_active = ($is_on_custom_view && isset($_GET['id']) && $_GET['id'] == $np['id']) ? 'active' : '';
        ?>
        <a href="<?=$p?>custom_page_view.php?id=<?=$np['id']?>" class="nav-link <?=$is_active?>">
            <i class="fa-solid fa-file-invoice"></i> <span><?=$np['page_name']?></span>
        </a>
        <?php endwhile; ?>
        
        <div class="nav-bottom">
            <a href="<?=$p?>login/logout.php" class="nav-link logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</nav>

<script>
    // Theme Toggle
    const root = document.documentElement; 
    if(localStorage.getItem('theme') === 'dark') root.setAttribute('data-theme', 'dark');
    
    function toggleTheme() { 
        const isDark = root.getAttribute('data-theme') === 'dark'; 
        root.setAttribute('data-theme', isDark ? 'light' : 'dark'); 
        localStorage.setItem('theme', isDark ? 'light' : 'dark'); 
        updateIcon(); 
    }
    
    function updateIcon() { 
        const isDark = root.getAttribute('data-theme') === 'dark'; 
        const btn = document.getElementById('themeBtn');
        if(btn) btn.innerHTML = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>'; 
    }
    updateIcon();

    // Sidebar Toggle
    const body = document.body, sidebar = document.getElementById('sidebar'), overlay = document.getElementById('overlay');
    function toggleSidebar() { 
        if (window.innerWidth <= 768) { 
            sidebar.classList.toggle('show'); 
            if(overlay) overlay.classList.toggle('show');
        } else { 
            body.classList.toggle('collapsed'); 
            localStorage.setItem('pm_menu_mini', body.classList.contains('collapsed') ? '1' : '0'); 
        } 
    };
    
    // Load State
    if(localStorage.getItem('pm_menu_mini') === '1' && window.innerWidth > 768) { 
        body.classList.add('collapsed'); 
    }

    // Clock
    setInterval(()=>{ 
        const clk = document.getElementById('clock');
        if(clk) clk.innerText = new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}); 
    }, 1000);
</script>