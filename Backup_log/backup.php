<?php
session_start();
$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: ../login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();
if (!isset($_SESSION['user_id'])) { header("Location: ../login/login.php"); exit(); }

// ✅ CONFIG
$current_page = 'backup';
$path = '../'; // 🔥 ต้องมี ../ เพราะอยู่ในโฟลเดอร์ย่อย Backup_log

// 🔥 แก้ Path: ถอยหลัง 1 ขั้นไปหา db.php
include '../db.php'; 

// Config
$TABLE_NAME = 'backup_logs'; 
$PAGE_TITLE = 'Backup Logs'; 
$THEME = '#3b82f6'; 
$cur_m = isset($_GET['month'])?(int)$_GET['month']:(int)date('m'); 
$cur_y = isset($_GET['year'])?(int)$_GET['year']:(int)date('Y');
$years = range(2026, 2030); 
$month_names = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"June", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $cur_m, $cur_y); 

// --- Holiday Logic ---
$holiday_definitions = [
    '01-01' => 'New Year Day',
    '04-13' => 'Songkran Day',
    '04-14' => 'Songkran Day',
    '04-15' => 'Songkran Day',
    '05-01' => 'Labor Day',
    '07-28' => 'King Birthday',
    '08-12' => 'Mother Day',
    '10-13' => 'King Rama 9 Memorial',
    '10-23' => 'Chulalongkorn Day',
    '12-05' => 'Father Day',
    '12-10' => 'Constitution Day',
    '12-31' => 'New Year Eve'
];

$fixed_holidays = array_keys($holiday_definitions); 
$current_month_holidays = [];

foreach ($holiday_definitions as $date => $name) { 
    list($m, $d) = explode('-', $date); 
    if ((int)$m == $cur_m) $current_month_holidays[(int)$d] = $name; 
}

function getDayType($d, $m, $y) {
    global $fixed_holidays;
    $date_str = sprintf('%04d-%02d-%02d', $y, $m, $d);
    $md_str = sprintf('%02d-%02d', $m, $d);
    
    if (in_array($md_str, $fixed_holidays)) return 'public';
    $dw = date('N', strtotime($date_str)); 
    if ($dw >= 6) return 'weekend';
    
    return 'normal';
}

$day_types = []; 
for($d=1; $d<=$days_in_month; $d++) $day_types[$d] = getDayType($d, $cur_m, $cur_y);

// Sync & Fetch
$master_eq = $conn->query("SELECT equipment_name FROM master_equipment WHERE system_type='backup' ORDER BY id ASC");
if($master_eq){ while($me = $master_eq->fetch_assoc()){
    $ename = mysqli_real_escape_string($conn, $me['equipment_name']);
    $check = $conn->query("SELECT id FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y AND equipment_name='$ename'");
    if($check->num_rows == 0) $conn->query("INSERT INTO $TABLE_NAME (equipment_name, month, year) VALUES ('$ename', $cur_m, $cur_y)");
}}

$result = $conn->query("SELECT * FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y ORDER BY id ASC");
$grand_total=0; $table_data=[];
if($res=$result){ while($r=$res->fetch_assoc()){ 
    $table_data[]=$r; 
    for($i=1; $i<=$days_in_month; $i++) { if(isset($r["day_$i"]) && $r["day_$i"]=='1') $grand_total++; }
    if(isset($r['check_backup_6m']) && $r['check_backup_6m']=='1') $grand_total++; 
    if(isset($r['check_recovery_6m']) && $r['check_recovery_6m']=='1') $grand_total++; 
}}
$q_time = $conn->query("SELECT MAX(last_updated) as latest FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y"); 
$r_time = $q_time->fetch_assoc(); $global_last_update = $r_time['latest'] ? date('d M Y, H:i', strtotime($r_time['latest'])) : '-';
$is_due_6m = in_array($cur_m, [1, 7]); 
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
    <link rel="stylesheet" href="../Backup_log/css/backup.css">
    <link rel="stylesheet" href="../css/theme.css">
    
</head>
<body> 
    <?php include '../components/header_nav.php'; ?>

    <main class="main">
        <div class="controls-bar">
            <div class="page-head"><h1><?=$PAGE_TITLE?></h1><small>Updated: <span id="lastUpd"><?=$global_last_update?></span></small></div>
            <div class="filters">
                <div class="stat-box"><span class="lbl">Total</span><span class="val" id="grandTotal"><?=number_format($grand_total)?></span></div>
                <form method="GET" class="picker">
                    <i class="fa-regular fa-calendar" style="color:var(--text-sub)"></i>
                    <select name="month" onchange="this.form.submit()"><?php foreach($month_names as $n=>$m) echo "<option value='$n' ".($n==$cur_m?'selected':'').">$m</option>"; ?></select>
                    <select name="year" onchange="this.form.submit()"><?php foreach($years as $y) echo "<option value='$y' ".($y==$cur_y?'selected':'').">$y</option>"; ?></select>
                </form>
                <div class="actions">
                    <button class="active-view" onclick="setTool('safe',this)" title="View Mode"><i class="fa-solid fa-arrow-pointer"></i></button>
                    <button onclick="setTool(1,this)" title="Mark OK"><i class="fa-solid fa-check"></i></button>
                    <button onclick="setTool(0,this)" title="Mark Fail"><i class="fa-solid fa-xmark"></i></button>
                    <button onclick="setTool(null,this)" title="Clear (Eraser)"><i class="fa-solid fa-eraser"></i></button>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <div class="table-card">
                <div class="scroll-area" id="scrollBox">
                    <table id="tbl">
                        <thead>
                            <tr>
                                <th class="sticky-col" rowspan="2">Equipment Name</th>
                                <th class="th-group" colspan="<?=$days_in_month?>">Check Status of data back up (D)</th>
                                <th rowspan="2" class="<?=$is_due_6m?'th-highlight':''?>" style="min-width:90px; vertical-align:middle; z-index:60;" ondblclick="fillCol('check_backup_6m')">Backup<br><small>(6M)</small></th>
                                <th rowspan="2" class="<?=$is_due_6m?'th-highlight':''?>" style="min-width:90px; vertical-align:middle; z-index:60;" ondblclick="fillCol('check_recovery_6m')">Restore<br><small>(6M)</small></th>
                                <th rowspan="2" style="width:60px; z-index:60;">Sum</th>
                            </tr>
                            <tr>
                                <?php for($i=1; $i<=$days_in_month; $i++): $type = $day_types[$i]; $th_cls = ($type == 'public') ? 'bg-public' : (($type == 'weekend') ? 'bg-weekend' : ''); ?>
                                    <th class="<?=$th_cls?>" style="min-width:34px; font-weight:400;" ondblclick="fillCol('day_<?=$i?>')"><?=$i?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($table_data)): foreach($table_data as $row): $name=$row['equipment_name']; $sum=0; for($i=1;$i<=$days_in_month;$i++) if(isset($row["day_$i"])&&$row["day_$i"]=='1') $sum++; if(isset($row['check_backup_6m'])&&$row['check_backup_6m']=='1') $sum++; if(isset($row['check_recovery_6m'])&&$row['check_recovery_6m']=='1') $sum++; ?>
                            <tr>
                                <td class="sticky-col"><?=$name?></td>
                                <?php for($i=1;$i<=$days_in_month;$i++): 
                                    $val = isset($row["day_$i"]) ? $row["day_$i"] : null; 
                                    $cls = ($val=='1')?'st-ok':(($val=='0')?'st-fail':'st-null'); 
                                    $icon = ($val=='1')?'<i class="fa-solid fa-check"></i>':(($val=='0')?'<i class="fa-solid fa-xmark"></i>':''); 
                                    // 🔥 ลบ logic ใส่สีออกจากตรงนี้แล้ว
                                ?>
                                    <td class="c-wrap" data-sys="<?=$name?>" data-col="day_<?=$i?>" data-val="<?=$val?>"><div class="cell-btn <?=$cls?>"><?=$icon?></div></td>
                                <?php endfor; ?>
                                <td class="c-wrap" data-sys="<?=$name?>" data-col="check_backup_6m" data-val="<?=isset($row['check_backup_6m'])?$row['check_backup_6m']:null?>"><div class="cell-btn <?=(isset($row['check_backup_6m'])&&$row['check_backup_6m']=='1')?'st-ok':((isset($row['check_backup_6m'])&&$row['check_backup_6m']=='0')?'st-fail':'st-null')?>"><?=(isset($row['check_backup_6m'])&&$row['check_backup_6m']=='1')?'<i class="fa-solid fa-check"></i>':((isset($row['check_backup_6m'])&&$row['check_backup_6m']=='0')?'<i class="fa-solid fa-xmark"></i>':'')?></div></td>
                                <td class="c-wrap" data-sys="<?=$name?>" data-col="check_recovery_6m" data-val="<?=isset($row['check_recovery_6m'])?$row['check_recovery_6m']:null?>"><div class="cell-btn <?=(isset($row['check_recovery_6m'])&&$row['check_recovery_6m']=='1')?'st-ok':((isset($row['check_recovery_6m'])&&$row['check_recovery_6m']=='0')?'st-fail':'st-null')?>"><?=(isset($row['check_recovery_6m'])&&$row['check_recovery_6m']=='1')?'<i class="fa-solid fa-check"></i>':((isset($row['check_recovery_6m'])&&$row['check_recovery_6m']=='0')?'<i class="fa-solid fa-xmark"></i>':'')?></div></td>
                                <td class="row-sum" style="font-weight:700;color:var(--primary)"><?=$sum?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="legend-bar">
                    <div style="font-weight:600; margin-right:10px;">Note:</div>
                    <div class="legend-item"><div class="color-box box-weekend"></div><span>Sat - Sun</span></div>
                    <?php if(!empty($current_month_holidays)): ?>
                        <?php foreach($current_month_holidays as $d => $name): ?>
                            <div class="legend-item"><div class="color-box box-public"></div><span><?=$d?> <?=date('M', mktime(0,0,0,$cur_m,1))?>: <?=$name?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="legend-item"><div class="color-box box-public"></div><span>Public Holiday</span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script>
        const APP_CONFIG = {
            tableType: '<?php echo $TABLE_NAME; ?>',
            curM: <?php echo $cur_m; ?>,
            curY: <?php echo $cur_y; ?>
        };
    </script>
    
    <script src="../Backup_log/js/backup.js"></script> 
</body>
</html>