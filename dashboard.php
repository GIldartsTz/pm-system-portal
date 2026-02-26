<?php
session_start();

// --- 1. Session & Auth ---
$timeout = 60 * 60; 
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > $timeout)) {
    session_unset(); session_destroy(); header("Location: login/login.php?timeout=1"); exit();
}
$_SESSION['last_active'] = time();
if (!isset($_SESSION['user_id'])) { header("Location: login/login.php"); exit(); }

// ✅ CONFIG
$current_page = 'dashboard';
$path = ''; 

include 'db.php';

// --- 2. Filter ---
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$years_range = range(2026, 2030); 
$months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

// =================================================================================
// 🔥 DYNAMIC SYSTEM CONFIGURATION
// =================================================================================

// 1. ดึง Tasks จาก DB และแยก Category (Hardware/Software)
$dynamic_tasks = [];
$q_tasks = $conn->query("SELECT * FROM master_tasks ORDER BY system_type, id ASC");
if ($q_tasks) {
    while ($row = $q_tasks->fetch_assoc()) {
        $sys = $row['system_type'];
        $freq = $row['frequency'];
        $col = $row['column_name'];
        
        // ถ้าเป็น hardsoft ให้เช็ค category เพื่อแยก Hardware/Software
        if ($sys == 'hardsoft' && !empty($row['category'])) {
            $sys = strtolower($row['category']); // ผลลัพธ์จะเป็น 'hardware' หรือ 'software'
        }
        $dynamic_tasks[$sys][$freq][] = $col;
    }
}

$systems = [];

// --- 1. BACKUP (Strict Task Count: Plan 2) ---
$systems['backup'] = [
    'label' => 'Backup Logs', 'table' => 'backup_logs',
    'freqs' => [
        'D' => ['label'=>'Daily (D)', 'type'=>'days', 'plan_mode'=>'calc_daily_dynamic', 'cols'=>[]]
    ]
];
if (isset($dynamic_tasks['backup']['6M'])) {
    $systems['backup']['freqs']['6M'] = ['label'=>'Semiannual (6M)', 'due'=>[6, 12], 'plan_mode'=>'strict_task_count', 'cols'=>$dynamic_tasks['backup']['6M']];
} else {
    $systems['backup']['freqs']['6M'] = ['label'=>'Semiannual (6M)', 'due'=>[6, 12], 'plan_mode'=>'strict_task_count', 'cols'=>['check_backup_6m', 'check_recovery_6m']];
}

// --- 2. SERVER (Count All Checks) ---
if (isset($dynamic_tasks['server'])) {
    $systems['server'] = ['label' => 'Server Logs', 'table' => 'server_logs', 'freqs' => []];
    foreach ($dynamic_tasks['server'] as $freq => $cols) {
        $label = ($freq=='M')?'Monthly (M)':(($freq=='3M')?'Quarterly (3M)':(($freq=='6M')?'Semiannual (6M)':'Annual (Y)'));
        $due = ($freq=='M')?range(1,12):(($freq=='3M')?[3,6,9,12]:(($freq=='6M')?[6,12]:[12]));
        $systems['server']['freqs'][$freq] = ['label'=>$label, 'due'=>$due, 'plan_mode'=>'count_all_checks', 'cols'=>$cols];
    }
}

// --- 3. NETWORK (Count All Checks) ---
if (isset($dynamic_tasks['network'])) {
    $systems['network'] = ['label' => 'Network Logs', 'table' => 'network_logs', 'freqs' => []];
    foreach ($dynamic_tasks['network'] as $freq => $cols) {
        $label = ($freq=='M')?'Monthly (M)':(($freq=='3M')?'Quarterly (3M)':(($freq=='6M')?'Semiannual (6M)':'Annual (Y)'));
        $due = ($freq=='M')?range(1,12):(($freq=='3M')?[3,6,9,12]:(($freq=='6M')?[6,12]:[12]));
        $systems['network']['freqs'][$freq] = ['label'=>$label, 'due'=>$due, 'plan_mode'=>'count_all_checks', 'cols'=>$cols];
    }
}

// --- 4. SOFTWARE MAINTENANCE ---
if (isset($dynamic_tasks['software'])) {
    $systems['software'] = ['label' => 'Software Logs', 'table' => 'hardsoft_logs', 'freqs' => []];
    foreach ($dynamic_tasks['software'] as $freq => $cols) {
        $label = ($freq=='M')?'Monthly (M)':(($freq=='3M')?'Quarterly (3M)':(($freq=='6M')?'Semiannual (6M)':'Annual (Y)'));
        $due = ($freq=='M')?range(1,12):(($freq=='3M')?[3,6,9,12]:(($freq=='6M')?[6,12]:[12]));
        $systems['software']['freqs'][$freq] = ['label'=>$label, 'due'=>$due, 'plan_mode'=>'count_all_checks', 'cols'=>$cols];
    }
}

// --- 5. HARDWARE MAINTENANCE ---
if (isset($dynamic_tasks['hardware'])) {
    $systems['hardware'] = ['label' => 'Hardware Logs', 'table' => 'hardsoft_logs', 'freqs' => []];
    foreach ($dynamic_tasks['hardware'] as $freq => $cols) {
        $label = ($freq=='M')?'Monthly (M)':(($freq=='3M')?'Quarterly (3M)':(($freq=='6M')?'Semiannual (6M)':'Annual (Y)'));
        $due = ($freq=='M')?range(1,12):(($freq=='3M')?[3,6,9,12]:(($freq=='6M')?[6,12]:[12]));
        $systems['hardware']['freqs'][$freq] = ['label'=>$label, 'due'=>$due, 'plan_mode'=>'count_all_checks', 'cols'=>$cols];
    }
}

// --- 6. General HardSoft (Fallback) ---
if (isset($dynamic_tasks['hardsoft'])) {
    $systems['hardsoft'] = ['label' => 'H/W & S/W (General)', 'table' => 'hardsoft_logs', 'freqs' => []];
    foreach ($dynamic_tasks['hardsoft'] as $freq => $cols) {
        $label = ($freq=='M')?'Monthly (M)':(($freq=='3M')?'Quarterly (3M)':(($freq=='6M')?'Semiannual (6M)':'Annual (Y)'));
        $due = ($freq=='M')?range(1,12):(($freq=='3M')?[3,6,9,12]:(($freq=='6M')?[6,12]:[12]));
        $systems['hardsoft']['freqs'][$freq] = ['label'=>$label, 'due'=>$due, 'plan_mode'=>'count_all_checks', 'cols'=>$cols];
    }
}

// --- Data Processing (คำนวณกราฟ) ---
$dashboard_data = []; // ✅ ตัวแปรเจ้าปัญหา ถูกประกาศตรงนี้ครับ
foreach ($systems as $sys_key => $sys_conf) {
    $logs = [];
    $table = $sys_conf['table'];
    
    $q_logs = $conn->query("SELECT * FROM $table WHERE year = $selected_year");
    if($q_logs){ while ($r = $q_logs->fetch_assoc()) { $m = (int)$r['month']; $logs[$m][] = $r; } }

    foreach ($sys_conf['freqs'] as $freq_key => $freq_conf) {
        $row_data = ['Plan'=>[], 'Actual'=>[], 'Percent'=>[], 'Healthy'=>[], 'NotHealthy'=>[]];
        for ($m = 1; $m <= 12; $m++) {
            $is_due = false;
            if (isset($freq_conf['type']) && $freq_conf['type'] == 'days') $is_due = true;
            elseif (isset($freq_conf['due']) && in_array($m, $freq_conf['due'])) $is_due = true;

            if (!$is_due) { foreach($row_data as $k=>$v) $row_data[$k][$m] = '-'; continue; }

            $plan = 0; $actual = 0; $healthy = 0; $not_healthy = 0;

            if (isset($freq_conf['plan_mode'])) {
                $cols = isset($freq_conf['cols']) ? $freq_conf['cols'] : [];
                $row_count = isset($logs[$m]) ? count($logs[$m]) : 0;

                if ($freq_conf['plan_mode'] == 'strict_task_count') {
                    $plan = count($cols); 
                    if ($row_count > 0) {
                        foreach ($cols as $col) {
                            $checked_count = 0; $pass_count = 0; $fail_count = 0;
                            foreach ($logs[$m] as $row) {
                                if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                                    $checked_count++;
                                    if ($row[$col] == '1') $pass_count++; elseif ($row[$col] == '0') $fail_count++;
                                }
                            }
                            if ($checked_count == $row_count) {
                                $actual++;
                                if ($fail_count > 0) $not_healthy++; else $healthy++;
                            }
                        }
                    }
                } 
                elseif ($freq_conf['plan_mode'] == 'count_all_checks') {
                    $plan = $row_count * count($cols);
                    if (isset($logs[$m])) {
                        foreach ($logs[$m] as $row) {
                            foreach ($cols as $col) {
                                if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                                    $actual++;
                                    if ($row[$col] == '1') $healthy++; elseif ($row[$col] == '0') $not_healthy++;
                                }
                            }
                        }
                    }
                }
                elseif ($freq_conf['plan_mode'] == 'calc_daily_dynamic') {
                    $days_in_m = cal_days_in_month(CAL_GREGORIAN, $m, $selected_year);
                    $plan = $row_count * $days_in_m;
                    if (isset($logs[$m])) {
                        foreach ($logs[$m] as $log_row) {
                            for($d=1; $d<=$days_in_m; $d++) {
                                $col = "day_$d";
                                if (isset($log_row[$col]) && $log_row[$col] !== null && $log_row[$col] !== '') {
                                    $actual++;
                                    if ($log_row[$col] == '1') $healthy++; elseif ($log_row[$col] == '0') $not_healthy++;
                                }
                            }
                        }
                    }
                }
            }
            
            $row_data['Plan'][$m] = $plan;
            $row_data['Actual'][$m] = $actual;
            $row_data['Healthy'][$m] = $healthy;
            $row_data['NotHealthy'][$m] = $not_healthy;
            $row_data['Percent'][$m] = ($plan > 0) ? round(($actual / $plan) * 100) : 0;
        }
        $dashboard_data[] = ['sys_key' => $sys_key, 'sys_label' => $sys_conf['label'], 'freq_key' => $freq_key, 'freq_label' => $freq_conf['label'], 'data' => $row_data];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/theme.css">
</head>
<body> 
    <?php include 'components/header_nav.php'; ?>

    <div class="main">
        <div class="content">
            <div class="page-header">
                <div><h1>Performance Dashboard</h1><small style="color:var(--text-sub)">Overview report for year <?=$selected_year?></small></div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <form method="GET"><select name="year" class="filter-select" onchange="this.form.submit()"><?php foreach($years_range as $y) echo "<option value='$y' ".($y==$selected_year?'selected':'').">$y</option>"; ?></select></form>
                    <div class="filter-bar">
                        <select id="filterSys" class="filter-select" onchange="applyFilter()">
                            <option value="all">All Systems</option>
                            <?php foreach($systems as $k=>$v): ?>
                                <option value="<?=$k?>"><?=$v['label']?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filterFreq" class="filter-select" onchange="applyFilter()">
                            <option value="all">All Frequencies</option>
                            <option value="D">Daily (D)</option>
                            <option value="M">Monthly (M)</option>
                            <option value="3M">Quarterly (3M)</option>
                            <option value="6M">Semiannual (6M)</option>
                            <option value="Y">Annual (Y)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid-container">
                <?php foreach($dashboard_data as $item): ?>
                <div class="card" data-sys="<?=$item['sys_key']?>" data-freq="<?=$item['freq_key']?>">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-chart-simple" style="color:var(--primary)"></i><?=$item['sys_label']?></div>
                        <span class="tag-period"><?=$item['freq_label']?></span>
                    </div>
                    <div class="card-body">
                        <table class="mini-table">
                            <thead><tr><th>Metric</th><?php foreach($months as $m) echo "<th>$m</th>"; ?></tr></thead>
                            <tbody>
                                <tr>
                                    <td>Plan</td>
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <td style="color:var(--text-sub)"><?=($item['data']['Plan'][$m]!=='-'? number_format($item['data']['Plan'][$m]) : '-')?></td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td>Actual</td>
                                    <?php for($m=1; $m<=12; $m++): ?>
                                        <td style="font-weight:700; color:var(--text-main)"><?=($item['data']['Actual'][$m]!=='-'? number_format($item['data']['Actual'][$m]) : '-')?></td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td>Healthy</td>
                                    <?php for($m=1; $m<=12; $m++): $h = $item['data']['Healthy'][$m]; ?>
                                        <td><?=($h!=='-' && $h>0 ? "<span class='badge badge-h'>$h</span>" : ($h!=='-'?'0':'-'))?></td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td>Not Healthy</td>
                                    <?php for($m=1; $m<=12; $m++): $nh = $item['data']['NotHealthy'][$m]; ?>
                                        <td><?=($nh!=='-' && $nh>0 ? "<span class='badge badge-nh'>$nh</span>" : ($nh!=='-'?'0':'-'))?></td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td>% Comp.</td>
                                    <?php for($m=1; $m<=12; $m++): $pct = $item['data']['Percent'][$m]; $val = ($pct !== '-') ? $pct : 0; $color = ($val >= 100) ? 'green' : (($val > 0) ? 'orange' : 'gray'); ?>
                                        <td><?php if($pct !== '-'): ?><div class="progress-cell"><span class="val-text c-<?=$color?>"><?=$val?>%</span><div class="progress-bar"><div class="progress-fill bg-<?=$color?>" style="width:<?=$val>100?100:$val?>%"></div></div></div><?php else: echo '-'; endif; ?></td>
                                    <?php endfor; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="noDataMsg" style="display:none; text-align:center; padding:40px; color:var(--text-sub);"><i class="fa-solid fa-filter-circle-xmark" style="font-size:3rem; margin-bottom:10px;"></i><p>No data matches the selected filters.</p></div>
        </div>
    </div>
    
    <script src="js/dashboard.js"></script>
</body>
</html>