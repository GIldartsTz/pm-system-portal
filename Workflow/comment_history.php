<?php
session_start();
$current_page = 'comment_history';

// ── DB connection ──────────────────────────────────────────────
if (file_exists('../db.php')) {
    include '../db.php'; $base_path = '../';
} elseif (file_exists('db.php')) {
    include 'db.php'; $base_path = './';
} else {
    die("<div style='padding:50px;text-align:center'><h2>❌ หาไฟล์ db.php ไม่เจอ!</h2></div>");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: {$base_path}login/login.php"); exit();
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// ── Filters ────────────────────────────────────────────────────
$filter_table = $_GET['table'] ?? 'all';
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$filter_year  = isset($_GET['year'])  ? (int)$_GET['year']  : 0;
$filter_type  = $_GET['type']  ?? 'all';
$search       = trim($_GET['search'] ?? '');

$month_names = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
$years = range(2026, 2030);

$log_sources = [
    'server_logs'           => ['name'=>'Server Logs',   'icon'=>'fa-server'],
    'network_logs'          => ['name'=>'Network Logs',  'icon'=>'fa-network-wired'],
    'backup_logs'           => ['name'=>'Backup Logs',   'icon'=>'fa-database'],
    'hardware_logs'         => ['name'=>'Hardware Logs', 'icon'=>'fa-microchip'],
    'software_logs'         => ['name'=>'Software Logs', 'icon'=>'fa-windows'],
    'custom_pages_workflow' => ['name'=>'OTHER Section', 'icon'=>'fa-file-invoice'],
];

$type_meta = [
    'submit'         => ['label'=>'Submit Note',     'icon'=>'fa-paper-plane', 'color'=>'#f59e0b','bg'=>'#fef3c7','text'=>'#92400e'],
    'approve'        => ['label'=>'Approve',          'icon'=>'fa-stamp',       'color'=>'#10b981','bg'=>'#dcfce7','text'=>'#166534'],
    'cancel_submit'  => ['label'=>'ยกเลิก Submit',    'icon'=>'fa-rotate-left', 'color'=>'#ef4444','bg'=>'#fee2e2','text'=>'#991b1b'],
    'cancel_approve' => ['label'=>'ยกเลิก Approve',   'icon'=>'fa-ban',         'color'=>'#6366f1','bg'=>'#ede9fe','text'=>'#4c1d95'],
];

// ── Build WHERE ────────────────────────────────────────────────
$where = [];
if ($filter_table !== 'all') $where[] = "log_table = '".mysqli_real_escape_string($conn,$filter_table)."'";
if ($filter_month > 0)       $where[] = "month = $filter_month";
if ($filter_year  > 0)       $where[] = "year = $filter_year";
if ($filter_type  !== 'all') $where[] = "action_type = '".mysqli_real_escape_string($conn,$filter_type)."'";
if ($search !== '')          $where[] = "(log_name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR comment_text LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR done_by LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

// ── Fetch data ─────────────────────────────────────────────────
$history = [];
$q = $conn->query("SELECT * FROM workflow_comment_history $where_sql ORDER BY done_at DESC");
if ($q) while ($r = $q->fetch_assoc()) $history[] = $r;

// ── Summary counts (global, no filter) ────────────────────────
$tc = ['submit'=>0,'approve'=>0,'cancel_submit'=>0,'cancel_approve'=>0];
$cq = $conn->query("SELECT action_type, COUNT(*) as cnt FROM workflow_comment_history GROUP BY action_type");
if ($cq) while ($cr = $cq->fetch_assoc()) $tc[$cr['action_type']] = (int)$cr['cnt'];
$total = array_sum($tc);

// ── Group by Month-Year ────────────────────────────────────────
$grouped = [];
foreach ($history as $h) {
    $key = ($month_names[(int)$h['month']] ?? $h['month']).' '.$h['year'];
    $grouped[$key][] = $h;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Comment History – TTM PM Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../css/theme.css">
<link rel="stylesheet" href="../Workflow/css/workflow_layout.css">
<link rel="stylesheet" href="../Workflow/css/workflow_page.css">
<style>
.page-wrap{width:95%;max-width:1400px;margin:0 auto;padding:30px 20px}
.ph{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:26px;padding-bottom:20px;border-bottom:1px solid var(--border)}
.ph-left{display:flex;align-items:center;gap:14px}
.ph-icon{width:50px;height:50px;background:rgba(79,70,229,.1);color:var(--primary);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
.ph-title{margin:0;font-size:1.55rem;font-weight:800;color:var(--text-main);letter-spacing:-.3px}
.ph-sub{margin:3px 0 0;font-size:.85rem;color:var(--text-sub)}
.btn-back{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text-sub);font-size:.84rem;font-weight:600;text-decoration:none;transition:.18s}
.btn-back:hover{border-color:var(--primary);color:var(--primary);background:rgba(79,70,229,.05)}
.sum-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.sum-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:560px){.sum-grid{grid-template-columns:repeat(2,1fr)}}
.sum-card{background:var(--bg-body);border:2px solid transparent;border-radius:14px;padding:18px 14px;text-align:center;text-decoration:none;display:block;transition:.18s;cursor:pointer}
.sum-card:hover{border-color:var(--primary);transform:translateY(-2px);box-shadow:0 6px 20px rgba(79,70,229,.12)}
.sum-card.active{border-color:var(--primary);background:rgba(79,70,229,.07)}
.sum-num{font-size:2rem;font-weight:800;color:var(--primary);line-height:1.1}
.sum-lbl{font-size:.73rem;color:var(--text-sub);margin-top:5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:flex;align-items:center;justify-content:center;gap:5px}
.filter-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:var(--bg-body);border-radius:12px;padding:16px 18px;margin-bottom:22px;border:1px solid var(--border)}
.filter-bar select,.filter-bar input{padding:8px 12px;border-radius:9px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-main);font-size:.84rem;font-weight:600;transition:border-color .15s}
.filter-bar select:focus,.filter-bar input:focus{border-color:var(--primary);outline:none}
.filter-bar input{min-width:180px;flex:1}
.btn-go{padding:8px 18px;border-radius:9px;background:var(--primary);color:#fff;border:none;font-weight:700;font-size:.84rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:opacity .18s}
.btn-go:hover{opacity:.86}
.btn-reset{padding:8px 13px;border-radius:9px;border:none;background:var(--border);color:var(--text-main);font-weight:600;font-size:.84rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:background .15s}
.btn-reset:hover{background:rgba(79,70,229,.12)}
.result-badge{margin-left:auto;font-size:.8rem;color:var(--text-sub);font-weight:600;background:var(--bg-card);padding:6px 13px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;gap:6px;white-space:nowrap}
.tl-group{margin-bottom:28px}
.tl-label{font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--text-sub);display:flex;align-items:center;gap:10px;margin-bottom:14px}
.tl-label::after{content:'';flex:1;height:1px;background:var(--border)}
.tl-list{display:flex;flex-direction:column;gap:10px;padding-left:28px;position:relative}
.tl-list::before{content:'';position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--border);border-radius:2px}
.tl-item{position:relative}
.tl-dot{position:absolute;left:-24px;top:18px;width:16px;height:16px;border-radius:50%;border:3px solid var(--bg-card);z-index:1}
.hc{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .18s,border-color .18s}
.hc:hover{box-shadow:0 6px 24px rgba(79,70,229,.09);border-color:rgba(79,70,229,.22)}
.hc-top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:14px 18px}
.hc-left{display:flex;align-items:center;gap:12px}
.hc-sysicon{width:36px;height:36px;border-radius:10px;background:rgba(79,70,229,.08);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.hc-name{font-weight:700;font-size:.95rem;color:var(--text-main)}
.hc-period{font-size:.76rem;color:var(--text-sub);margin-top:2px}
.hc-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.badge-type{padding:4px 12px;border-radius:50px;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.hc-meta{display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--text-sub);background:var(--bg-body);padding:8px 18px;border-top:1px solid var(--border)}
.sep{opacity:.35}
.hc-body{padding:13px 18px;border-top:1px solid var(--border)}
.comment-box{background:rgba(79,70,229,.04);border-left:3px solid var(--primary);padding:11px 14px;border-radius:6px;font-size:.87rem;line-height:1.75;color:var(--text-main);white-space:pre-wrap;word-break:break-word}
.no-comment{font-size:.81rem;color:var(--text-sub);font-style:italic;display:flex;align-items:center;gap:6px}
.btn-del{background:none;border:1px solid #fca5a5;color:#ef4444;padding:5px 11px;border-radius:8px;font-size:.73rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.15s}
.btn-del:hover{background:#fee2e2;border-color:#ef4444}
.empty{text-align:center;padding:70px 20px;color:var(--text-sub)}
.empty i{font-size:3.5rem;opacity:.18;margin-bottom:16px;display:block}
.empty p{font-size:1rem;margin:0 0 6px;font-weight:600;color:var(--text-main)}
.empty small{font-size:.84rem}
.overlay-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(3px)}
.modal-box{background:var(--bg-card);border-radius:16px;padding:30px 28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);border:1px solid var(--border);text-align:center}
.modal-icon{font-size:2.5rem;color:#ef4444;margin-bottom:14px}
.modal-title{font-size:1.2rem;font-weight:800;color:var(--text-main);margin:0 0 8px}
.modal-sub{font-size:.87rem;color:var(--text-sub);margin:0 0 24px;line-height:1.65}
.modal-btns{display:flex;gap:10px;justify-content:center}
.mbtn{padding:10px 24px;border-radius:10px;border:none;font-weight:700;font-size:.9rem;cursor:pointer;transition:.16s}
.mbtn-cancel{background:var(--border);color:var(--text-main)}
.mbtn-cancel:hover{background:rgba(79,70,229,.1)}
.mbtn-delete{background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff}
.mbtn-delete:hover{opacity:.88;transform:translateY(-1px)}
.toast{position:fixed;bottom:26px;right:26px;background:#1e1e1e;color:#fff;padding:12px 20px;border-radius:12px;font-size:.87rem;font-weight:600;display:flex;align-items:center;gap:9px;box-shadow:0 8px 30px rgba(0,0,0,.3);transform:translateY(80px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:10000;pointer-events:none}
.toast.show{transform:translateY(0);opacity:1}
.toast.t-ok{border-left:4px solid #10b981}
.toast.t-err{border-left:4px solid #ef4444}
</style>
</head>
<body>
<?php $path = $base_path; include $base_path.'components/header_nav.php'; ?>

<div class="main">
<div class="page-wrap">

<div class="card" style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:28px 26px;margin-bottom:30px;">

    <!-- Page header -->
    <div class="ph">
        <div class="ph-left">
            <div class="ph-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div>
                <h2 class="ph-title">Comment History</h2>
                <p class="ph-sub">ประวัติ Note / Comment ทั้งหมดของระบบ Workflow</p>
            </div>
        </div>
        <a href="workflow_mgmt.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> กลับหน้า Workflow</a>
    </div>

    <!-- Summary cards -->
    <div class="sum-grid">
        <?php
        $cards = [
            ['type'=>'all',            'label'=>'ทั้งหมด',        'icon'=>'fa-list',        'n'=>$total],
            ['type'=>'submit',         'label'=>'Submit',          'icon'=>'fa-paper-plane', 'n'=>$tc['submit']],
            ['type'=>'approve',        'label'=>'Approve',         'icon'=>'fa-stamp',       'n'=>$tc['approve']],
            ['type'=>'cancel_submit',  'label'=>'ยกเลิก Submit',   'icon'=>'fa-rotate-left', 'n'=>$tc['cancel_submit']],
            ['type'=>'cancel_approve', 'label'=>'ยกเลิก Approve',  'icon'=>'fa-ban',         'n'=>$tc['cancel_approve']],
        ];
        foreach ($cards as $c):
            $active = ($filter_type === $c['type']) ? 'active' : '';
            $qs = http_build_query(array_merge($_GET, ['type'=>$c['type']]));
        ?>
        <a href="?<?= $qs ?>" class="sum-card <?= $active ?>">
            <div class="sum-num"><?= $c['n'] ?></div>
            <div class="sum-lbl"><i class="fa-solid <?= $c['icon'] ?>"></i><?= $c['label'] ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
        <input type="text" name="search" placeholder="🔍 ค้นหาชื่อ / comment / ผู้ทำ…"
               value="<?= htmlspecialchars($search) ?>">
        <select name="table">
            <option value="all" <?= $filter_table==='all'?'selected':'' ?>>🗂 ทุกระบบ</option>
            <?php foreach ($log_sources as $tbl => $info): ?>
            <option value="<?= $tbl ?>" <?= $filter_table===$tbl?'selected':'' ?>><?= $info['name'] ?></option>
            <?php endforeach; ?>
        </select>
        <select name="month">
            <option value="0" <?= $filter_month===0?'selected':'' ?>>📅 ทุกเดือน</option>
            <?php foreach ($month_names as $n => $m): ?>
            <option value="<?= $n ?>" <?= $filter_month===$n?'selected':'' ?>><?= $m ?></option>
            <?php endforeach; ?>
        </select>
        <select name="year">
            <option value="0" <?= $filter_year===0?'selected':'' ?>>📆 ทุกปี</option>
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $filter_year===$y?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-go"><i class="fa-solid fa-magnifying-glass"></i> กรอง</button>
        <a href="comment_history.php" class="btn-reset"><i class="fa-solid fa-xmark"></i> ล้าง</a>
        <span class="result-badge">
            <i class="fa-solid fa-layer-group" style="opacity:.6;"></i>
            <span id="resultCount"><?= count($history) ?></span> รายการ
        </span>
    </form>

    <!-- Timeline -->
    <div id="tlWrap">
    <?php if (empty($history)): ?>
        <div class="empty">
            <i class="fa-solid fa-comment-slash"></i>
            <p>ไม่พบรายการ Comment / Note</p>
            <small>ลองเปลี่ยน filter หรือรอให้มีการ Submit / Approve</small>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $period => $rows): ?>
        <div class="tl-group">
            <div class="tl-label">
                <i class="fa-regular fa-calendar" style="color:var(--primary);"></i><?= $period ?>
            </div>
            <div class="tl-list">
                <?php foreach ($rows as $h):
                    $tm   = $type_meta[$h['action_type']]
                          ?? ['label'=>$h['action_type'],'icon'=>'fa-circle','color'=>'#6b7280','bg'=>'#f3f4f6','text'=>'#374151'];
                    $src  = $log_sources[$h['log_table']]
                          ?? ['name'=>($h['log_name']?:$h['log_table']),'icon'=>'fa-file'];
                    $name = htmlspecialchars($h['log_name'] ?: $src['name']);
                    $dt   = date('d M Y  H:i', strtotime($h['done_at']));
                    $hasComment = !empty(trim($h['comment_text'] ?? ''));
                ?>
                <div class="tl-item" id="item-<?= $h['id'] ?>">
                    <div class="tl-dot" style="background:<?= $tm['color'] ?>;"></div>
                    <div class="hc">
                        <div class="hc-top">
                            <div class="hc-left">
                                <div class="hc-sysicon"><i class="fa-solid <?= $src['icon'] ?>"></i></div>
                                <div>
                                    <div class="hc-name"><?= $name ?></div>
                                    <div class="hc-period">
                                        <?= ($month_names[(int)$h['month']] ?? $h['month']).' '.$h['year'] ?>
                                    </div>
                                </div>
                            </div>
                            <div class="hc-right">
                                <span class="badge-type"
                                      style="background:<?= $tm['bg'] ?>;color:<?= $tm['text'] ?>;">
                                    <i class="fa-solid <?= $tm['icon'] ?>"></i><?= $tm['label'] ?>
                                </span>
                                <?php if ($is_admin): ?>
                                <button class="btn-del"
                                        onclick="askDelete(<?= (int)$h['id'] ?>, '<?= addslashes($name) ?>')">
                                    <i class="fa-solid fa-trash-can"></i> ลบ
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="hc-meta">
                            <i class="fa-solid fa-user"></i>
                            <span><?= htmlspecialchars($h['done_by'] ?? '–') ?></span>
                            <span class="sep">|</span>
                            <i class="fa-regular fa-clock"></i>
                            <span><?= $dt ?></span>
                        </div>
                        <div class="hc-body">
                            <?php if ($hasComment): ?>
                                <div class="comment-box"><?= htmlspecialchars($h['comment_text']) ?></div>
                            <?php else: ?>
                                <div class="no-comment">
                                    <i class="fa-regular fa-comment"></i> ไม่มี comment / note
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

</div>
<div style="height:40px;"></div>
</div>
</div>

<!-- Confirm Delete Modal -->
<div class="overlay-modal" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="modal-title">ยืนยันการลบ</h3>
        <p class="modal-sub" id="modalSub">คุณต้องการลบ comment นี้ใช่ไหม?</p>
        <div class="modal-btns">
            <button class="mbtn mbtn-cancel" onclick="closeModal()">
                <i class="fa-solid fa-xmark"></i> ยกเลิก
            </button>
            <button class="mbtn mbtn-delete" id="confirmBtn">
                <i class="fa-solid fa-trash-can"></i> ลบเลย
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
if (localStorage.getItem('theme') === 'dark')
    document.documentElement.setAttribute('data-theme', 'dark');

let _pendingId = null;

function askDelete(id, name) {
    _pendingId = id;
    document.getElementById('modalSub').innerHTML =
        'ต้องการลบ comment ของ <strong>' + name + '</strong> ใช่ไหม?<br>' +
        '<span style="color:#ef4444;font-size:.81rem;">ข้อมูลจะหายถาวร ไม่สามารถกู้คืนได้</span>';
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
    _pendingId = null;
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.getElementById('confirmBtn').addEventListener('click', function () {
    if (!_pendingId) return;
    var deleteId = _pendingId;
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังลบ…';

    var fd = new FormData();
    fd.append('delete_id', deleteId);

    fetch('delete_comment.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            closeModal();
            if (data.success) {
                var el = document.getElementById('item-' + deleteId);
                if (el) {
                    el.style.transition = 'all .35s ease';
                    el.style.opacity    = '0';
                    el.style.transform  = 'translateX(30px)';
                    setTimeout(function() { el.remove(); syncCount(); pruneEmpty(); }, 360);
                }
                showToast('✅ ลบ comment เรียบร้อยแล้ว', 'ok');
            } else {
                showToast('❌ ' + (data.error || 'เกิดข้อผิดพลาด'), 'err');
            }
        })
        .catch(function() {
            closeModal();
            showToast('❌ ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'err');
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> ลบเลย';
        });
});

function syncCount() {
    var n = document.querySelectorAll('.tl-item').length;
    document.getElementById('resultCount').textContent = n;
}

function pruneEmpty() {
    document.querySelectorAll('.tl-group').forEach(function(g) {
        if (!g.querySelector('.tl-item')) g.remove();
    });
    if (!document.querySelector('.tl-item')) {
        document.getElementById('tlWrap').innerHTML =
            '<div class="empty"><i class="fa-solid fa-comment-slash"></i>' +
            '<p>ไม่มีรายการเหลืออยู่</p><small>ลบทุกรายการออกหมดแล้ว</small></div>';
    }
}

function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.className = 'toast t-' + type + ' show';
    t.innerHTML = msg;
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.classList.remove('show'); }, 3200);
}
</script>
</body>
</html>
