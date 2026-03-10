<?php
session_start();

// ระบบ Auto-Detect Path ป้องกันหา db.php ไม่เจอ
$base_path = '';
if (file_exists('../db.php')) {
    include '../db.php'; $base_path = '../'; 
} elseif (file_exists('db.php')) {
    include 'db.php'; $base_path = './';  
} else {
    die("<div style='padding:50px; text-align:center;'><h2>❌ หาไฟล์ db.php ไม่เจอ!</h2></div>");
}

if (!isset($_SESSION['user_id'])) { header("Location: {$base_path}login/login.php"); exit(); }

$cur_m = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$cur_y = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month_names = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"June", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];

// ✅ สร้างตัวเลือกปี 2026 - 2030
$years = range(2026, 2030);

$wf_data = ['ICT' => [], 'OTHER' => []];

$static_logs = [
    'ICT' => [
        ['name' => 'Server Logs', 'table' => 'server_logs', 'link' => $base_path.'Server_log/server.php', 'icon' => 'fa-server', 'is_custom' => 0],
        ['name' => 'Network Logs', 'table' => 'network_logs', 'link' => $base_path.'Network_log/network.php', 'icon' => 'fa-network-wired', 'is_custom' => 0],
        ['name' => 'Backup Logs', 'table' => 'backup_logs', 'link' => $base_path.'Backup_log/backup.php', 'icon' => 'fa-database', 'is_custom' => 0],
        ['name' => 'Hardware/Software', 'table' => 'hardsoft_logs', 'link' => $base_path.'HardSoft_log/hardsoft.php', 'icon' => 'fa-microchip', 'is_custom' => 0]
    ]
];

foreach ($static_logs as $sec_name => $logs) {
    foreach ($logs as $log) {
        $q = $conn->query("SELECT sub_by, sub_at, app_by, app_at FROM {$log['table']} WHERE month=$cur_m AND year=$cur_y LIMIT 1");
        $res = ($q && $q->num_rows > 0) ? $q->fetch_assoc() : ['sub_by'=>null, 'sub_at'=>null, 'app_by'=>null, 'app_at'=>null];
        $log['id_val'] = $cur_m; 
        $log['year_val'] = $cur_y;
        $wf_data[$sec_name][] = array_merge($log, $res);
    }
}

$check_cp = $conn->query("SHOW TABLES LIKE 'custom_pages'");
if($check_cp && $check_cp->num_rows > 0) {
    $cp_q = $conn->query("SELECT * FROM custom_pages ORDER BY id ASC");
    if($cp_q && $cp_q->num_rows > 0){
        while($cp = $cp_q->fetch_assoc()){
            $wf_data['OTHER'][] = [
                'name' => $cp['page_name'] ?? 'Unknown Page',
                'table' => 'custom_pages',
                'link' => $base_path.'custom_page_view.php?id='.($cp['id'] ?? 1),
                'icon' => 'fa-file-lines',
                'is_custom' => 1,
                'id_val' => $cp['id'] ?? 1,
                'year_val' => 0,
                'sub_by' => $cp['sub_by'] ?? null,
                'sub_at' => $cp['sub_at'] ?? null,
                'app_by' => $cp['app_by'] ?? null,
                'app_at' => $cp['app_at'] ?? null
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Management - TTM PM Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?=$base_path?>css/theme.css">
    <link rel="stylesheet" href="<?=$base_path?>css/layout.css">
    <link rel="stylesheet" href="<?=$base_path?>css/custom_page.css">
    <link rel="stylesheet" href="css/workflow.css">
</head>
<body>
    <?php 
    $path = $base_path; 
    include $base_path.'components/header_nav.php'; 
    ?>

    <div class="main">
        <div class="container">
            <div class="card custom-card">
                
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid var(--border);">
                    <div style="display:flex; align-items:center; gap:20px;">
                        <div style="width:55px; height:55px; background:rgba(79, 70, 229, 0.1); color:var(--primary); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.6rem;">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div>
                            <h2 style="margin:0; font-size:1.8rem; color:var(--text-main);">Workflow Approval Center</h2>
                            <p style="margin:0; font-size:0.95rem; color:var(--text-sub);">ระบบจัดการและอนุมัติการตรวจสอบระบบรายเดือน (PM)</p>
                        </div>
                    </div>
                    
                    <form method="GET" class="filter-group" style="display:flex; gap:10px;">
                        <select name="month" onchange="this.form.submit()"><?php foreach($month_names as $n=>$m) echo "<option value='$n' ".($n==$cur_m?'selected':'').">$m</option>"; ?></select>
                        <select name="year" onchange="this.form.submit()">
                            <?php foreach($years as $y) echo "<option value='$y' ".($y==$cur_y?'selected':'').">$y</option>"; ?>
                        </select>
                    </form>
                </div>

                <div class="wf-scroll-area">
                    <?php foreach(['ICT', 'OTHER'] as $sec_name): ?>
                        <div class="sec-title">
                            <?php 
                                $icon_sec = 'fa-folder';
                                if($sec_name == 'ICT') $icon_sec = 'fa-desktop';
                                if($sec_name == 'OTHER') $icon_sec = 'fa-layer-group';
                            ?>
                            <i class="fa-solid <?=$icon_sec?>" style="color:var(--primary)"></i> Section: <?=$sec_name?>
                        </div>

                        <?php if(empty($wf_data[$sec_name])): ?>
                            <div style="padding:30px; text-align:center; color:var(--text-sub); font-style:italic;">ไม่มีข้อมูลในหมวดหมู่นี้</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="wf-table">
                                    <thead>
                                        <tr>
                                            <th width="25%">Log System</th>
                                            <th width="20%">Submission Status</th>
                                            <th width="20%">Approval Status</th>
                                            <th width="35%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($wf_data[$sec_name] as $row): 
                                            $final_link = $row['is_custom'] ? $row['link'] : $row['link']."?month=$cur_m&year=$cur_y";
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="<?=$final_link?>" style="text-decoration:none; color:var(--text-main); font-weight:600; display:flex; align-items:center; gap:10px;">
                                                    <i class="fa-solid <?=$row['icon']?>" style="color:var(--primary); opacity:0.8;"></i> <?=$row['name']?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if($row['sub_at']): ?>
                                                    <span class="badge badge-done">SUBMITTED</span>
                                                    <div class="wf-info"><i class="fa-solid fa-user"></i> <?=$row['sub_by']?><br><?=date('d M Y, H:i', strtotime($row['sub_at']))?></div>
                                                <?php else: ?>
                                                    <span class="badge badge-pending">PENDING</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($row['app_at']): ?>
                                                    <span class="badge badge-done">APPROVED</span>
                                                    <div class="wf-info"><i class="fa-solid fa-user-shield"></i> <?=$row['app_by']?><br><?=date('d M Y, H:i', strtotime($row['app_at']))?></div>
                                                <?php else: ?>
                                                    <span class="badge badge-waiting">AWAITING</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:10px;">
                                                    
                                                    <?php if($row['app_at']): ?>
                                                        <button class="btn-action btn-success" disabled title="ถูกล็อกเพราะ Approve ไปแล้ว">
                                                            <i class="fa-solid fa-lock"></i> Submitted
                                                        </button>
                                                    <?php elseif($row['sub_at']): ?>
                                                        <button class="btn-action btn-danger" onclick="handleAction('cancel_submit', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)">
                                                            <i class="fa-solid fa-rotate-left"></i> Submitted
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-action btn-warning" onclick="handleAction('submit', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)">
                                                            <i class="fa-solid fa-paper-plane"></i> Submit
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if($row['app_at']): ?>
                                                        <button class="btn-action btn-danger" onclick="handleAction('cancel_approve', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)">
                                                            <i class="fa-solid fa-rotate-left"></i> Approved
                                                        </button>
                                                    <?php elseif($row['sub_at']): ?>
                                                        <button class="btn-action btn-warning" onclick="handleAction('approve', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)">
                                                            <i class="fa-solid fa-stamp"></i> Approve
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-action btn-disabled" disabled title="ต้องทำการ Submit ก่อน">
                                                            <i class="fa-solid fa-stamp"></i> Approve
                                                        </button>
                                                    <?php endif; ?>

                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="height: 40px;"></div>
        </div>
    </div>

    <script src="../Workflow/js/workflow.js"></script> 
</body>
</html>