<?php
session_start();
include '../db.php';

// รับค่า ID หน้าจาก URL
$page_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page_data = $conn->query("SELECT * FROM custom_pages WHERE id = $page_id")->fetch_assoc();

if (!$page_data) { header("Location: index.php"); exit(); }

$msg = ""; $error = "";

// --- ส่วนจัดการอัปโหลดไฟล์ ---
if (isset($_POST['upload'])) {
    $date = $_POST['report_date']; 
    if (!empty($_FILES['file_upload']['name'])) {
        $file = $_FILES['file_upload'];
        $filename_original = pathinfo($file['name'], PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = $filename_original . "_" . time() . "." . $ext;
        
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
        if (move_uploaded_file($file['tmp_name'], "uploads/" . $new_name)) {
            $conn->query("INSERT INTO custom_page_files (page_id, report_date, file_path, file_type) VALUES ($page_id, '$date', '$new_name', '$ext')");
            $msg = " อัปโหลดสำเร็จ!";
        } else { $error = "❌ อัปโหลดไม่สำเร็จ"; }
    }
}

// --- ส่วนจัดการลบไฟล์ ---
if (isset($_GET['del_file'])) {
    $fid = intval($_GET['del_file']);
    $f_info = $conn->query("SELECT file_path FROM custom_page_files WHERE id=$fid AND page_id=$page_id")->fetch_assoc();
    if($f_info) {
        if(file_exists("uploads/".$f_info['file_path'])) unlink("uploads/".$f_info['file_path']);
        $conn->query("DELETE FROM custom_page_files WHERE id=$fid");
        header("Location: custom_page_view.php?id=$page_id"); exit();
    }
}

$files = $conn->query("SELECT * FROM custom_page_files WHERE page_id = $page_id ORDER BY report_date DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$page_data['page_name']?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../Custom_page/css/custom.css">
    <link rel="stylesheet" href="../Custom_page/css/custom_page.css">
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
    <?php 
    $path = "../"; 
    include '../components/header_nav.php'; 
    ?>

    <div class="main">
        <div class="container">
            


            <div class="card custom-card">
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:30px;">
                    <div style="width:55px; height:55px; background:rgba(79, 70, 229, 0.1); color:var(--primary); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.6rem;">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <h2 style="margin:0; font-size:1.8rem; color:var(--text-main);"><?=$page_data['page_name']?></h2>
                        <p style="margin:0; font-size:0.95rem; color:var(--text-sub);">ระบบจัดการและจัดเก็บเอกสารดิจิทัลแยกตามวันที่บันทึก</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="upload-zone">
                    <div class="input-group" style="flex: 1; min-width: 200px;">
                        <label><i class="fa-regular fa-calendar"></i> วันที่ลงข้อมูล (ระบุเอง)</label>
                        <input type="date" name="report_date" value="<?=date('Y-m-d')?>" required>
                    </div>
                    <div class="input-group" style="flex: 4; min-width: 350px;">
                        <label><i class="fa-regular fa-file-pdf"></i> เลือกไฟล์ที่ต้องการบันทึก (PDF หรือ รูปภาพ)</label>
                        <input type="file" name="file_upload" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                    <button type="submit" name="upload" class="btn-upload">
                        <i class="fa-solid fa-cloud-arrow-up"></i> บันทึกข้อมูล
                    </button>
                </form>

                <div class="wf-scroll-area">
                    <div class="table-responsive">
                        <table class="file-table">
                            <thead>
                                <tr>
                                    <th width="15%">วันที่ลงข้อมูล</th>
                                    <th>ชื่อไฟล์เอกสาร</th>
                                    <th width="10%">ประเภท</th>
                                    <th width="10%" style="text-align:center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($f = $files->fetch_assoc()): 
                                    $file_ext = $f['file_type'];
                                    $icon_class = ($file_ext == 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
                                    $icon_color = ($file_ext == 'pdf') ? '#ef4444' : '#3b82f6';
                                    $full_filename = $f['file_path'];
                                    $last_underscore = strrpos($full_filename, '_');
                                    $display_name = ($last_underscore !== false) ? substr($full_filename, 0, $last_underscore) . "." . $file_ext : $full_filename;
                                ?>
                                <tr>
                                    <td><span style="font-weight:600; color:var(--text-main);"><?=date('d/m/Y', strtotime($f['report_date']))?></span></td>
                                    <td>
                                        <a href="uploads/<?=$f['file_path']?>" target="_blank" class="file-link">
                                            <i class="fa-solid <?=$icon_class?>" style="font-size:1.2rem; opacity:0.9; color: <?=$icon_color?>;"></i>
                                            <?=htmlspecialchars($display_name)?>
                                        </a>
                                    </td>
                                    <td><span class="badge-type"><?=strtoupper($file_ext)?></span></td>
                                    <td align="center">
                                        <button class="del-btn" style="background:none;border:none;color:#ef4444;font-size:1.2rem;cursor:pointer;"
                                            onclick="openDelModal(
                                                '?id=<?=$page_id?>&del_file=<?=$f['id']?>',
                                                'ยืนยันการลบไฟล์',
                                                'ต้องการลบไฟล์ <strong><?=htmlspecialchars($display_name)?></strong> ใช่ไหม?<br><span style=\'color:#ef4444;font-size:.81rem;\'>ไฟล์จะหายถาวร ไม่สามารถกู้คืนได้</span>'
                                            )">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($files->num_rows == 0): ?>
                                <tr>
                                    <td colspan="4" align="center" style="padding:80px; color:var(--text-sub);">
                                        <i class="fa-solid fa-folder-open" style="font-size:3.5rem; display:block; margin-bottom:20px; opacity:0.15;"></i>
                                        ไม่มีข้อมูลเอกสารในระบบ
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div> 
            </div>
            <div style="height: 40px;"></div>
        </div>
    </div>


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

    <!-- ── Toast notification ────────────────────────────── -->
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
        max-width:340px; word-break:break-word;
    "></div>

    <script>
        // ── Theme sync ──────────────────────────────────────
        if (localStorage.getItem('theme') === 'dark')
            document.documentElement.setAttribute('data-theme', 'dark');

        // ── Toast helper ────────────────────────────────────
        function showToast(msg, type) {
            var t = document.getElementById('toast');
            t.innerHTML = msg;
            t.style.borderLeft = type === 'ok'
                ? '4px solid #10b981'
                : '4px solid #ef4444';
            t.style.transform = 'translateY(0)';
            t.style.opacity   = '1';
            clearTimeout(t._timer);
            t._timer = setTimeout(function () {
                t.style.transform = 'translateY(80px)';
                t.style.opacity   = '0';
            }, 3800);
        }

        // ── Fire toast from PHP result ───────────────────────
        <?php if ($msg): ?>
        window.addEventListener('DOMContentLoaded', function () {
            showToast('✅ <?= addslashes(htmlspecialchars($msg)) ?>', 'ok');
        });
        <?php elseif ($error): ?>
        window.addEventListener('DOMContentLoaded', function () {
            showToast('❌ <?= addslashes(htmlspecialchars($error)) ?>', 'err');
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