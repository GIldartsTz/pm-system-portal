<?php
session_start();
$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: ../login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();
if (!isset($_SESSION['user_id'])) { header("Location: ../login/login.php"); exit(); }

// ✅ CONFIG
$current_page = 'server';
$path = '../'; 

include '../db.php'; 
// Config
$TABLE_NAME = 'server_logs'; 
$PAGE_TITLE = 'Server Logs'; 
$ICON_CLASS = 'fa-server'; 
$THEME = '#10b981';

$task_headers = [];
$q_task = $conn->query("SELECT * FROM master_tasks WHERE system_type='server' ORDER BY id ASC");
if($q_task && $q_task->num_rows > 0){
    while($row = $q_task->fetch_assoc()){ $task_headers[$row['column_name']] = $row['task_label'] . "<br><small>(" . $row['frequency'] . ")</small>"; }
} else { $task_headers = ["task_1"=>"Check temperature (Default)"]; }

$cur_m = isset($_GET['month'])?(int)$_GET['month']:(int)date('m'); 
$cur_y = isset($_GET['year'])?(int)$_GET['year']:(int)date('Y');
$years = range(2026, 2030); 
$month_names = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"June", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];

// Sync Check
$master_eq = $conn->query("SELECT equipment_name FROM master_equipment WHERE system_type='server' ORDER BY id ASC");
if($master_eq){ while($me = $master_eq->fetch_assoc()){
    $ename = mysqli_real_escape_string($conn, $me['equipment_name']);
    $check = $conn->query("SELECT id FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y AND equipment_name='$ename'");
    if($check->num_rows == 0) $conn->query("INSERT INTO $TABLE_NAME (equipment_name, month, year) VALUES ('$ename', $cur_m, $cur_y)");
}}

// 🔥🔥🔥 แก้ตรงนี้ครับ: เพิ่ม ORDER BY id ASC ให้เรียงตามลำดับที่ Insert 🔥🔥🔥
$result = $conn->query("SELECT * FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y ORDER BY id ASC"); 

$grand_total=0; $table_data=[];
if($res=$result){ while($r=$res->fetch_assoc()){ $table_data[]=$r; foreach($task_headers as $k=>$v) if(isset($r[$k])&&$r[$k]=='1') $grand_total++; }}
$q_time = $conn->query("SELECT MAX(last_updated) as latest FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y");
$r_time = $q_time->fetch_assoc(); $global_last_update = $r_time['latest'] ? date('d M Y, H:i', strtotime($r_time['latest'])) : '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Logs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../css/layout.css">  
    <link rel="stylesheet" href="css/server.css">     
    <link rel="stylesheet" href="../css/theme.css">
</head>
<body> 
    <?php include '../components/header_nav.php'; ?>

    <main class="main">
        <div class="controls-bar">
            <div class="page-head"><h1><?=$PAGE_TITLE?></h1><small>Updated: <span id="lastUpd"><?=$global_last_update?></span></small></div>
            <div class="filters">
                <div class="stat-box"><span class="lbl">Total</span><span class="val" style="color:var(--primary)" id="grandTotal"><?=number_format($grand_total)?></span></div>
                <form method="GET" class="picker"><i class="fa-regular fa-calendar" style="color:var(--text-sub)"></i><select name="month" onchange="this.form.submit()"><?php foreach($month_names as $n=>$m) echo "<option value='$n' ".($n==$cur_m?'selected':'').">$m</option>"; ?></select><select name="year" onchange="this.form.submit()"><?php foreach($years as $y) echo "<option value='$y' ".($y==$cur_y?'selected':'').">$y</option>"; ?></select></form>
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
                                <th>Equipment Name</th>
                                <?php foreach($task_headers as $k=>$t): 
                                    $is_due = false;
                                    if (strpos($t, '(M)') !== false) $is_due = true;
                                    elseif (strpos($t, '(3M)') !== false && in_array($cur_m, [3,6,9,12])) $is_due = true;
                                    elseif (strpos($t, '(6M)') !== false && in_array($cur_m, [6,12])) $is_due = true;
                                    elseif (strpos($t, '(Y)') !== false && $cur_m == 12) $is_due = true;
                                    $th_class = $is_due ? 'th-due' : '';
                                ?>
                                    <th class="<?=$th_class?>" ondblclick="fillCol('<?=$k?>')" style="cursor:pointer;min-width:120px"><?=$t?></th>
                                <?php endforeach; ?>
                                <th style="min-width:60px">Sum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($table_data)): foreach($table_data as $row): 
                                $name=$row['equipment_name']; $sum=0; 
                                foreach($task_headers as $k=>$v) if(isset($row[$k]) && $row[$k]=='1') $sum++; 
                            ?>
                            <tr>
                                <td><?=$name?></td>
                                
                                <?php foreach($task_headers as $k=>$v): 
                                    $val = isset($row[$k]) ? $row[$k] : null;
                                    $cls = ($val=='1')?'st-ok':(($val=='0')?'st-fail':'st-null');
                                    $icon = ($val=='1')?'<i class="fa-solid fa-check"></i>':(($val=='0')?'<i class="fa-solid fa-xmark"></i>':'');
                                ?>
                                    <td class="c-wrap" data-sys="<?=$name?>" data-col="<?=$k?>" data-val="<?=$val?>"><div class="cell-btn <?=$cls?>"><?=$icon?></div></td>
                                <?php endforeach; ?>
                                <td class="row-sum" style="font-weight:700;color:var(--primary)"><?=$sum?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
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
    <script src="../Server_log/js/server.js"></script>
</html>