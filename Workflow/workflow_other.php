<?php
session_start();

// ระบบ Auto-Detect Path ป้องกันหา db.php ไม่เจอ
$current_page = 'workflow_other';
$base_path = '';
if (file_exists('../db.php')) {
    include '../db.php'; $base_path = '../'; 
} elseif (file_exists('db.php')) {
    include 'db.php'; $base_path = './';  
} else {
    die("<div style='padding:50px; text-align:center;'><h2>❌ หาไฟล์ db.php ไม่เจอ!</h2></div>");
}

if (!isset($_SESSION['user_id'])) { header("Location: {$base_path}login/login.php"); exit(); }

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// ✅ เพิ่ม Month/Year Filter
$cur_m = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$cur_y = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month_names = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"June", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];

// ✅ สร้างตัวเลือกปี 2026 - 2030
$years = range(2026, 2030);

$wf_data = ['OTHER' => []];

$check_cp = $conn->query("SHOW TABLES LIKE 'custom_pages'");
if($check_cp && $check_cp->num_rows > 0) {
    $cp_q = $conn->query("SELECT * FROM custom_pages ORDER BY id ASC");
    if($cp_q && $cp_q->num_rows > 0){
        while($cp = $cp_q->fetch_assoc()){
            // ✅ Filter by month/year - Query จาก custom_pages_workflow
            $check_query = $conn->query("
                SELECT page_name, sub_by, sub_at, app_by, app_at, sub_note, app_comment 
                FROM custom_pages_workflow 
                WHERE page_id={$cp['id']} AND month=$cur_m AND year=$cur_y 
                LIMIT 1
            ");
            
            if($check_query && $check_query->num_rows > 0) {
                $row_data = $check_query->fetch_assoc();
                $display_page_name = $row_data['page_name'] ?? $cp['page_name'] ?? 'Unknown Page';
            } else {
                // ถ้ายังไม่มีการบันทึกสำหรับ month/year นี้ ให้แสดงเป็น empty
                $display_page_name = $cp['page_name'] ?? 'Unknown Page';
                $row_data = [
                    'sub_by' => null,
                    'sub_at' => null,
                    'app_by' => null,
                    'app_at' => null,
                    'sub_note' => null,
                    'app_comment' => null
                ];
            }
            
            $wf_data['OTHER'][] = [
                'name' => $display_page_name,
                'table' => 'custom_pages_workflow',
                'link' => $base_path.'custom_page_view.php?id='.($cp['id'] ?? 1),
                'icon' => 'fa-file-lines',
                'is_custom' => 1,
                'id_val' => $cp['id'] ?? 1,
                'month_val' => $cur_m,
                'year_val' => $cur_y,
                'sub_by' => $row_data['sub_by'] ?? null,
                'sub_at' => $row_data['sub_at'] ?? null,
                'app_by' => $row_data['app_by'] ?? null,
                'app_at' => $row_data['app_at'] ?? null,
                'sub_note' => $row_data['sub_note'] ?? null,
                'app_comment' => $row_data['app_comment'] ?? null
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
    <title>Workflow Management - OTHER Section - TTM PM Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../Workflow/css/workflow_layout.css">
    <link rel="stylesheet" href="../Workflow/css/workflow.css">
    <link rel="stylesheet" href="../Workflow/css/workflow_page.css">
    <style>
        .modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:999; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-content { background:var(--bg-card); border-radius:14px; padding:30px; max-width:600px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.4); border:1px solid var(--border); }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:15px; }
        .modal-header h3 { margin:0; font-size:1.3rem; color:var(--text-main); font-weight:600; }
        .modal-close { background:none; border:none; font-size:1.5rem; color:var(--text-sub); cursor:pointer; padding:0; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:background 0.2s; }
        .modal-close:hover { background:rgba(79,70,229,0.1); }
        .modal-form-group { margin-bottom:15px; }
        .modal-form-group label { display:block; margin-bottom:8px; font-weight:600; color:var(--text-main); font-size:0.95rem; }
        .modal-form-group textarea { width:100%; padding:12px 15px; border:1px solid var(--border); border-radius:8px; resize:vertical; min-height:100px; font-family:inherit; background:var(--input-bg); color:var(--text-main); }
        .modal-form-group textarea::placeholder { color:var(--text-sub); opacity:0.6; }
        .modal-actions { display:flex; gap:10px; margin-top:25px; padding-top:15px; border-top:1px solid var(--border); }
        .modal-btn { padding:10px 20px; border-radius:8px; border:none; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .modal-btn-submit { background:var(--primary); color:white; }
        .modal-btn-submit:hover { opacity:0.9; transform:translateY(-2px); }
        .modal-btn-cancel { background:var(--border); color:var(--text-main); }
        .modal-btn-cancel:hover { background:rgba(79,70,229,0.1); }
        .comment-box { background:rgba(79, 70, 229, 0.05); border-left:3px solid var(--primary); padding:12px 15px; border-radius:4px; font-size:0.9rem; margin-top:8px; line-height:1.5; }
        .comment-label { font-weight:600; color:var(--text-sub); font-size:0.85rem; }
    </style>
</head>
<body>
    <?php 
    $path = $base_path; 
    include $base_path.'components/header_nav.php'; 
    ?>

    <div class="main">
        <div class="container">
            
            <div class="card custom-card" style="margin-bottom: 25px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid var(--border);">
                    <div style="display:flex; align-items:center; gap:20px;">
                        <div style="width:55px; height:55px; background:rgba(79, 70, 229, 0.1); color:var(--primary); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.6rem;">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div>
                            <h2 style="margin:0; font-size:1.8rem; color:var(--text-main);">Workflow Approval - OTHER Section</h2>
                            <p style="margin:0; font-size:0.95rem; color:var(--text-sub);">ระบบจัดการและอนุมัติเอกสารเพิ่มเติม</p>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <form method="GET" class="filter-group" style="display:flex; gap:10px;">
                            <select name="month" onchange="this.form.submit()" style="padding:8px 15px; border-radius:8px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-main); font-weight:600;"><?php foreach($month_names as $n=>$m) echo "<option value='$n' ".($n==$cur_m?'selected':'').">$m</option>"; ?></select>
                            <select name="year" onchange="this.form.submit()" style="padding:8px 15px; border-radius:8px; border:1px solid var(--border); background:var(--bg-card); color:var(--text-main); font-weight:600;">
                                <?php foreach($years as $y) echo "<option value='$y' ".($y==$cur_y?'selected':'').">$y</option>"; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <div class="sec-title">
                    <i class="fa-solid fa-layer-group" style="color:var(--primary)"></i> Section: OTHER
                </div>

                <?php if(empty($wf_data['OTHER'])): ?>
                    <div style="padding:30px; text-align:center; color:var(--text-sub); font-style:italic;">ไม่มีข้อมูลในหมวดหมู่นี้</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="wf-table">
                            <thead>
                                <tr>
                                    <th width="22%">Page Name</th>
                                    <th width="18%">Submission Status</th>
                                    <th width="18%">Approval Status</th>
                                    <th width="42%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($wf_data['OTHER'] as $row): 
                                    $final_link = $row['link'];
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
                                            <?php if($row['sub_note']): ?>
                                                <div class="comment-box">
                                                    <div class="comment-label">📝 Note:</div>
                                                    <?=htmlspecialchars($row['sub_note'])?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-pending">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($row['app_at']): ?>
                                            <span class="badge badge-done">APPROVED</span>
                                            <div class="wf-info"><i class="fa-solid fa-user-shield"></i> <?=$row['app_by']?><br><?=date('d M Y, H:i', strtotime($row['app_at']))?></div>
                                            <?php if($row['app_comment']): ?>
                                                <div class="comment-box">
                                                    <div class="comment-label">💬 Comment:</div>
                                                    <?=htmlspecialchars($row['app_comment'])?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-waiting">AWAITING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                            <?php if($row['app_at']): ?>
                                                <button class="btn-action btn-success" disabled><i class="fa-solid fa-lock"></i> Submitted</button>
                                            <?php elseif($row['sub_at']): ?>
                                                <button class="btn-action btn-danger" onclick="handleAction('cancel_submit', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['month_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-rotate-left"></i> ยกเลิก Submit</button>
                                            <?php else: ?>
                                                <button class="btn-action btn-warning" onclick="openNoteModal('submit', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['month_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                                            <?php endif; ?>

                                            <?php if($row['app_at']): ?>
                                                <button class="btn-action btn-danger" onclick="handleAction('cancel_approve', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['month_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-rotate-left"></i> ยกเลิก</button>
                                            <?php elseif($row['sub_at']): ?>
                                                <button class="btn-action btn-warning" onclick="openNoteModal('approve', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['month_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-stamp"></i> Approve</button>
                                            <?php else: ?>
                                                <button class="btn-action btn-disabled" disabled><i class="fa-solid fa-stamp"></i> Approve</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div style="height: 40px;"></div>
        </div>
    </div>

    <!-- Modal for Note/Comment -->
    <div class="modal-overlay" id="noteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Note</h3>
                <button class="modal-close" onclick="closeNoteModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="noteForm">
                <div class="modal-form-group">
                    <label id="labelText">Note:</label>
                    <textarea id="noteText" placeholder="เพิ่มหมายเหตุ (ถ้ามี)..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="modal-btn modal-btn-submit">Submit</button>
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeNoteModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentAction = {};

        function openNoteModal(type, table, is_custom, id_val, month_val, year_val) {
            currentAction = { type, table, is_custom, id_val, month_val, year_val };
            
            const modal = document.getElementById('noteModal');
            const title = document.getElementById('modalTitle');
            const label = document.getElementById('labelText');
            
            if(type === 'submit') {
                title.textContent = 'Submit with Note';
                label.textContent = 'Note (for submission):';
            } else {
                title.textContent = 'Approve with Comment';
                label.textContent = 'Comment (for approval):';
            }
            
            document.getElementById('noteText').value = '';
            modal.classList.add('active');
        }

        function closeNoteModal() {
            document.getElementById('noteModal').classList.remove('active');
            currentAction = {};
        }

        document.getElementById('noteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const noteText = document.getElementById('noteText').value;
            
            let payload = { 
                type: currentAction.type, 
                table: currentAction.table, 
                is_custom: currentAction.is_custom 
            };
            
            if(currentAction.is_custom === 1) {
                payload.page_id = currentAction.id_val;
                payload.month = currentAction.month_val;
                payload.year = currentAction.year_val;
            } else {
                payload.month = currentAction.id_val;
                payload.year = currentAction.year_val;
            }
            
            // Add note/comment based on action type
            if(currentAction.type === 'submit') {
                payload.sub_note = noteText;
            } else if(currentAction.type === 'approve') {
                payload.app_comment = noteText;
            }

            fetch('update_workflow.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    closeNoteModal();
                    location.reload();
                } else {
                    alert('Error: ' + d.error);
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                alert('ไม่สามารถติดต่อไฟล์ update_workflow.php ได้ครับ');
            });
        });

        function handleAction(type, table, is_custom, id_val, month_val, year_val) {
            const actionNames = {
                'cancel_submit': 'ยกเลิกการ Submit',
                'cancel_approve': 'ยกเลิกการ Approve'
            };

            if(!confirm(`ยืนยันการทำรายการ: ${actionNames[type]} ใช่หรือไม่?`)) return;
            
            let payload = { type: type, table: table, is_custom: is_custom };
            if(is_custom === 1) {
                payload.page_id = id_val;
                payload.month = month_val;
                payload.year = year_val;
            } else {
                payload.month = id_val;
                payload.year = year_val;
            }

            fetch('update_workflow.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) location.reload();
                else alert('Error: ' + d.error);
            })
            .catch(err => {
                console.error('Fetch Error:', err);
                alert('ไม่สามารถติดต่อไฟล์ update_workflow.php ได้ครับ');
            });
        }

        // Close modal when clicking outside
        document.getElementById('noteModal').addEventListener('click', function(e) {
            if(e.target === this) closeNoteModal();
        });
    </script>
</body> 
</html>
