<?php
session_start();

// ระบบ Auto-Detect Path ป้องกันหา db.php ไม่เจอ
$current_page = 'workflow';
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
$cur_m = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$cur_y = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month_names = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"June", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];

// ✅ สร้างตัวเลือกปี 2026 - 2030
$years = range(2026, 2030);

$wf_data = ['ICT' => []];

$static_logs = [
    'ICT' => [
        ['name' => 'Server Logs', 'table' => 'server_logs', 'link' => $base_path.'Server_log/server.php', 'icon' => 'fa-server', 'is_custom' => 0],
        ['name' => 'Network Logs', 'table' => 'network_logs', 'link' => $base_path.'Network_log/network.php', 'icon' => 'fa-network-wired', 'is_custom' => 0],
        ['name' => 'Backup Logs', 'table' => 'backup_logs', 'link' => $base_path.'Backup_log/backup.php', 'icon' => 'fa-database', 'is_custom' => 0],
        ['name' => 'Hardware Logs', 'table' => 'hardware_logs', 'link' => $base_path.'Hardware_log/hardware.php', 'icon' => 'fa-microchip', 'is_custom' => 0],
        ['name' => 'Software Logs', 'table' => 'software_logs', 'link' => $base_path.'Software_log/software.php', 'icon' => 'fa-windows', 'is_custom' => 0]
    ]
];

foreach ($static_logs as $sec_name => $logs) {
    foreach ($logs as $log) {
        $q = $conn->query("SELECT sub_by, sub_at, app_by, app_at, sub_note, app_comment FROM {$log['table']} WHERE month=$cur_m AND year=$cur_y LIMIT 1");
        $res = ($q && $q->num_rows > 0) ? $q->fetch_assoc() : ['sub_by'=>null, 'sub_at'=>null, 'app_by'=>null, 'app_at'=>null, 'sub_note'=>null, 'app_comment'=>null];
        $log['id_val'] = $cur_m; 
        $log['year_val'] = $cur_y;
        $wf_data[$sec_name][] = array_merge($log, $res);
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
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../Workflow/css/workflow_layout.css">
    <link rel="stylesheet" href="../Workflow/css/workflow.css">
    <link rel="stylesheet" href="../Workflow/css/workflow_page.css">
    <style>
        /* ── Modal overlay ─────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 999; align-items: center; justify-content: center;
            backdrop-filter: blur(3px);
        }
        .modal-overlay.active { display: flex; }

        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 0;
            max-width: 640px; width: 92%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            border: 1px solid var(--border);
            display: flex; flex-direction: column;
            max-height: 90vh;
            overflow: hidden;
        }

        /* ── Modal header ───────────────────────────────── */
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 22px 26px 18px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .modal-header-left { display: flex; align-items: center; gap: 12px; }
        .modal-icon-wrap {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .modal-icon-submit  { background: #fef3c7; color: #d97706; }
        .modal-icon-approve { background: #dcfce7; color: #16a34a; }
        .modal-header h3 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-main); }
        .modal-header-sub { margin: 2px 0 0; font-size: 0.8rem; color: var(--text-sub); }
        .modal-close {
            background: none; border: none; font-size: 1.2rem; color: var(--text-sub);
            cursor: pointer; width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; transition: background 0.18s; flex-shrink: 0;
        }
        .modal-close:hover { background: rgba(239,68,68,0.1); color: #ef4444; }

        /* ── Modal body ─────────────────────────────────── */
        .modal-body {
            padding: 22px 26px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-body::-webkit-scrollbar { width: 6px; }
        .modal-body::-webkit-scrollbar-track { background: transparent; }
        .modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }

        .modal-form-group { margin-bottom: 6px; }
        .modal-form-group label {
            display: flex; align-items: center; gap: 7px;
            margin-bottom: 10px; font-weight: 600;
            color: var(--text-main); font-size: 0.9rem;
        }
        .modal-form-group label i { color: var(--primary); font-size: 0.85rem; }

        /* ── Textarea ───────────────────────────────────── */
        .modal-form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            resize: vertical;
            min-height: 140px;
            max-height: 340px;
            font-family: inherit;
            font-size: 0.92rem;
            line-height: 1.7;
            background: var(--bg-body);
            color: var(--text-main);
            box-sizing: border-box;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .modal-form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
        }
        .modal-form-group textarea::placeholder { color: var(--text-sub); opacity: 0.55; }

        /* char counter */
        .char-counter {
            text-align: right; font-size: 0.75rem;
            color: var(--text-sub); margin-top: 5px;
        }
        .char-counter.warn { color: #f59e0b; }
        .char-counter.over { color: #ef4444; font-weight: 700; }

        /* ── Modal footer ───────────────────────────────── */
        .modal-footer {
            display: flex; gap: 10px; justify-content: flex-end;
            padding: 16px 26px 20px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }
        .modal-btn {
            padding: 10px 22px; border-radius: 9px; border: none;
            font-weight: 700; font-size: 0.87rem;
            cursor: pointer; transition: all 0.18s;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .modal-btn-submit  { background: var(--primary); color: #fff; }
        .modal-btn-submit:hover  { opacity: 0.88; transform: translateY(-1px); }
        .modal-btn-approve { background: #16a34a; color: #fff; }
        .modal-btn-approve:hover { opacity: 0.88; transform: translateY(-1px); }
        .modal-btn-cancel  { background: var(--border); color: var(--text-main); }
        .modal-btn-cancel:hover  { background: rgba(79,70,229,0.1); }

        /* ── Comment box (in table) ─────────────────────── */
        .comment-box {
            background: rgba(79,70,229,0.05);
            border: 1px solid rgba(79,70,229,0.15);
            border-left: 3px solid var(--primary);
            border-radius: 8px;
            padding: 10px 13px;
            margin-top: 8px;
            font-size: 0.82rem;
            line-height: 1.65;
            color: var(--text-main);
            word-break: break-word;

            /* รองรับข้อความยาว – มี scroll ถ้าเกิน */
            max-height: 110px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .comment-box::-webkit-scrollbar { width: 4px; }
        .comment-box::-webkit-scrollbar-track { background: transparent; }
        .comment-box::-webkit-scrollbar-thumb { background: rgba(79,70,229,0.25); border-radius: 4px; }

        /* ปุ่มขยาย comment */
        .comment-expand-btn {
            background: none; border: none; padding: 0;
            font-size: 0.73rem; color: var(--primary);
            cursor: pointer; font-weight: 600;
            display: inline-flex; align-items: center; gap: 4px;
            margin-top: 4px; opacity: 0.8;
            transition: opacity 0.15s;
        }
        .comment-expand-btn:hover { opacity: 1; }

        .comment-label {
            display: flex; align-items: center; gap: 5px;
            font-weight: 700; color: var(--text-sub);
            font-size: 0.78rem; margin-bottom: 4px;
            text-transform: uppercase; letter-spacing: 0.04em;
        }

        /* ── Full-text modal (read-only view) ───────────── */
        .view-modal-content {
            background: var(--bg-card); border-radius: 16px;
            padding: 0; max-width: 580px; width: 92%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            border: 1px solid var(--border);
            max-height: 85vh; display: flex; flex-direction: column;
        }
        .view-modal-body {
            padding: 20px 24px 24px;
            overflow-y: auto; flex: 1;
        }
        .view-modal-body pre {
            margin: 0; font-family: inherit; font-size: 0.92rem;
            line-height: 1.75; color: var(--text-main);
            white-space: pre-wrap; word-break: break-word;
        }
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
                            <h2 style="margin:0; font-size:1.8rem; color:var(--text-main);">Workflow Approval - ICT Section</h2>
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

                <div class="sec-title">
                    <i class="fa-solid fa-desktop" style="color:var(--primary)"></i> Section: ICT
                </div>

                <?php if(empty($wf_data['ICT'])): ?>
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
                                <?php foreach($wf_data['ICT'] as $row): 
                                    $final_link = $row['link']."?month=$cur_m&year=$cur_y";
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
                                            <?php if(isset($row['sub_note']) && trim($row['sub_note']) !== ''): ?>
                                                <div class="comment-label"><i class="fa-solid fa-note-sticky"></i> Note</div>
                                                <div class="comment-box"><?=htmlspecialchars($row['sub_note'])?></div>
                                                <?php if(mb_strlen($row['sub_note']) > 120): ?>
                                                <button class="comment-expand-btn" onclick="viewFullText('📝 Submit Note', this.previousElementSibling.textContent)">
                                                    <i class="fa-solid fa-expand"></i> ดูทั้งหมด
                                                </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-pending">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($row['app_at']): ?>
                                            <span class="badge badge-done">APPROVED</span>
                                            <div class="wf-info"><i class="fa-solid fa-user-shield"></i> <?=$row['app_by']?><br><?=date('d M Y, H:i', strtotime($row['app_at']))?></div>
                                            <?php if(isset($row['app_comment']) && trim($row['app_comment']) !== ''): ?>
                                                <div class="comment-label"><i class="fa-solid fa-comment"></i> Comment</div>
                                                <div class="comment-box"><?=htmlspecialchars($row['app_comment'])?></div>
                                                <?php if(mb_strlen($row['app_comment']) > 120): ?>
                                                <button class="comment-expand-btn" onclick="viewFullText('💬 Approve Comment', this.previousElementSibling.textContent)">
                                                    <i class="fa-solid fa-expand"></i> ดูทั้งหมด
                                                </button>
                                                <?php endif; ?>
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
                                                <button class="btn-action btn-danger" onclick="handleAction('cancel_submit', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-rotate-left"></i> ยกเลิก Submit</button>
                                            <?php else: ?>
                                                <button class="btn-action btn-warning" onclick="openNoteModal('submit', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                                            <?php endif; ?>

                                            <?php if($row['app_at']): ?>
                                                <button class="btn-action btn-danger" onclick="handleAction('cancel_approve', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-rotate-left"></i> ยกเลิก</button>
                                            <?php elseif($row['sub_at']): ?>
                                                <button class="btn-action btn-warning" onclick="openNoteModal('approve', '<?=$row['table']?>', <?=$row['is_custom']?>, <?=$row['id_val']?>, <?=$row['year_val']?>)"><i class="fa-solid fa-stamp"></i> Approve</button>
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

    <script src="../Workflow/js/workflow.js"></script> 
    
    <!-- Note / Comment Input Modal -->
    <div class="modal-overlay" id="noteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-icon-wrap" id="modalIconWrap">
                        <i class="fa-solid fa-paper-plane" id="modalIcon"></i>
                    </div>
                    <div>
                        <h3 id="modalTitle">Add Note</h3>
                        <p class="modal-header-sub" id="modalSubtitle">กรอก Note สำหรับการ Submit</p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeNoteModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="noteForm">
                <div class="modal-body">
                    <div class="modal-form-group">
                        <label id="labelText"><i class="fa-solid fa-pen-to-square"></i> <span id="labelSpan">Note:</span></label>
                        <textarea id="noteText"
                                  placeholder="พิมพ์ข้อความได้เลย ไม่จำกัดความยาว…"
                                  rows="6"
                                  maxlength="5000"
                                  oninput="updateCharCount(this)"></textarea>
                        <div class="char-counter" id="charCounter">0 / 5,000</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeNoteModal()">
                        <i class="fa-solid fa-xmark"></i> ยกเลิก
                    </button>
                    <button type="submit" class="modal-btn modal-btn-submit" id="modalSubmitBtn">
                        <i class="fa-solid fa-paper-plane"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Full Text Modal (read-only) -->
    <div class="modal-overlay" id="viewModal">
        <div class="view-modal-content">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-icon-wrap modal-icon-submit">
                        <i class="fa-solid fa-align-left" id="viewModalIcon"></i>
                    </div>
                    <h3 id="viewModalTitle">ข้อความทั้งหมด</h3>
                </div>
                <button class="modal-close" onclick="document.getElementById('viewModal').classList.remove('active')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="view-modal-body">
                <pre id="viewModalText"></pre>
            </div>
            <div class="modal-footer" style="justify-content:flex-end;">
                <button class="modal-btn modal-btn-cancel"
                        onclick="document.getElementById('viewModal').classList.remove('active')">
                    <i class="fa-solid fa-xmark"></i> ปิด
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentAction = {};

        /* ── Open note/comment modal ─────────────────────── */
        function openNoteModal(type, table, is_custom, id_val, year_val) {
            currentAction = { type, table, is_custom, id_val, year_val };

            const isSubmit = (type === 'submit');

            document.getElementById('modalTitle').textContent    = isSubmit ? 'Submit with Note' : 'Approve with Comment';
            document.getElementById('modalSubtitle').textContent = isSubmit ? 'กรอก Note สำหรับการ Submit (ถ้ามี)' : 'กรอก Comment สำหรับการ Approve (ถ้ามี)';
            document.getElementById('labelSpan').textContent     = isSubmit ? 'Note:' : 'Comment:';
            document.getElementById('noteText').placeholder      = isSubmit ? 'พิมพ์ Note… (ไม่บังคับ)' : 'พิมพ์ Comment… (ไม่บังคับ)';

            // icon + colour
            const iconWrap = document.getElementById('modalIconWrap');
            const icon     = document.getElementById('modalIcon');
            const submitBtn = document.getElementById('modalSubmitBtn');
            iconWrap.className = 'modal-icon-wrap ' + (isSubmit ? 'modal-icon-submit' : 'modal-icon-approve');
            icon.className     = 'fa-solid ' + (isSubmit ? 'fa-paper-plane' : 'fa-stamp');
            submitBtn.className = 'modal-btn ' + (isSubmit ? 'modal-btn-submit' : 'modal-btn-approve');
            submitBtn.innerHTML = isSubmit
                ? '<i class="fa-solid fa-paper-plane"></i> Submit'
                : '<i class="fa-solid fa-stamp"></i> Approve';

            document.getElementById('noteText').value = '';
            document.getElementById('charCounter').textContent = '0 / 5,000';
            document.getElementById('charCounter').className   = 'char-counter';
            document.getElementById('noteModal').classList.add('active');
            setTimeout(() => document.getElementById('noteText').focus(), 80);
        }

        function closeNoteModal() {
            document.getElementById('noteModal').classList.remove('active');
            currentAction = {};
        }

        /* ── Char counter ────────────────────────────────── */
        function updateCharCount(el) {
            const len = el.value.length;
            const max = parseInt(el.getAttribute('maxlength')) || 5000;
            const counter = document.getElementById('charCounter');
            counter.textContent = len.toLocaleString() + ' / ' + max.toLocaleString();
            counter.className = 'char-counter' + (len >= max ? ' over' : len >= max * 0.85 ? ' warn' : '');
        }

        /* ── View full text (read-only) ──────────────────── */
        function viewFullText(title, text) {
            document.getElementById('viewModalTitle').textContent = title;
            document.getElementById('viewModalText').textContent  = text;
            document.getElementById('viewModal').classList.add('active');
        }
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });

        /* ── Form submit ─────────────────────────────────── */
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const noteText = document.getElementById('noteText').value.trim();
            const submitBtn = document.getElementById('modalSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก…';

            let payload = {
                type: currentAction.type,
                table: currentAction.table,
                is_custom: currentAction.is_custom
            };
            if (currentAction.is_custom === 1) {
                payload.page_id = currentAction.id_val;
            } else {
                payload.month = currentAction.id_val;
                payload.year  = currentAction.year_val;
            }
            if (currentAction.type === 'submit')  payload.sub_note    = noteText;
            if (currentAction.type === 'approve') payload.app_comment = noteText;

            fetch('update_workflow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) { closeNoteModal(); location.reload(); }
                else { alert('Error: ' + d.error); }
            })
            .catch(() => alert('ไม่สามารถติดต่อไฟล์ update_workflow.php ได้ครับ'))
            .finally(() => {
                submitBtn.disabled = false;
            });
        });

        document.getElementById('noteModal').addEventListener('click', function(e) {
            if (e.target === this) closeNoteModal();
        });
    </script>