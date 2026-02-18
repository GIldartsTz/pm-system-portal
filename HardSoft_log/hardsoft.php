<?php
session_start();
$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: ../login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();
if (!isset($_SESSION['user_id'])) { header("Location: ../login/login.php"); exit(); }

// ✅ CONFIG
$current_page = 'hardsoft';
$path = '../'; 

include '../db.php'; 
// Config
$TABLE_NAME = 'hardsoft_logs'; 
$PAGE_TITLE = 'H/W & S/W'; 
$ICON_CLASS = 'fa-microchip'; 
$THEME = '#8b5cf6'; 

// --- ส่วนดึงข้อมูลแบบ Grouped Headers (คงเดิมไว้) ---
$grouped_headers = []; $all_column_keys = [];
$q_task = $conn->query("SELECT * FROM master_tasks WHERE system_type='hardsoft' ORDER BY id ASC");
if($q_task && $q_task->num_rows > 0){
    while($row = $q_task->fetch_assoc()){
        $cat = !empty($row['category']) ? $row['category'] : 'General';
        $grouped_headers[$cat][] = [ 'col' => $row['column_name'], 'label' => $row['task_label'] . "<br><small>(" . $row['frequency'] . ")</small>", 'freq' => $row['frequency'] ];
        $all_column_keys[] = $row['column_name'];
    }
} else { $grouped_headers['General'][] = ['col'=>'task_1', 'label'=>'Clean dust', 'freq'=>'Default']; $all_column_keys[] = 'task_1'; }

$cur_m = isset($_GET['month'])?(int)$_GET['month']:(int)date('m'); 
$cur_y = isset($_GET['year'])?(int)$_GET['year']:(int)date('Y');
$years = range(2026, 2030); 
$month_names = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"June", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];

$master_eq = $conn->query("SELECT equipment_name FROM master_equipment WHERE system_type='hardsoft'");
if($master_eq){ while($me = $master_eq->fetch_assoc()){
    $ename = mysqli_real_escape_string($conn, $me['equipment_name']);
    $check = $conn->query("SELECT id FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y AND equipment_name='$ename'");
    if($check->num_rows == 0) $conn->query("INSERT INTO $TABLE_NAME (equipment_name, month, year) VALUES ('$ename', $cur_m, $cur_y)");
}}

// ✅ เรียง A-Z
$result = $conn->query("SELECT * FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y ORDER BY equipment_name ASC"); 
$grand_total=0; $table_data=[];
if($res=$result){ while($r=$res->fetch_assoc()){ $table_data[]=$r; foreach($all_column_keys as $k) if(isset($r[$k]) && $r[$k]=='1') $grand_total++; }}
$q_time = $conn->query("SELECT MAX(last_updated) as latest FROM $TABLE_NAME WHERE month=$cur_m AND year=$cur_y");
$r_time = $q_time->fetch_assoc(); $global_last_update = $r_time['latest'] ? date('d M Y, H:i', strtotime($r_time['latest'])) : '-';
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
    <link rel="stylesheet" href="../HardSoft/css/hardsoft.css">
    <link rel="stylesheet" href="css/theme.css">
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
                            <tr style="height:40px;">
                                <th class="sticky-col" rowspan="2">Equipment Name</th>
                                <?php foreach($grouped_headers as $cat => $tasks): $col_span = count($tasks); ?>
                                    <th colspan="<?=$col_span?>" class="th-group"><?=$cat?></th>
                                <?php endforeach; ?>
                                <th rowspan="2" style="top:0; z-index:40; min-width:60px;">Sum</th>
                            </tr>
                            <tr>
                                <?php foreach($grouped_headers as $cat => $tasks): foreach($tasks as $t):
                                    $label = $t['label']; $freq = $t['freq'];
                                    $is_due = false;
                                    if (strpos($freq, 'M') !== false && $freq !== '3M' && $freq !== '6M') $is_due = true;
                                    elseif ($freq == '3M' && in_array($cur_m, [3,6,9,12])) $is_due = true;
                                    elseif ($freq == '6M' && in_array($cur_m, [6,12])) $is_due = true;
                                    elseif ($freq == 'Y' && $cur_m == 12) $is_due = true;
                                    $th_class = $is_due ? 'th-due' : '';
                                ?>
                                    <th class="<?=$th_class?>" ondblclick="fillCol('<?=$t['col']?>')" style="cursor:pointer; min-width:120px;"><?=$label?></th>
                                <?php endforeach; endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($table_data)): foreach($table_data as $row): 
                                $name=$row['equipment_name']; $sum=0; 
                                foreach($all_column_keys as $col) if(isset($row[$col]) && $row[$col]=='1') $sum++; 
                            ?>
                            <tr>
                                <td class="sticky-col"><?=$name?></td>
                                <?php foreach($all_column_keys as $col): 
                                    $val = isset($row[$col]) ? $row[$col] : null; 
                                    $cls = ($val=='1')?'st-ok':(($val=='0')?'st-fail':'st-null'); 
                                    $icon = ($val=='1')?'<i class="fa-solid fa-check"></i>':(($val=='0')?'<i class="fa-solid fa-xmark"></i>':''); 
                                ?>
                                    <td class="c-wrap" data-sys="<?=$name?>" data-col="<?=$col?>" data-val="<?=$val?>"><div class="cell-btn <?=$cls?>"><?=$icon?></div></td>
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

    <script src="../HardSoft/js/hardsoft.js"></script>
</body>
</html>