<?php
session_start();

// --- Session Timeout (ตัดการเชื่อมต่อถ้าไม่ขยับเมาส์ 1 ชม.) ---
$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();

// --- ตรวจสอบว่า Login หรือยัง ---
if (!isset($_SESSION['user_id'])) { header("Location: login/login.php"); exit(); }

// --- 🔥 ระบบป้องกัน: เฉพาะ Admin เท่านั้นที่เข้าได้ 🔥 ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>
        alert('Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (Admin Only)');
        window.location.href = 'index.php'; 
    </script>";
    exit();
}

// --- เชื่อมต่อฐานข้อมูล ---
include 'db.php'; 

// 🔥 AUTO FIX DATA (โค้ดช่วยแก้หมวดหมู่ให้อัตโนมัติ) 🔥
$conn->query("UPDATE master_tasks SET category='hardware' WHERE system_type='hardsoft' AND (category IS NULL OR category='') AND (task_label LIKE '%battery%' OR task_label LIKE '%hardware%' OR task_label LIKE '%disk%' OR task_label LIKE '%temp%' OR task_label LIKE '%firmware%' OR task_label LIKE '%Clean dust%' OR task_label LIKE '%Check cables%')");
$conn->query("UPDATE master_tasks SET category='software' WHERE system_type='hardsoft' AND (category IS NULL OR category='') AND (task_label LIKE '%app%' OR task_label LIKE '%Window%')");

$msg = ""; $error = "";

// ---------------------------------------------------------
// ส่วนจัดการข้อมูล (Logic การเพิ่ม/แก้ไข/ลบ)
// ---------------------------------------------------------

// --- 1. จัดการอุปกรณ์ (Equipment) ---
if (isset($_POST['add_equip'])) {
    $sys = $_POST['sys_type'];
    $name = trim(mysqli_real_escape_string($conn, $_POST['eq_name']));
    if ($sys == "all") { $error = "⚠️ กรุณาเลือกหมวดหมู่"; } elseif ($name) {
        $check = $conn->query("SELECT id FROM master_equipment WHERE system_type='$sys' AND equipment_name='$name'");
        if ($check->num_rows == 0) {
            if ($conn->query("INSERT INTO master_equipment (system_type, equipment_name) VALUES ('$sys', '$name')")) {
                $msg = "✅ เพิ่มอุปกรณ์สำเร็จ!";
            } else { $error = "❌ SQL Error: " . $conn->error; }
        } else { $error = "⚠️ ชื่อซ้ำ"; }
    }
}
if (isset($_GET['del_eq'])) {
    $id = intval($_GET['del_eq']);
    $q = $conn->query("SELECT * FROM master_equipment WHERE id=$id");
    if ($q->num_rows > 0) {
        $row = $q->fetch_assoc();
        $name = mysqli_real_escape_string($conn, $row['equipment_name']);
        $conn->query("DELETE FROM server_logs WHERE equipment_name='$name'");
        $conn->query("DELETE FROM network_logs WHERE equipment_name='$name'");
        $conn->query("DELETE FROM hardsoft_logs WHERE equipment_name='$name'");
        $conn->query("DELETE FROM backup_logs WHERE equipment_name='$name'");
        if ($conn->query("DELETE FROM master_equipment WHERE id=$id")) { $msg = "🗑️ ลบสำเร็จ!"; }
    }
}

// --- 2. จัดการหัวข้อตรวจ (Tasks) ---
if (isset($_POST['add_task'])) {
    $sys_input = $_POST['sys_type_task']; 
    $label = mysqli_real_escape_string($conn, $_POST['task_label']);
    $freq = $_POST['frequency'];
    $sys_db = $sys_input; $cat_db = "NULL"; $tb_name = "";

    if ($sys_input == 'all') { $error = "⚠️ เลือกหมวดหมู่"; } else {
        if ($sys_input == 'server') { $tb_name = 'server_logs'; }
        elseif ($sys_input == 'network') { $tb_name = 'network_logs'; }
        elseif ($sys_input == 'hardsoft') { $tb_name = 'hardsoft_logs'; }
        elseif ($sys_input == 'hardware') { $sys_db = 'hardsoft'; $cat_db = "'hardware'"; $tb_name = 'hardsoft_logs'; }
        elseif ($sys_input == 'software') { $sys_db = 'hardsoft'; $cat_db = "'software'"; $tb_name = 'hardsoft_logs'; }

        if ($tb_name != "") {
            $last = $conn->query("SELECT column_name FROM master_tasks WHERE system_type='$sys_db' ORDER BY id DESC LIMIT 1")->fetch_assoc();
            $next = 1;
            if ($last && preg_match('/task_(\d+)/', $last['column_name'], $m)) { $next = (int)$m[1] + 1; }
            $new_col = "task_" . $next;

            if ($conn->query("INSERT INTO master_tasks (system_type, category, column_name, task_label, frequency) VALUES ('$sys_db', $cat_db, '$new_col', '$label', '$freq')")) {
                if ($conn->query("ALTER TABLE $tb_name ADD COLUMN $new_col TINYINT(1) DEFAULT NULL COMMENT '$label ($freq)'")) {
                    $msg = "✅ เพิ่มหัวข้อสำเร็จ!";
                } else {
                    $conn->query("DELETE FROM master_tasks WHERE system_type='$sys_db' AND column_name='$new_col'");
                    $error = "❌ สร้างคอลัมน์ไม่สำเร็จ";
                }
            } else { $error = "❌ บันทึกไม่สำเร็จ"; }
        }
    }
}
if (isset($_GET['del_task'])) {
    $id = intval($_GET['del_task']);
    $q = $conn->query("SELECT * FROM master_tasks WHERE id=$id");
    if ($q->num_rows > 0) {
        $row = $q->fetch_assoc();
        $sys = $row['system_type'];
        $col = $row['column_name'];
        $tb_map = ['server'=>'server_logs', 'network'=>'network_logs', 'hardsoft'=>'hardsoft_logs'];
        if (isset($tb_map[$sys])) {
            if ($conn->query("DELETE FROM master_tasks WHERE id=$id")) {
                $check = $conn->query("SHOW COLUMNS FROM {$tb_map[$sys]} LIKE '$col'");
                if($check->num_rows > 0) { $conn->query("ALTER TABLE {$tb_map[$sys]} DROP COLUMN $col"); }
                $msg = "🗑️ ลบหัวข้อสำเร็จ!";
            }
        }
    }
}

// --- 🔥 3. จัดการหน้าอิสระ (Custom Pages) - เพิ่มและแก้ไข 🔥 ---
if (isset($_POST['save_custom_page'])) {
    $p_name = trim(mysqli_real_escape_string($conn, $_POST['page_name']));
    $p_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;

    if ($p_name) {
        if ($p_id > 0) {
            // โหมดแก้ไข
            $conn->query("UPDATE custom_pages SET page_name='$p_name' WHERE id=$p_id");
            $msg = "✅ แก้ไขชื่อหน้าสำเร็จ!";
        } else {
            // โหมดเพิ่มใหม่
            $check = $conn->query("SELECT id FROM custom_pages WHERE page_name='$p_name'");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO custom_pages (page_name) VALUES ('$p_name')");
                $msg = "✅ สร้างหน้า '$p_name' สำเร็จ!";
            } else { $error = "⚠️ ชื่อหน้านี้มีอยู่แล้ว"; }
        }
    }
}
if (isset($_GET['del_page'])) {
    $pid = intval($_GET['del_page']);
    $files_q = $conn->query("SELECT file_path FROM custom_page_files WHERE page_id=$pid");
    while($f = $files_q->fetch_assoc()) {
        if(file_exists("uploads/".$f['file_path'])) unlink("uploads/".$f['file_path']);
    }
    if ($conn->query("DELETE FROM custom_pages WHERE id=$pid")) { $msg = "🗑️ ลบหน้าสำเร็จ!"; }
}

$equipments = $conn->query("SELECT * FROM master_equipment ORDER BY system_type ASC, equipment_name ASC");
$tasks = $conn->query("SELECT * FROM master_tasks ORDER BY system_type ASC, category ASC, id ASC");
$custom_pages = $conn->query("SELECT * FROM custom_pages ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Config</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/config.css">
    <link rel="stylesheet" href="css/layout.css">
</head>
<body>

    <header class="header">
        <div class="logo">
            <i class="fa-solid fa-gear"></i> Manage Config
        </div>
        <div class="header-right">
            <a href="index.php" class="back-btn-header">
                <i class="fa-solid fa-arrow-left"></i> <span class="back-text">Back</span>
            </a>
            <div class="divider-v"></div>
            <button class="theme-btn" onclick="toggleTheme()" title="Toggle Theme">
                <i class="fa-solid fa-moon" id="themeIcon"></i>
            </button>
        </div>
    </header>

    <div class="main">
        <div class="container">
            
            <?php if($msg): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?=$msg?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?=$error?></div><?php endif; ?>

            <div class="card">
                <h2><i class="fa-solid fa-server"></i> จัดการรายชื่ออุปกรณ์</h2>
                <form method="POST" class="form-row">
                    <select name="sys_type" id="filter_eq" onchange="filterTable('eq_table', this.value)" required style="flex:0.4">
                        <option value="all">-- แสดงทั้งหมด --</option>
                        <option value="backup">Backup Logs</option>
                        <option value="server">Server Logs</option>
                        <option value="network">Network Logs</option>
                        <option value="hardsoft">Hardware & Software</option>
                    </select>
                    <input type="text" name="eq_name" placeholder="ชื่ออุปกรณ์ใหม่..." style="flex:1;" required>
                    <button type="submit" name="add_equip"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                </form>
                <div class="table-scroll-fixed">
                    <table id="eq_table">
                        <thead><tr><th width="15%">System</th><th>Equipment Name</th><th width="10%" style="text-align:center">Action</th></tr></thead>
                        <tbody>
                            <?php if ($equipments->num_rows > 0): while($row = $equipments->fetch_assoc()): ?>
                            <tr data-sys="<?=$row['system_type']?>">
                                <td><span class="badge bg-<?=$row['system_type']?>"><?=$row['system_type']?></span></td>
                                <td><?=$row['equipment_name']?></td>
                                <td style="text-align:center;"><a href="?del_eq=<?=$row['id']?>" class="del-btn" onclick="return confirm('ลบ?')"><i class="fa-solid fa-trash"></i></a></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align:center; padding:20px;">ไม่พบข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-list-check"></i> จัดการหัวข้อตรวจ</h2>
                <form method="POST" class="form-row">
                    <select name="sys_type_task" id="filter_task" onchange="filterTable('task_table', this.value)" required style="flex:0.4">
                        <option value="all">-- แสดงทั้งหมด --</option>
                        <option value="server">Server Logs</option>
                        <option value="network">Network Logs</option>
                        <option value="hardsoft">Hardware & Software</option>
                        <option value="hardware" style="color:#4f46e5; font-weight:600;">↳ Hardware Only</option>
                        <option value="software" style="color:#9333ea; font-weight:600;">↳ Software Only</option>
                    </select>
                    <input type="text" name="task_label" placeholder="ชื่อหัวข้อตรวจ..." style="flex:1;" required>
                    <select name="frequency" style="width:120px;">
                        <option value="M">Monthly</option>
                        <option value="3M">3M</option>
                        <option value="6M">6M</option>
                        <option value="Y">Yearly</option>
                    </select>
                    <button type="submit" name="add_task"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                </form>
                <div class="table-scroll-fixed">
                    <table id="task_table">
                        <thead><tr><th width="15%">System</th><th>Task Name</th><th>Freq</th><th width="10%" style="text-align:center">Action</th></tr></thead>
                        <tbody>
                            <?php 
                            $current_cat = "";
                            while($t = $tasks->fetch_assoc()): 
                                $cls = 'bg-'.$t['system_type'];
                                $display_sys = $t['system_type'];
                                $data_cat = $t['category']; 

                                if ($t['system_type'] == 'hardsoft') {
                                    if ($t['category'] == 'hardware') {
                                        $display_sys = 'Hardware'; $cls = 'bg-hardware';
                                        if ($current_cat != 'hardware') {
                                            echo "<tr class='section-header' data-sys='hardsoft' data-cat='hardware'><td colspan='4'><i class='fa-solid fa-microchip'></i> Hardware Section</td></tr>";
                                            $current_cat = 'hardware';
                                        }
                                    } elseif ($t['category'] == 'software') {
                                        $display_sys = 'Software'; $cls = 'bg-software';
                                        if ($current_cat != 'software') {
                                            echo "<tr class='section-header' data-sys='hardsoft' data-cat='software'><td colspan='4'><i class='fa-brands fa-windows'></i> Software Section</td></tr>";
                                            $current_cat = 'software';
                                        }
                                    }
                                }
                            ?>
                            <tr data-sys="<?=$t['system_type']?>" data-cat="<?=$data_cat?>">
                                <td><span class="badge <?=$cls?>"><?=$display_sys?></span></td>
                                <td><?=$t['task_label']?> <span style="font-size:0.8rem; color:var(--text-sub)">[<?=$t['column_name']?>]</span></td>
                                <td><b><?=$t['frequency']?></b></td>
                                <td style="text-align:center;"><a href="?del_task=<?=$t['id']?>" class="del-btn" onclick="return confirm('ลบ?')"><i class="fa-solid fa-trash"></i></a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-file-circle-plus"></i> จัดการหน้าใหม่ (OTHER Section)</h2>
                <form method="POST" class="form-row" id="customPageForm">
                    <input type="hidden" name="page_id" id="page_id" value="0">
                    <input type="text" name="page_name" id="page_name" placeholder="ชื่อหน้า เช่น รายงาน Audit, รายงานประจำเดือน..." style="flex:1;" required>
                    <button type="submit" name="save_custom_page" id="btnPageSubmit" style="background:#10b981"><i class="fa-solid fa-plus"></i> สร้างหน้า</button>
                    <button type="button" id="btnCancelEdit" style="background:#6b7280; display:none;" onclick="cancelEditPage()">ยกเลิก</button>
                </form>
                <div class="table-scroll-fixed">
                    <table>
                        <thead><tr><th>ชื่อหน้า</th><th width="20%" style="text-align:center">จัดการ</th></tr></thead>
                        <tbody>
                            <?php while($p = $custom_pages->fetch_assoc()): ?>
                            <tr>
                                <td><i class="fa-solid fa-file-lines"></i> <?=$p['page_name']?></td>
                                <td align="center">
                                    <button onclick="editPage(<?=$p['id']?>, '<?=addslashes($p['page_name'])?>')" class="edit-btn" style="border:none; background:none; color:#f59e0b; cursor:pointer; margin-right:10px;"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <a href="?del_page=<?=$p['id']?>" class="del-btn" onclick="return confirm('ลบหน้านี้และไฟล์ทั้งหมด?')"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; if($custom_pages->num_rows == 0) echo "<tr><td colspan='2' align='center'>ยังไม่มีหน้าใหม่</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="height: 50px;"></div>
        </div>
    </div> 

    <script src="js/manage_config.js"></script>
</body>
</html>