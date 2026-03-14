<?php
session_start();
$timeout = 60 * 60;
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: ../login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();
if (!isset($_SESSION['user_id'])) { header("Location: ../login/login.php"); exit(); }

$current_page = 'hardware';
$path = '../';

include '../db.php';

$TABLE_NAME = 'hardware_logs';
$PAGE_TITLE  = 'Hardware Logs';

// ดึง task headers — ใช้ $task_row เพื่อไม่ทับ $log ใน tbody
$task_headers = [];
$q_task = $conn->query("SELECT * FROM master_tasks WHERE system_type='hardware' ORDER BY CAST(SUBSTRING(column_name, 6) AS UNSIGNED) ASC");
if ($q_task && $q_task->num_rows > 0) {
    while ($task_row = $q_task->fetch_assoc()) {
        $task_headers[$task_row['column_name']] = [
            'label' => $task_row['task_label'] . "<br><small>(" . $task_row['frequency'] . ")</small>",
            'freq'  => $task_row['frequency']
        ];
    }
} else {
    $task_headers['task_1'] = ['label' => 'Task 1<br><small>(Default)</small>', 'freq' => 'M'];
}

$cur_m = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$cur_y = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$years = range(2026, 2030);
$month_names = [1=>"Jan",2=>"Feb",3=>"Mar",4=>"Apr",5=>"May",6=>"June",7=>"Jul",8=>"Aug",9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dec"];

// Sync equipment rows
$master_eq = $conn->query("SELECT equipment_name FROM master_equipment WHERE system_type='hardware'");
if ($master_eq) {
    while ($me = $master_eq->fetch_assoc()) {
        $ename = mysqli_real_escape_string($conn, $me['equipment_name']);
        $chk = $conn->query("SELECT id FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y AND equipment_name='$ename'");
        if ($chk->num_rows == 0) {
            $conn->query("INSERT INTO $TABLE_NAME (equipment_name, month, year) VALUES ('$ename', $cur_m, $cur_y)");
        }
    }
}

// ดึงข้อมูล log
$result = $conn->query("SELECT * FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y ORDER BY equipment_name ASC");
$grand_total = 0;
$table_data  = [];
if ($res = $result) {
    while ($r = $res->fetch_assoc()) {
        $table_data[] = $r;
        foreach ($task_headers as $k => $v) {
            if (isset($r[$k]) && ($r[$k] == '1' || $r[$k] == '0')) $grand_total++;
        }
    }
}
$q_time = $conn->query("SELECT MAX(last_updated) as latest FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y");
$r_time = $q_time->fetch_assoc();
$global_last_update = $r_time['latest'] ? date('d M Y, H:i', strtotime($r_time['latest'])) : '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?=$PAGE_TITLE?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/layout.css">
    <link rel="stylesheet" href="../Hardware_log/css/hardware.css">
    <link rel="stylesheet" href="../css/theme.css">
</head>
<body>
    <?php include '../components/header_nav.php'; ?>

    <main class="main">
        <div class="controls-bar">
            <div class="page-head"><h1><?=$PAGE_TITLE?></h1><small>Updated: <span id="lastUpd"><?=$global_last_update?></span></small></div>
            <div class="filters">
                <div class="stat-box" style="display:none;"><span class="lbl">Total</span><span class="val" style="color:var(--primary)" id="grandTotal"><?=number_format($grand_total)?></span></div>
                <form method="GET" class="picker">
                    <i class="fa-regular fa-calendar" style="color:var(--text-sub)"></i>
                    <select name="month" onchange="this.form.submit()"><?php foreach($month_names as $n=>$mn) echo "<option value='$n' ".($n==$cur_m?'selected':'').">$mn</option>"; ?></select>
                    <select name="year"  onchange="this.form.submit()"><?php foreach($years as $yr) echo "<option value='$yr' ".($yr==$cur_y?'selected':'').">$yr</option>"; ?></select>
                </form>
                <div class="actions">
                    <button class="active-view" onclick="setTool('safe',this)"><i class="fa-solid fa-arrow-pointer"></i></button>
                    <button onclick="setTool(1,this)"><i class="fa-solid fa-check"></i></button>
                    <button onclick="setTool(0,this)"><i class="fa-solid fa-xmark"></i></button>
                    <button onclick="setTool(null,this)"><i class="fa-solid fa-eraser"></i></button>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <div class="table-card">
                <div class="scroll-area" id="scrollBox">
                    <table id="tbl">
                        <thead>
                            <tr>
                                <th class="sticky-col">Equipment Name</th>
                                <?php foreach($task_headers as $k => $t):
                                    $freq = $t['freq'];
                                    $is_due = false;
                                    if ($freq == 'M') $is_due = true;
                                    elseif ($freq == '3M' && in_array($cur_m, [3,6,9,12])) $is_due = true;
                                    elseif ($freq == '6M' && in_array($cur_m, [6,12])) $is_due = true;
                                    elseif ($freq == 'Y' && $cur_m == 12) $is_due = true;
                                ?>
                                    <th class="<?=$is_due?'th-due':''?>" ondblclick="fillCol('<?=$k?>')" style="cursor:pointer; min-width:120px;"><?=$t['label']?></th>
                                <?php endforeach; ?>
                                <th style="min-width:60px;">Sum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($table_data)): foreach($table_data as $log):
                                $name = $log['equipment_name'];
                                $sum  = 0;
                                foreach ($task_headers as $k => $v) {
                                    if (isset($log[$k]) && ($log[$k] == '1' || $log[$k] == '0')) $sum++;
                                }
                            ?>
                            <tr>
                                <td class="sticky-col"><?=$name?></td>
                                <?php foreach($task_headers as $k => $v):
                                    $val  = isset($log[$k]) ? $log[$k] : null;
                                    $cls  = ($val==='1'||$val===1) ? 'st-ok'  : (($val==='0'||$val===0) ? 'st-fail' : 'st-null');
                                    $icon = ($val==='1'||$val===1) ? '<i class="fa-solid fa-check"></i>' : (($val==='0'||$val===0) ? '<i class="fa-solid fa-xmark"></i>' : '');
                                    $dval = ($val === null) ? 'null' : $val;
                                ?>
                                    <td class="c-wrap" data-sys="<?=$name?>" data-col="<?=$k?>" data-val="<?=$dval?>"><div class="cell-btn <?=$cls?>"><?=$icon?></div></td>
                                <?php endforeach; ?>
                                <td class="row-sum" style="font-weight:700;color:var(--primary)"><?=$sum?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="<?=count($task_headers)+2?>" style="text-align:center;padding:30px;color:var(--text-sub)">ไม่พบข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script>
        const APP_CONFIG = {
            tableType: '<?=$TABLE_NAME?>',
            curM: <?=$cur_m?>,
            curY: <?=$cur_y?>,
            updateUrl: '../Hardware_log/update.php'
        };
    </script>
    <script src="../Hardware_log/js/hardware.js"></script>
</body>
</html>
