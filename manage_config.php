<?php
session_start();
$timeout = 60 * 60;
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();
if (!isset($_SESSION['user_id'])) { header("Location: login/login.php"); exit(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('Access Denied'); window.location.href='index.php';</script>"; exit();
}

include 'db.php';
$msg = ""; $error = "";

// ==================== จัดการอุปกรณ์ ====================
if (isset($_POST['add_equip'])) {
    $sys  = $_POST['sys_type'];
    $name = trim(mysqli_real_escape_string($conn, $_POST['eq_name']));
    $allowed = ['backup','server','network','hardware','software'];
    if (!in_array($sys, $allowed)) { $error = "⚠️ กรุณาเลือกหมวดหมู่"; }
    elseif ($name) {
        $chk = $conn->query("SELECT id FROM master_equipment WHERE system_type='$sys' AND equipment_name='$name'");
        if ($chk->num_rows == 0) {
            if ($conn->query("INSERT INTO master_equipment (system_type, equipment_name) VALUES ('$sys', '$name')")) {
                $table_map = ['hardware'=>'hardware_logs','software'=>'software_logs','server'=>'server_logs','network'=>'network_logs','backup'=>'backup_logs'];
                $cur_year = (int)date('Y');
                if (isset($table_map[$sys])) {
                    $tbl_pre = $table_map[$sys];
                    for ($mo = 1; $mo <= 12; $mo++) {
                        $conn->query("INSERT INTO $tbl_pre (equipment_name, month, year)
                                      SELECT '$name', $mo, $cur_year FROM DUAL
                                      WHERE NOT EXISTS (SELECT 1 FROM $tbl_pre WHERE equipment_name='$name' AND month=$mo AND year=$cur_year)");
                    }
                }
                $msg = "✅ เพิ่มอุปกรณ์สำเร็จ!";
            } else { $error = "❌ SQL Error: " . $conn->error; }
        } else { $error = "⚠️ ชื่อซ้ำ"; }
    }
}
if (isset($_GET['del_eq'])) {
    $id = intval($_GET['del_eq']);
    $q  = $conn->query("SELECT * FROM master_equipment WHERE id=$id");
    if ($q->num_rows > 0) {
        $row  = $q->fetch_assoc();
        $name = mysqli_real_escape_string($conn, $row['equipment_name']);
        $sys  = $row['system_type'];
        $table_map = ['hardware'=>'hardware_logs','software'=>'software_logs','server'=>'server_logs','network'=>'network_logs','backup'=>'backup_logs'];
        if (isset($table_map[$sys])) $conn->query("DELETE FROM {$table_map[$sys]} WHERE equipment_name='$name'");
        if ($conn->query("DELETE FROM master_equipment WHERE id=$id")) { $msg = "🗑️ ลบสำเร็จ!"; }
    }
}

// ==================== จัดการหัวข้อตรวจ ====================
if (isset($_POST['add_task'])) {
    $sys_input = $_POST['sys_type_task'];
    $label     = mysqli_real_escape_string($conn, $_POST['task_label']);
    $freq      = $_POST['frequency'];
    $allowed_sys = ['server','network','hardware','software','backup'];
    if (!in_array($sys_input, $allowed_sys)) { $error = "⚠️ เลือกหมวดหมู่"; }
    else {
        $table_map = ['hardware'=>'hardware_logs','software'=>'software_logs','server'=>'server_logs','network'=>'network_logs','backup'=>'backup_logs'];
        $tb_name   = $table_map[$sys_input];
        $last = $conn->query("SELECT column_name FROM master_tasks WHERE system_type='$sys_input' ORDER BY CAST(SUBSTRING(column_name,6) AS UNSIGNED) DESC LIMIT 1")->fetch_assoc();
        $next = 1;
        if ($last && preg_match('/task_(\d+)/', $last['column_name'], $m_match)) { $next = (int)$m_match[1] + 1; }
        $new_col = "task_" . $next;
        if ($conn->query("INSERT INTO master_tasks (system_type, category, column_name, task_label, frequency) VALUES ('$sys_input', NULL, '$new_col', '$label', '$freq')")) {
            if ($conn->query("ALTER TABLE $tb_name ADD COLUMN $new_col TINYINT(1) DEFAULT NULL COMMENT '$label ($freq)'")) {
                $msg = "✅ เพิ่มหัวข้อสำเร็จ! ($new_col)";
            } else {
                $conn->query("DELETE FROM master_tasks WHERE system_type='$sys_input' AND column_name='$new_col'");
                $error = "❌ สร้างคอลัมน์ไม่สำเร็จ: " . $conn->error;
            }
        } else { $error = "❌ บันทึกไม่สำเร็จ: " . $conn->error; }
    }
}
if (isset($_GET['del_task'])) {
    $id = intval($_GET['del_task']);
    $q  = $conn->query("SELECT * FROM master_tasks WHERE id=$id");
    if ($q->num_rows > 0) {
        $row = $q->fetch_assoc(); $sys = $row['system_type']; $col = $row['column_name'];
        $table_map = ['hardware'=>'hardware_logs','software'=>'software_logs','server'=>'server_logs','network'=>'network_logs','backup'=>'backup_logs'];
        if (isset($table_map[$sys])) {
            if ($conn->query("DELETE FROM master_tasks WHERE id=$id")) {
                $chk = $conn->query("SHOW COLUMNS FROM {$table_map[$sys]} LIKE '$col'");
                if ($chk->num_rows > 0) $conn->query("ALTER TABLE {$table_map[$sys]} DROP COLUMN $col");
                $msg = "🗑️ ลบหัวข้อสำเร็จ!";
            }
        }
    }
}

// ==================== จัดการหน้าอิสระ ====================
if (isset($_POST['save_custom_page'])) {
    $p_name = trim(mysqli_real_escape_string($conn, $_POST['page_name']));
    $p_id   = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
    if ($p_name) {
        if ($p_id > 0) { $conn->query("UPDATE custom_pages SET page_name='$p_name' WHERE id=$p_id"); $msg = "✅ แก้ไขชื่อหน้าสำเร็จ!"; }
        else {
            $chk = $conn->query("SELECT id FROM custom_pages WHERE page_name='$p_name'");
            if ($chk->num_rows == 0) { $conn->query("INSERT INTO custom_pages (page_name) VALUES ('$p_name')"); $msg = "✅ สร้างหน้า '$p_name' สำเร็จ!"; }
            else { $error = "⚠️ ชื่อหน้านี้มีอยู่แล้ว"; }
        }
    }
}
if (isset($_GET['del_page'])) {
    $pid = intval($_GET['del_page']);
    $files_q = $conn->query("SELECT file_path FROM custom_page_files WHERE page_id=$pid");
    while ($f = $files_q->fetch_assoc()) { if (file_exists("uploads/".$f['file_path'])) unlink("uploads/".$f['file_path']); }
    if ($conn->query("DELETE FROM custom_pages WHERE id=$pid")) { $msg = "🗑️ ลบหน้าสำเร็จ!"; }
}

$equipments   = $conn->query("SELECT * FROM master_equipment ORDER BY system_type ASC, equipment_name ASC");
$tasks_all = $conn->query("SELECT * FROM master_tasks ORDER BY system_type ASC, CAST(SUBSTRING(column_name,6) AS UNSIGNED) ASC");
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
    <style>
        /* ── Delete confirm modal ───────────────────────── */
        .del-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.6); z-index:9999;
            align-items:center; justify-content:center;
            backdrop-filter:blur(3px);
        }
        .del-overlay.active { display:flex; }
        .del-box {
            background:var(--bg-card); border-radius:16px;
            padding:32px 28px 26px; max-width:420px; width:92%;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
            border:1px solid var(--border); text-align:center;
        }
        .del-icon-wrap {
            width:58px; height:58px; border-radius:50%;
            background:#fee2e2; color:#ef4444;
            display:flex; align-items:center; justify-content:center;
            font-size:1.7rem; margin:0 auto 16px;
        }
        .del-title { margin:0 0 8px; font-size:1.15rem; font-weight:800; color:var(--text-main); }
        .del-sub   { margin:0 0 24px; font-size:0.87rem; color:var(--text-sub); line-height:1.65; }
        .del-sub strong { color:var(--text-main); }
        .del-btns  { display:flex; gap:10px; justify-content:center; }
        .dbtn {
            padding:10px 24px; border-radius:10px; border:none;
            font-weight:700; font-size:0.88rem; cursor:pointer;
            transition:all 0.16s; display:inline-flex; align-items:center; gap:7px;
        }
        .dbtn-cancel { background:var(--border); color:var(--text-main); }
        .dbtn-cancel:hover { background:rgba(79,70,229,0.1); }
        .dbtn-delete { background:linear-gradient(135deg,#ef4444,#b91c1c); color:#fff; }
        .dbtn-delete:hover { opacity:.88; transform:translateY(-1px); }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo"><i class="fa-solid fa-gear"></i> Manage Config</div>
        <div class="header-right">
            <a href="index.php" class="back-btn-header"><i class="fa-solid fa-arrow-left"></i> <span class="back-text">Back</span></a>
            <div class="divider-v"></div>
            <button class="theme-btn" onclick="toggleTheme()" title="Toggle Theme"><i class="fa-solid fa-moon" id="themeIcon"></i></button>
        </div>
    </header>
    <div class="main">
        <div class="container">


            <!-- อุปกรณ์ -->
            <div class="card">
                <h2><i class="fa-solid fa-server"></i> จัดการรายชื่ออุปกรณ์</h2>
                <form method="POST" class="form-row">
                    <select name="sys_type" id="filter_eq" onchange="filterTable('eq_table', this.value)" required style="flex:0.4">
                        <option value="all">-- แสดงทั้งหมด --</option>
                        <option value="backup">Backup Logs</option>
                        <option value="server">Server Logs</option>
                        <option value="network">Network Logs</option>
                        <option value="hardware">Hardware Logs</option>
                        <option value="software">Software Logs</option>
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
                                <td style="text-align:center;"><button class="del-btn" style="background:none;border:none;cursor:pointer;"
                                onclick="openDelModal(
                                    '?del_eq=<?=$row['id']?>',
                                    'ยืนยันการลบอุปกรณ์',
                                    'ต้องการลบ <strong><?=htmlspecialchars($row['equipment_name'])?></strong> ใช่ไหม?<br><span style=\'color:#ef4444;font-size:.81rem;\'>ข้อมูล Log ทั้งหมดของอุปกรณ์นี้จะถูกลบด้วย</span>'
                                )"><i class="fa-solid fa-trash" style="color:#ef4444;"></i></button></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align:center;padding:20px;">ไม่พบข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- หัวข้อตรวจ ทั้งหมด -->
            <div class="card">
                <h2><i class="fa-solid fa-list-check"></i> จัดการหัวข้อตรวจ</h2>
                <form method="POST" class="form-row">
                    <select name="sys_type_task" id="filter_task" onchange="filterTable('task_table', this.value)" required style="flex:0.35">
                        <option value="all">-- แสดงทั้งหมด --</option>
                        <option value="hardware">Hardware Logs</option>
                        <option value="software">Software Logs</option>
                        <option value="server">Server Logs</option>
                        <option value="network">Network Logs</option>
                        
                    </select>
                    <input type="text" name="task_label" placeholder="ชื่อหัวข้อตรวจ..." style="flex:1;" required>
                    <select name="frequency" style="width:130px;">
                        <option value="M">Monthly (M)</option>
                        <option value="3M">Quarterly (3M)</option>
                        <option value="6M">Semiannual (6M)</option>
                        <option value="Y">Yearly (Y)</option>
                    </select>
                    <button type="submit" name="add_task"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                </form>
                <div class="table-scroll-fixed">
                    <table id="task_table">
                        <thead><tr><th width="15%">System</th><th>Task Name</th><th width="12%">Column</th><th width="10%">Freq</th><th width="10%" style="text-align:center">Action</th></tr></thead>
                        <tbody>
                            <?php if ($tasks_all->num_rows > 0): while($t = $tasks_all->fetch_assoc()): 
                                $sys = $t['system_type'];
                                $badge_color = ['hardware'=>'#4f46e5','software'=>'#9333ea','server'=>'#10b981','network'=>'#0ea5e9','backup'=>'#f59e0b'];
                                $color = $badge_color[$sys] ?? '#6b7280';
                            ?>
                            <tr data-sys="<?=$sys?>">
                                <td><span class="badge" style="background:<?=$color?>;color:#fff;"><?=$sys?></span></td>
                                <td><?=$t['task_label']?></td>
                                <td><span style="font-size:0.8rem;color:var(--text-sub)">[<?=$t['column_name']?>]</span></td>
                                <td><b><?=$t['frequency']?></b></td>
                                <td style="text-align:center;"><button class="del-btn" style="background:none;border:none;cursor:pointer;"
                                onclick="openDelModal(
                                    '?del_task=<?=$t['id']?>',
                                    'ยืนยันการลบหัวข้อตรวจ',
                                    'ต้องการลบหัวข้อ <strong><?=htmlspecialchars($t['task_label'])?></strong> ใช่ไหม?<br><span style=\'color:#ef4444;font-size:.81rem;\'>คอลัมน์ [<?=$t['column_name']?>] จะถูกลบออกจากตารางด้วย</span>'
                                )"><i class="fa-solid fa-trash" style="color:#ef4444;"></i></button></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align:center;padding:20px;">ยังไม่มีหัวข้อตรวจ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- หน้าอิสระ -->
            <div class="card">
                <h2><i class="fa-solid fa-file-circle-plus"></i> จัดการหน้าใหม่ (OTHER Section)</h2>
                <form method="POST" class="form-row" id="customPageForm">
                    <input type="hidden" name="page_id" id="page_id" value="0">
                    <input type="text" name="page_name" id="page_name" placeholder="ชื่อหน้า เช่น รายงาน Audit..." style="flex:1;" required>
                    <button type="submit" name="save_custom_page" id="btnPageSubmit" style="background:#10b981"><i class="fa-solid fa-plus"></i> สร้างหน้า</button>
                    <button type="button" id="btnCancelEdit" style="background:#6b7280;display:none;" onclick="cancelEditPage()">ยกเลิก</button>
                </form>
                <div class="table-scroll-fixed">
                    <table>
                        <thead><tr><th>ชื่อหน้า</th><th width="20%" style="text-align:center">จัดการ</th></tr></thead>
                        <tbody>
                            <?php while($p = $custom_pages->fetch_assoc()): ?>
                            <tr>
                                <td><i class="fa-solid fa-file-lines"></i> <?=$p['page_name']?></td>
                                <td align="center">
                                    <button onclick="editPage(<?=$p['id']?>, '<?=addslashes($p['page_name'])?>')" class="edit-btn" style="border:none;background:none;color:#f59e0b;cursor:pointer;margin-right:10px;"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="del-btn" style="background:none;border:none;cursor:pointer;"
                                    onclick="openDelModal(
                                        '?del_page=<?=$p['id']?>',
                                        'ยืนยันการลบหน้า',
                                        'ต้องการลบหน้า <strong><?=addslashes(htmlspecialchars($p['page_name']))?></strong> ใช่ไหม?<br><span style=\'color:#ef4444;font-size:.81rem;\'>ไฟล์แนบทั้งหมดในหน้านี้จะถูกลบด้วย</span>'
                                    )"><i class="fa-solid fa-trash" style="color:#ef4444;"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; if($custom_pages->num_rows == 0) echo "<tr><td colspan='2' align='center'>ยังไม่มีหน้าใหม่</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="height:50px;"></div>
        </div>
    </div>
    <script src="js/manage_config.js"></script>


    <!-- ── Delete Confirm Modal ─────────────────────────── -->
    <div class="del-overlay" id="delModal">
        <div class="del-box">
            <div class="del-icon-wrap"><i class="fa-solid fa-trash-can"></i></div>
            <h3 class="del-title" id="delTitle">ยืนยันการลบ</h3>
            <p class="del-sub" id="delSub"></p>
            <div class="del-btns">
                <button class="dbtn dbtn-cancel" onclick="closeDelModal()">
                    <i class="fa-solid fa-xmark"></i> ยกเลิก
                </button>
                <a class="dbtn dbtn-delete" id="delConfirmBtn" href="#">
                    <i class="fa-solid fa-trash-can"></i> ลบเลย
                </a>
            </div>
        </div>
    </div>

    <!-- ── Toast notification ───────────────────────── -->
    <div id="toast" style="
        position:fixed; bottom:26px; right:26px;
        background:#1e1e1e; color:#fff;
        padding:13px 20px; border-radius:12px;
        font-size:.88rem; font-weight:600;
        display:flex; align-items:center; gap:10px;
        box-shadow:0 8px 30px rgba(0,0,0,.3);
        transform:translateY(80px); opacity:0;
        transition:all .35s cubic-bezier(.34,1.56,.64,1);
        z-index:10000; pointer-events:none;
        max-width:360px; word-break:break-word;
    "></div>

    <script>
        function showToast(msg, type) {
            var t = document.getElementById('toast');
            t.innerHTML = msg;
            t.style.borderLeft = type === 'ok'
                ? '4px solid #10b981'
                : type === 'warn'
                    ? '4px solid #f59e0b'
                    : '4px solid #ef4444';
            t.style.transform = 'translateY(0)';
            t.style.opacity   = '1';
            clearTimeout(t._timer);
            t._timer = setTimeout(function () {
                t.style.transform = 'translateY(80px)';
                t.style.opacity   = '0';
            }, 3800);
        }

        <?php if ($msg): ?>
        window.addEventListener('DOMContentLoaded', function () {
            var m = '<?= addslashes(htmlspecialchars($msg)) ?>';
            var type = (m.indexOf('🗑️') !== -1) ? 'warn' : 'ok';
            showToast(m, type);
        });
        <?php elseif ($error): ?>
        window.addEventListener('DOMContentLoaded', function () {
            showToast('<?= addslashes(htmlspecialchars($error)) ?>', 'err');
        });
        <?php endif; ?>

        // ── Delete confirm modal ──────────────────────────
        function openDelModal(href, title, sub) {
            document.getElementById('delTitle').textContent = title;
            document.getElementById('delSub').innerHTML    = sub;
            document.getElementById('delConfirmBtn').href  = href;
            document.getElementById('delModal').classList.add('active');
        }
        function closeDelModal() {
            document.getElementById('delModal').classList.remove('active');
        }
        document.getElementById('delModal').addEventListener('click', function(e) {
            if (e.target === this) closeDelModal();
        });
    </script>
</body>
</html>
