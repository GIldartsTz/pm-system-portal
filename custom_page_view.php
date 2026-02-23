<?php
session_start();
include 'db.php'; 

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
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = "doc_" . time() . "_" . rand(10,99) . "." . $ext;
        
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
        if (move_uploaded_file($file['tmp_name'], "uploads/" . $new_name)) {
            $conn->query("INSERT INTO custom_page_files (page_id, report_date, file_path, file_type) VALUES ($page_id, '$date', '$new_name', '$ext')");
            $msg = "✅ อัปโหลดสำเร็จ!";
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
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/custom_page.css">
</head>
<body>
    <?php 
    $path = ""; 
    include 'components/header_nav.php'; 
    ?>

    <div class="main">
        <div class="container">
            
            <?php if($msg): ?><div class="alert alert-success" style="margin-top:20px; border-radius:10px;"><i class="fa-solid fa-circle-check"></i> <?=$msg?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-error" style="margin-top:20px; border-radius:10px;"><i class="fa-solid fa-circle-xmark"></i> <?=$error?></div><?php endif; ?>

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
                            ?>
                            <tr>
                                <td><span style="font-weight:600; color:var(--text-main);"><?=date('d/m/Y', strtotime($f['report_date']))?></span></td>
                                <td>
                                    <a href="uploads/<?=$f['file_path']?>" target="_blank" class="file-link">
                                        <i class="fa-solid <?=$icon_class?>" style="font-size:1.2rem; opacity:0.8;"></i>
                                        <?=htmlspecialchars($f['file_path'])?>
                                    </a>
                                </td>
                                <td><span class="badge-type"><?=strtoupper($file_ext)?></span></td>
                                <td align="center">
                                    <a href="?id=<?=$page_id?>&del_file=<?=$f['id']?>" 
                                       class="del-btn"
                                       style="color: #ef4444; font-size: 1.2rem;"
                                       onclick="return confirm('คุณต้องการลบไฟล์นี้ใช่หรือไม่?')">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
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
            <div style="height: 40px;"></div>
        </div>
    </div>
    <script src="js/manage_config.js"></script>
    
</body>
</html>