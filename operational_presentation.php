<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

// حماية: تسجيل الدخول مطلوب
if (empty($_SESSION['op_user_id'])) {
    header("Location: operational_login.php");
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tableExists(string $table): bool {
    try {
        $s = pdo()->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $s->execute([$table]);
        return ((int)$s->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}

function columnExists(string $table, string $column): bool {
    try {
        $s = pdo()->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $s->execute([$column]);
        return (bool)$s->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function latestRow(string $table, string $department = ''): array {
    if (!tableExists($table)) return [];
    try {
        if ($department !== '' && columnExists($table, 'department')) {
            $s = pdo()->prepare("SELECT * FROM `$table` WHERE department = ? ORDER BY id DESC LIMIT 1");
            $s->execute([$department]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $s = pdo()->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT 1");
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function latestRows(string $table, int $limit = 20, string $department = ''): array {
    if (!tableExists($table)) return [];
    try {
        $limit = max(1, min(200, $limit));
        if ($department !== '' && columnExists($table, 'department')) {
            $s = pdo()->prepare("SELECT * FROM `$table` WHERE department = ? ORDER BY id DESC LIMIT {$limit}");
            $s->execute([$department]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        }
        $s = pdo()->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT {$limit}");
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

function rowsByDailyId(string $table, int $dailyId): array {
    if ($dailyId <= 0 || !tableExists($table)) return [];
    try {
        if (columnExists($table, 'daily_id')) {
            $s = pdo()->prepare("SELECT * FROM `$table` WHERE daily_id = ? ORDER BY id ASC");
            $s->execute([$dailyId]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        }
        if (columnExists($table, 'report_id')) {
            $s = pdo()->prepare("SELECT * FROM `$table` WHERE report_id = ? ORDER BY id ASC");
            $s->execute([$dailyId]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        }
        return latestRows($table, 20);
    } catch (Throwable $e) { return []; }
}

function num($v): float {
    if ($v === null || $v === '') return 0;
    $v = str_replace(['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9'], (string)$v);
    return (float)preg_replace('/[^\d.]/', '', $v);
}

/* ===== تلوين القيم مقابل المستهدف (نفس منطق صفحة القطاعات) ===== */
function toSecP($s): ?float {
    $s = trim((string)$s);
    if ($s === '') return null;
    if (strpos($s, ':') !== false) {
        $p = explode(':', $s);
        return ((float)($p[0] ?? 0))*3600 + ((float)($p[1] ?? 0))*60 + ((float)($p[2] ?? 0));
    }
    return null;
}
function toPctP($s): ?float {
    $v = (float)str_replace('%', '', trim((string)$s));
    return trim((string)$s) === '' ? null : $v;
}
function colorClassP($val, $target): string {
    if (trim((string)$val) === '' || trim((string)$target) === '') return 'c-muted';
    if (strpos((string)$target, '%') !== false) {
        $v = toPctP($val); $t = toPctP($target);
        if ($v === null || $t === null) return 'c-muted';
        return $v >= $t ? 'c-ok' : 'c-bad';
    }
    $v = toSecP($val); $t = toSecP($target);
    if ($v === null || $t === null) return 'c-muted';
    if ($v <= $t) return 'c-ok';
    if ($v <= $t * 1.05) return 'c-mid';
    return 'c-bad';
}
function respColorClass($pct, float $base, float $target): string {
    $v = toPctP($pct);
    if ($v === null) return 'c-muted';
    if ($v >= $target) return 'c-ok';
    if ($v >= $base) return 'c-mid';
    return 'c-bad';
}

function renderTable(array $rows, int $limit = 10): void {
    if (!$rows) { echo '<div class="empty">لا توجد بيانات</div>'; return; }
    $rows = array_slice($rows, 0, $limit);
    $skip = ['password','token','data_json'];
    $cols = [];
    foreach ($rows as $r) foreach (array_keys($r) as $c)
        if (!in_array($c, $skip, true) && !in_array($c, $cols, true)) $cols[] = $c;
    $cols = array_slice($cols, 0, 8);
    echo '<table class="data-table"><thead><tr>';
    foreach ($cols as $c) echo '<th>' . h(str_replace('_',' ',$c)) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($cols as $c) echo '<td>' . h((string)($r[$c] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function kvTable(array $pairs): void {
    // جدول بيان/قيمة من مصفوفة مفاتيح => قيم
    if (!$pairs) { echo '<div class="empty">لا توجد بيانات</div>'; return; }
    echo '<table class="data-table"><thead><tr><th>البيان</th><th>القيمة</th></tr></thead><tbody>';
    foreach ($pairs as $k => $v) {
        if (is_array($v)) continue;
        if (trim((string)$v) === '') continue;
        echo '<tr><td style="text-align:right;font-weight:700">' . h((string)$k) . '</td><td>' . h((string)$v) . '</td></tr>';
    }
    echo '</tbody></table>';
}

/* =====================================================
   جلب البيانات
===================================================== */

// الشؤون الطبية
$medical    = latestRow('operational_medical_daily', 'إدارة الشؤون الطبية');
$medicalId  = (int)($medical['id'] ?? 0);
$cardiacRows = rowsByDailyId('operational_medical_cardiac', $medicalId);
$traumaRows  = rowsByDailyId('operational_medical_trauma', $medicalId);
$medicalChart = [
    'حالات CPR'        => (string)($medical['cpr'] ?? '—'),
    'الصحة المهنية'    => (string)($medical['occupational_health'] ?? '—'),
    'السكتة الدماغية'  => (string)($medical['stroke'] ?? '—'),
    'القسطرة القلبية'  => (string)($medical['cath_cases'] ?? '—'),
    'الأسبرين'         => (string)($medical['aspirin'] ?? '—'),
    'تخطيط القلب'      => (string)($medical['ecg'] ?? '—'),
    'مسار الإصابات'    => (string)($medical['trauma_path'] ?? '—'),
];

// القطاعات — نفس بيانات صفحة الإدخال
$secRow  = latestRow('operational_sectors_daily', 'إدارة القطاعات');
$SEC     = [];
if (!empty($secRow['data_json'])) {
    $tmp = json_decode((string)$secRow['data_json'], true);
    if (is_array($tmp)) $SEC = $tmp;
}
$secV = function(string $k) use ($SEC): string {
    $v = trim((string)($SEC[$k] ?? ''));
    return $v !== '' ? $v : '—';
};
$secArr = function(string $k) use ($SEC): array {
    return is_array($SEC[$k] ?? null) ? $SEC[$k] : [];
};

$respGroups = [
    ['داخل التجمعات السكانية — أكثر من 50 ألف نسمة', 91, 95, [
        [1, '8 دقائق',  '(إيكو – دلتا توقف قلب وتنفس – كود 1)'],
        [2, '10 دقائق', '(دلتا – برافو – غير معروف – كود 2)'],
        [3, '15 دقيقة', '(تشارلي – برافو – كود 3)'],
    ]],
    ['الضواحي — من 5000 إلى 50 ألف نسمة', 90, 95, [
        [4, '12 دقيقة', '(إيكو – دلتا توقف قلب وتنفس – كود 1)'],
        [5, '15 دقيقة', '(دلتا – برافو – غير معروف – كود 2)'],
        [6, '20 دقيقة', '(تشارلي – برافو – كود 3)'],
    ]],
    ['المناطق النائية — أقل من 5000 نسمة', 83, 85, [
        [7, '20 دقيقة', '(إيكو – دلتا توقف قلب وتنفس – كود 1)'],
        [8, '25 دقيقة', '(دلتا – برافو – غير معروف – كود 2)'],
        [9, '30 دقيقة', '(تشارلي – برافو – كود 3)'],
    ]],
];

$tripRows = [
    ['انتظار الترحيل', '0:00:10'], ['زمن القبول', '0:00:11'], ['زمن التحرك', '0:01:00'],
    ['الزمن من التحرك إلى الوصول للموقع', '0:06:50'], ['زمن الانتظار قبل المباشرة', '0:01:06'],
    ['زمن تقديم الخدمة الاسعافية في الموقع', '0:13:07'], ['زمن الوصول للمستشفى', '0:12:02'],
    ['زمن تسليم المريض', '90%'], ['زمن الاغلاق', '0:01:07'], ['زمن العودة للجاهزية', '0:01:08'],
];

// الطوارئ — تُحفظ في الجدول العام
$emergencyEntries = [];
try {
    $s = pdo()->prepare("SELECT title, value_1, created_at FROM operational_entries WHERE department = 'إدارة الطوارئ' AND DATE(created_at) = (SELECT DATE(MAX(created_at)) FROM operational_entries WHERE department = 'إدارة الطوارئ') ORDER BY id ASC LIMIT 40");
    $s->execute();
    $emergencyEntries = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// التطوع
$volStats      = latestRow('volunteer_stats');
$volActivities = latestRows('volunteer_activities', 12);
$volDiversity  = latestRow('volunteer_diversity');
$volRecs       = latestRow('volunteer_recommendations');

// التموين
$supplyRow  = latestRow('supply_daily_reports', 'التموين الطبي والمستودعات');
$supplyData = [];
if (!empty($supplyRow['data_json'])) {
    $tmp = json_decode((string)$supplyRow['data_json'], true);
    if (is_array($tmp)) $supplyData = $tmp;
}
$supplyTemps = latestRows('medical_temperatures', 15);
$supplyOxy   = latestRow('medical_oxygen');

// الأسطول
$fleetDist   = latestRow('fleet_distribution');
$fleetMaint  = latestRows('fleet_maintenance', 10);
$fleetSupport = latestRows('fleet_tech_support', 10);
$fleetBudget = latestRow('fleet_budget');
$fleetRecs   = latestRow('fleet_recommendations');

// المرافق
$opsStats = latestRow('ops_statistics');
$opsReqs  = latestRows('ops_requests', 12);
$opsRecs  = latestRow('ops_recommendations');

// الاتصال المؤسسي
$commRow  = latestRow('corporate_communication_reports', 'الاتصال المؤسسي');
$commData = [];
if (!empty($commRow['data_json'])) {
    $tmp = json_decode((string)$commRow['data_json'], true);
    if (is_array($tmp)) $commData = $tmp;
}

// الخدمات التقنية
$techDevices  = latestRows('tech_operational_devices', 12);
$techSupport  = latestRows('tech_support_requests', 10);
$techLines    = latestRows('tech_communication_lines', 12);
$techFaults   = latestRows('tech_support_devices', 10);

// الالتزام
$compComplaints = latestRows('compliance_complaints', 10);
$compViolations = latestRows('compliance_violations', 10);
$compRecs       = latestRow('compliance_recommendations');

// القانونية
$legalReport = latestRow('legal_department_reports');
$legalTrans  = rowsByDailyId('legal_department_transactions', (int)($legalReport['id'] ?? 0));

// صوت الموظف
$voiceReport = latestRow('employee_voice_reports', 'صوت الموظف');
$voiceItems  = rowsByDailyId('employee_voice_items', (int)($voiceReport['id'] ?? 0));

// حالة الإرسال اليومية
$deptChecks = [
    'إدارة الشؤون الطبية'            => ['operational_medical_daily'],
    'إدارة القطاعات'                 => ['operational_sectors_daily'],
    'إدارة الطوارئ'                  => ['operational_entries'],
    'إدارة التطوع'                   => ['volunteer_stats','volunteer_activities','volunteer_recommendations'],
    'التموين الطبي والمستودعات'      => ['supply_daily_reports','medical_temperatures'],
    'تشغيل وصيانة الأسطول'           => ['fleet_distribution','fleet_daily_reports','fleet_maintenance'],
    'تشغيل وصيانة المرافق'           => ['ops_statistics','ops_requests'],
    'الاتصال المؤسسي'                => ['corporate_communication_reports'],
    'الوضع التشغيلي للخدمات التقنية' => ['tech_operational_devices','tech_support_requests','tech_support_devices'],
    'إدارة الالتزام'                 => ['compliance_complaints','compliance_violations','compliance_recommendations'],
    'الإدارة القانونية'              => ['legal_department_reports'],
    'صوت الموظف'                     => ['employee_voice_reports'],
];

$todayCount = 0; $deptStatus = []; $lastSent = 'لا يوجد';
foreach ($deptChecks as $dName => $tables) {
    $sentAt = '';
    foreach ($tables as $t) {
        $r = latestRow($t, $dName);
        if (!$r) $r = latestRow($t);
        if (!empty($r['created_at']) && $r['created_at'] > $sentAt) $sentAt = $r['created_at'];
    }
    $isToday = $sentAt && date('Y-m-d', strtotime($sentAt)) === date('Y-m-d');
    if ($isToday) $todayCount++;
    if ($sentAt && ($lastSent === 'لا يوجد' || $sentAt > $lastSent)) $lastSent = $sentAt;
    $deptStatus[$dName] = ['sent_at' => $sentAt ?: '—', 'is_today' => $isToday];
}

$todayArabic = date('d-m-Y');
$curMonthAr  = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'][ (int)date('n') - 1 ];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>العرض التشغيلي — الملف التشغيلي</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--red:#C8102E;--gold:#D4A017;--green:#16a34a;--dark:#1f2937;--gray:#6b7280;--line:#e5e7eb}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#111827;direction:rtl;overflow:hidden}

.deck{position:relative;width:100vw;height:100vh;display:flex;align-items:center;justify-content:center}
.slide{display:none;position:relative;width:min(96vw, 177vh);aspect-ratio:16/9;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.slide.active{display:block}
.slide-inner{position:absolute;inset:0;padding:30px 40px 46px;overflow-y:auto}

/* الشعار */
.logo-line{display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-bottom:6px}
.logo-line .ar{font-size:15px;font-weight:900;color:var(--dark)}
.logo-line .en{font-size:9px;color:var(--gray);letter-spacing:.5px}
.crescent{width:34px;height:34px;border-radius:50%;background:radial-gradient(circle at 35% 50%, #fff 38%, var(--red) 40%);border:2px solid var(--red);flex-shrink:0}

/* الشريط السفلي */
.footer-997{position:absolute;bottom:0;left:0;right:0;height:34px;display:flex;align-items:center;justify-content:flex-start;padding:0 14px;gap:8px;background:linear-gradient(90deg, var(--red) 0 180px, #f8fafc 180px);font-size:12px;color:#fff;font-weight:900}
.footer-997 .num{font-size:16px}
.footer-997 .handle{font-size:10px;opacity:.85}
.slash-mini{position:absolute;bottom:0;left:150px;width:40px;height:34px;background:var(--gold);transform:skewX(-25deg)}

/* الغلاف والفواصل */
.divider-body{position:absolute;inset:0;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;padding:0 8%}
.divider-title{font-size:44px;font-weight:900;color:var(--red);margin-bottom:8px}
.divider-name{font-size:22px;font-weight:800;color:var(--green)}
.big-slash{position:absolute;bottom:-20px;left:6%;width:280px;height:260px;pointer-events:none}
.big-slash .s1{position:absolute;left:90px;bottom:0;width:70px;height:240px;background:var(--red);transform:skewX(-28deg)}
.big-slash .s2{position:absolute;left:20px;bottom:0;width:34px;height:160px;background:var(--gold);transform:skewX(-28deg)}
.cover-title{font-size:46px;font-weight:900;color:var(--red);margin:20px 0 10px}
.cover-sub{font-size:22px;color:var(--green);font-weight:800;line-height:1.7}

/* العناوين */
.s-head{text-align:center;margin-bottom:12px}
.s-head h2{font-size:22px;font-weight:900;color:var(--red)}
.s-head p{font-size:12px;color:var(--gray);margin-top:2px}
.sec-bar{background:var(--red);color:#fff;font-size:13px;font-weight:900;text-align:center;padding:7px;border-radius:8px 8px 0 0;margin-top:10px}
.sec-bar.blue{background:#2563eb}.sec-bar.orange{background:#f97316}.sec-bar.green{background:var(--green)}

/* الجداول */
.data-table{width:100%;border-collapse:collapse;background:#fff;font-size:11px}
.data-table th{background:var(--green);color:#fff;padding:6px 7px;font-weight:800;text-align:center;white-space:nowrap;border:1px solid #ffffff33}
.data-table td{padding:5px 7px;border:1px solid var(--line);text-align:center;color:var(--dark)}
.data-table tr:nth-child(even) td{background:#f8fafc}
.group-row td{background:var(--dark)!important;color:#fff!important;font-weight:900}
.empty{text-align:center;color:var(--gray);padding:14px;font-size:12px;background:#f8fafc;border:1px dashed var(--line);border-radius:8px}

/* التلوين */
.c-ok{background:#16a34a!important;color:#fff!important;font-weight:900}
.c-mid{background:#f59e0b!important;color:#fff!important;font-weight:900}
.c-bad{background:#dc2626!important;color:#fff!important;font-weight:900}
.c-muted{background:#f1f5f9!important;color:#94a3b8!important}
.t-target{color:var(--red);font-weight:900;background:#f0fdf4!important}

/* شرائط KPI */
.kpi-strip{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:14px 0}
.kpi{flex:1;min-width:100px;max-width:150px;text-align:center;border:1px solid var(--line);border-radius:12px;padding:10px 6px;background:#fff}
.kpi .circle{width:38px;height:38px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff}
.kpi b{display:block;font-size:17px;color:var(--dark);margin-bottom:2px}
.kpi span{font-size:10px;color:var(--gray);font-weight:700;line-height:1.3;display:block}
.k-red .circle{background:var(--red)} .k-gold .circle{background:var(--gold)} .k-green .circle{background:var(--green)}
.k-blue .circle{background:#2563eb} .k-purple .circle{background:#7c3aed} .k-sky .circle{background:#0ea5e9} .k-slate .circle{background:#475569}

.two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.status-list{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.status-item{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:8px;padding:6px 10px;font-size:11px;font-weight:700}
.dot{width:9px;height:9px;border-radius:50%;background:#d1d5db;flex-shrink:0}
.dot.ok{background:var(--green)}
.sent-time{margin-right:auto;font-size:9px;color:var(--gray)}

/* التنقل */
.nav{position:fixed;bottom:14px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:10px;background:rgba(17,24,39,.85);border:1px solid rgba(255,255,255,.15);border-radius:999px;padding:7px 16px;z-index:99}
.nav button{background:var(--red);color:#fff;border:none;border-radius:999px;width:34px;height:34px;font-size:15px;cursor:pointer;font-family:inherit}
.nav button:hover{filter:brightness(1.15)}
.nav #counter{color:#fff;font-size:12px;font-weight:800;min-width:52px;text-align:center}
.nav .print-btn{width:auto;padding:0 14px;font-size:11px;font-weight:800;background:var(--gold)}

@media print {
  body{background:#fff;overflow:visible}
  .deck{display:block;width:auto;height:auto}
  .slide{display:block!important;width:100%;aspect-ratio:16/9;page-break-after:always;box-shadow:none;border-radius:0;margin:0}
  .nav{display:none}
}
</style>
</head>
<body>
<div class="deck">

<?php
/* ===== مكونات مساعدة للعرض ===== */
function slideLogo(): void {
    echo '<div class="logo-line"><div><div class="ar">هيئة الهلال الأحمر السعودي</div><div class="en">SAUDI RED CRESCENT AUTHORITY</div></div><div class="crescent"></div></div>';
}
function slideFooter(): void {
    echo '<div class="slash-mini"></div><div class="footer-997"><span class="num">997</span><span class="handle">@mediasrca</span></div>';
}
function dividerSlide(string $title, string $name): void {
    echo '<section class="slide"><div class="slide-inner">';
    slideLogo();
    echo '<div class="divider-body"><div class="divider-title">' . h($title) . '</div><div class="divider-name">' . h($name) . '</div></div>';
    echo '<div class="big-slash"><div class="s2"></div><div class="s1"></div></div>';
    echo '</div>';
    slideFooter();
    echo '</section>';
}
?>

  <!-- ===== 1: الغلاف ===== -->
  <section class="slide active">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="divider-body">
        <div class="cover-title">الاجتماع التشغيلي</div>
        <div class="cover-sub">فرع الهيئة بمنطقة تبوك<br>يوم <?= h(['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][(int)date('w')]) ?> الموافق <?= h($todayArabic) ?> م</div>
      </div>
      <div class="big-slash"><div class="s2"></div><div class="s1"></div></div>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== 2: حالة الإرسال ===== -->
  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>المحاور التشغيلية</h2><p>حالة إرسال الأقسام اليوم</p></div>
      <div class="kpi-strip">
        <div class="kpi k-green"><div class="circle">✅</div><b><?= (int)$todayCount ?></b><span>أقسام مرسلة اليوم</span></div>
        <div class="kpi k-gold"><div class="circle">🏢</div><b><?= count($deptChecks) ?></b><span>إجمالي الأقسام</span></div>
        <div class="kpi k-red"><div class="circle">⏳</div><b><?= count($deptChecks) - $todayCount ?></b><span>غير مرسل</span></div>
        <div class="kpi k-blue"><div class="circle">🕒</div><b style="font-size:11px"><?= h($lastSent) ?></b><span>آخر إرسال</span></div>
      </div>
      <div class="status-list">
        <?php foreach ($deptStatus as $dn => $st): ?>
          <div class="status-item">
            <span class="dot <?= $st['is_today'] ? 'ok' : '' ?>"></span>
            <?= h($dn) ?>
            <span class="sent-time"><?= h($st['sent_at']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== إدارة الشؤون الطبية ===== -->
  <?php dividerSlide('إدارة الشؤون الطبية', 'د/ بدر'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>مؤشرات الشؤون الطبية اليومية</h2><p><?= h((string)($medical['report_date'] ?? $todayArabic)) ?></p></div>
      <div class="kpi-strip">
        <?php $kc = ['k-gold','k-sky','k-green','k-blue','k-purple','k-red','k-slate']; $ki = 0;
        foreach ($medicalChart as $lbl => $val): ?>
          <div class="kpi <?= $kc[$ki % 7] ?>"><div class="circle">●</div><b style="font-size:12px"><?= h($val ?: '—') ?></b><span><?= h($lbl) ?></span></div>
        <?php $ki++; endforeach; ?>
      </div>
      <div class="two-col">
        <div>
          <div class="sec-bar">حالات توقف القلب والتنفس</div>
          <?php renderTable($cardiacRows, 6); ?>
        </div>
        <div>
          <div class="sec-bar">حالات عدم تفعيل مسار الإصابات</div>
          <?php renderTable($traumaRows, 6); ?>
        </div>
      </div>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== إدارة القطاعات ===== -->
  <?php dividerSlide('إدارة القطاعات', 'مبارك العطوي'); ?>

  <!-- القطاعات: مؤشرات الاستجابة -->
  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>مؤشرات نسبة البلاغات المستجاب لها خلال الزمن المستهدف</h2>
        <p>آخر تقرير: <?= h((string)($secRow['created_at'] ?? 'لا يوجد')) ?></p></div>

      <?php
      $secTables = [
          ['p',   'من ' . $secV('ind_date_from') . ' إلى ' . $secV('ind_date_to'), 'blue'],
          ['ytd', 'من بداية السنة حتى تاريخه', 'orange'],
      ];
      foreach ($secTables as [$tk, $tTitle, $tCls]): ?>
        <div class="sec-bar <?= $tCls ?>">📊 المؤشرات <?= h($tTitle) ?></div>
        <table class="data-table">
          <thead><tr><th style="width:22px">#</th><th style="text-align:right">المؤشر</th><th>المستهدف</th><th>إجمالي البلاغات</th><th>المحققة</th><th>النسبة</th></tr></thead>
          <tbody>
          <?php foreach ($respGroups as [$gTitle, $gBase, $gTarget, $gRows]): ?>
            <tr class="group-row"><td colspan="6"><?= h($gTitle) ?></td></tr>
            <?php foreach ($gRows as [$ri, $time, $cls]): ?>
              <tr>
                <td><?= $ri ?></td>
                <td style="text-align:right">نسبة البلاغات المستجاب لها خلال <b><?= h($time) ?></b> للحالات المصنفة <?= h($cls) ?></td>
                <td class="t-target"><?= $gTarget ?>%</td>
                <td><?= h($secV("resp_total_{$tk}_{$ri}")) ?></td>
                <td><?= h($secV("resp_ok_{$tk}_{$ri}")) ?></td>
                <td class="<?= respColorClass($secV("resp_pct_{$tk}_{$ri}"), (float)$gBase, (float)$gTarget) ?>"><?= h($secV("resp_pct_{$tk}_{$ri}")) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endforeach; ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- القطاعات: مؤشرات الرحلة الإسعافية -->
  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>مؤشرات الرحلة الإسعافية بمنطقة تبوك</h2><p><?= h($todayArabic) ?></p></div>
      <table class="data-table">
        <thead><tr><th style="width:22px">ت</th><th style="text-align:right">اسم مؤشر الأداء</th><th>المستهدف <?= date('Y') ?></th><th>من بداية السنة</th><th>شهر <?= h($curMonthAr) ?></th><th>خلال 24 ساعة</th></tr></thead>
        <tbody>
        <?php foreach ($tripRows as $ti => [$tn, $tt]): ?>
          <tr>
            <td><?= $ti + 1 ?></td>
            <td style="text-align:right;font-weight:700"><?= h($tn) ?></td>
            <td class="t-target"><?= h($tt) ?></td>
            <td class="<?= colorClassP($secV("trip_ytd_{$ti}"), $tt) ?>"><?= h($secV("trip_ytd_{$ti}")) ?></td>
            <td class="<?= colorClassP($secV("trip_month_{$ti}"), $tt) ?>"><?= h($secV("trip_month_{$ti}")) ?></td>
            <td class="<?= colorClassP($secV("trip_day_{$ti}"), $tt) ?>"><?= h($secV("trip_day_{$ti}")) ?></td>
          </tr>
        <?php endforeach; ?>
          <tr>
            <td>11</td>
            <td style="text-align:right;font-weight:700">نسبة بلاغات الطوارئ التي تم الاستجابة لها خلال 8 دقائق ( عام المنطقة )</td>
            <td class="t-target">85%</td>
            <td class="<?= colorClassP($secV('trip8_ytd'), '85%') ?>"><?= h($secV('trip8_ytd')) ?></td>
            <td class="<?= colorClassP($secV('trip8_month'), '85%') ?>"><?= h($secV('trip8_month')) ?></td>
            <td class="<?= colorClassP($secV('trip8_day'), '85%') ?>"><?= h($secV('trip8_day')) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- القطاعات: غرفة التحكم + المركبات -->
  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>وضع غرفة التحكم والمركبات</h2><p>العمليات التشغيلية / الاتصالات / الأعطال</p></div>

      <div class="sec-bar">🔧 الدعم الفني</div>
      <table class="data-table">
        <thead><tr><th>رقم طلب الدعم الفني</th><th>سبب الدعم الفني</th><th>الإجراء المتخذ</th><th>الملاحظات</th></tr></thead>
        <tbody><tr><td><?= h($secV('sup1')) ?></td><td><?= h($secV('sup2')) ?></td><td><?= h($secV('sup3')) ?></td><td><?= h($secV('sup4')) ?></td></tr></tbody>
      </table>

      <div class="sec-bar orange">⚠️ البلاغات المتأثرة بعوامل استثنائية أو ظروف طارئة</div>
      <table class="data-table">
        <thead><tr><th>رقم البلاغ</th><th>نوع الأثر</th><th>الأثر</th><th>الإجراء</th></tr></thead>
        <tbody><tr><td><?= h($secV('inc1')) ?></td><td><?= h($secV('inc2')) ?></td><td><?= h($secV('inc3')) ?></td><td><?= h($secV('inc4')) ?></td></tr></tbody>
      </table>

      <div class="two-col" style="margin-top:10px">
        <div>
          <div class="sec-bar green">عدد المركبات العاملة بالقطاعات</div>
          <table class="data-table">
            <thead><tr><th>قطاع تبوك</th><th>قطاع تيماء</th><th>قطاع نيوم</th><th>قطاع الساحل</th></tr></thead>
            <tbody><tr><td><?= h($secV('veh_active_tabuk')) ?></td><td><?= h($secV('veh_active_tayma')) ?></td><td><?= h($secV('veh_active_neom')) ?></td><td><?= h($secV('veh_active_coast')) ?></td></tr></tbody>
          </table>
        </div>
        <div>
          <div class="sec-bar green">عدد المركبات الاحتياط بالقطاعات</div>
          <table class="data-table">
            <thead><tr><th>قطاع تبوك</th><th>قطاع تيماء</th><th>قطاع نيوم</th><th>قطاع الساحل</th></tr></thead>
            <tbody><tr><td><?= h($secV('veh_backup_tabuk')) ?></td><td><?= h($secV('veh_backup_tayma')) ?></td><td><?= h($secV('veh_backup_neom')) ?></td><td><?= h($secV('veh_backup_coast')) ?></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- القطاعات: الخروج عن الخدمة والتمركز والعهد -->
  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>الخروج عن الخدمة والتمركز والعهد الطبية</h2></div>

      <div class="sec-bar">⏱️ ساعات الخروج عن الخدمة من <?= h($secV('out_date_from')) ?> إلى <?= h($secV('out_date_to')) ?></div>
      <?php
      $outRows = $secArr('out_rows');
      if (!$outRows) echo '<div class="empty">لا توجد حالات خروج عن الخدمة</div>';
      else {
          echo '<table class="data-table"><thead><tr><th>ساعة الخروج</th><th>ساعة العودة</th><th>الفترة</th><th>أسباب الخروج</th><th>القطاع</th></tr></thead><tbody>';
          foreach ($outRows as $r) {
              if (!is_array($r)) continue;
              echo '<tr><td>'.h((string)($r['out_time']??'')).'</td><td>'.h((string)($r['back_time']??'')).'</td><td>'.h((string)($r['period']??'')).'</td><td>'.h((string)($r['reason']??'')).'</td><td>'.h((string)($r['sector']??'')).'</td></tr>';
          }
          echo '</tbody></table>';
      }
      ?>

      <div class="two-col" style="margin-top:10px">
        <div>
          <div class="sec-bar orange">📍 تمركز الفرق الإسعافية من <?= h($secV('tmz_date_from')) ?> إلى <?= h($secV('tmz_date_to')) ?></div>
          <?php
          $tmz = $secArr('tmz_rows');
          if (!$tmz) echo '<div class="empty">لا توجد بيانات تمركز</div>';
          else {
              echo '<table class="data-table"><thead><tr><th>عدد حالات التمركز</th><th>أماكن التمركز</th></tr></thead><tbody>';
              foreach ($tmz as $r) { if (!is_array($r)) continue;
                  echo '<tr><td>'.h((string)($r['count']??'')).'</td><td>'.h((string)($r['places']??'')).'</td></tr>'; }
              echo '</tbody></table>';
          }
          ?>
        </div>
        <div>
          <div class="sec-bar green">🏥 العهد الطبية في المستشفيات</div>
          <?php
          $cst = $secArr('custody_rows');
          if (!$cst) echo '<div class="empty">لا توجد بيانات</div>';
          else {
              echo '<table class="data-table"><thead><tr><th>اسم الصنف</th><th>الملاحظة</th></tr></thead><tbody>';
              foreach ($cst as $r) { if (!is_array($r)) continue;
                  echo '<tr><td>'.h((string)($r['item']??'')).'</td><td>'.h((string)($r['note']??'')).'</td></tr>'; }
              echo '</tbody></table>';
          }
          ?>
        </div>
      </div>

      <div class="two-col" style="margin-top:10px">
        <div>
          <div class="sec-bar">🚨 التحديات في زمن تسليم الحالة للمستشفى</div>
          <?php
          $chl = $secArr('challenge_rows');
          if (!$chl) echo '<div class="empty">لا توجد تحديات</div>';
          else {
              echo '<table class="data-table"><thead><tr><th>رقم البلاغ</th><th>التحدي</th></tr></thead><tbody>';
              foreach ($chl as $r) { if (!is_array($r)) continue;
                  echo '<tr><td>'.h((string)($r['report_no']??'')).'</td><td>'.h((string)($r['challenge']??'')).'</td></tr>'; }
              echo '</tbody></table>';
          }
          ?>
        </div>
        <div>
          <div class="sec-bar orange">⏳ مؤشر تسليم الحالة حتى مغادرة المستشفى</div>
          <?php
          $hnd = $secArr('handover_rows');
          if (!$hnd) echo '<div class="empty">لا توجد بيانات</div>';
          else {
              echo '<table class="data-table"><thead><tr><th>التاريخ</th><th>القيمة المحققة</th></tr></thead><tbody>';
              foreach ($hnd as $r) { if (!is_array($r)) continue;
                  echo '<tr><td>'.h((string)($r['date_range']??'')).'</td><td>'.h((string)($r['value']??'')).'</td></tr>'; }
              echo '</tbody></table>';
          }
          ?>
        </div>
      </div>

      <?php if (trim((string)($SEC['notes'] ?? '')) !== ''): ?>
        <div class="sec-bar green" style="margin-top:10px">📝 ملاحظات عامة</div>
        <div style="border:1px solid var(--line);padding:8px 12px;font-size:12px;border-radius:0 0 8px 8px"><?= nl2br(h((string)$SEC['notes'])) ?></div>
      <?php endif; ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== إدارة الطوارئ ===== -->
  <?php dividerSlide('إدارة الطوارئ', 'خالد الصالح'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>بيانات إدارة الطوارئ</h2><p>آخر يوم إرسال</p></div>
      <?php
      if (!$emergencyEntries) echo '<div class="empty">لا توجد بيانات محفوظة لإدارة الطوارئ</div>';
      else {
          echo '<table class="data-table"><thead><tr><th style="text-align:right">البند</th><th>القيمة</th><th>وقت الإرسال</th></tr></thead><tbody>';
          foreach ($emergencyEntries as $r) {
              echo '<tr><td style="text-align:right;font-weight:700">'.h((string)$r['title']).'</td><td>'.h((string)$r['value_1']).'</td><td>'.h((string)$r['created_at']).'</td></tr>';
          }
          echo '</tbody></table>';
      }
      ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== إدارة التطوع ===== -->
  <?php dividerSlide('إدارة التطوع', 'فايزة البلوي'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>المشاركات التطوعية</h2></div>
      <div class="kpi-strip">
        <div class="kpi k-green"><div class="circle">🤝</div><b><?= h((string)($volStats['total_volunteers'] ?? '—')) ?></b><span>إجمالي المتطوعين</span></div>
        <div class="kpi k-gold"><div class="circle">📊</div><b><?= h((string)($volStats['participation_rate'] ?? '—')) ?></b><span>نسبة المشاركة</span></div>
        <div class="kpi k-blue"><div class="circle">⏰</div><b><?= h((string)($volStats['total_hours'] ?? '—')) ?></b><span>إجمالي الساعات</span></div>
        <div class="kpi k-red"><div class="circle">⚡</div><b><?= h((string)($volStats['efficiency_pct'] ?? '—')) ?></b><span>الكفاءة</span></div>
      </div>
      <div class="two-col">
        <div>
          <div class="sec-bar green">الأنشطة والبرامج التطوعية</div>
          <?php renderTable($volActivities, 8); ?>
        </div>
        <div>
          <div class="sec-bar blue">التنوع التطوعي</div>
          <?php
          if (!$volDiversity) echo '<div class="empty">لا توجد بيانات</div>';
          else kvTable([
              'إسعافي' => $volDiversity['ambulance'] ?? '', 'تنظيمي' => $volDiversity['organizing'] ?? '',
              'إنساني' => $volDiversity['humanitarian'] ?? '', 'بيئي' => $volDiversity['environment'] ?? '',
              'إعلامي' => $volDiversity['media'] ?? '', 'إداري' => $volDiversity['administrative'] ?? '',
              'الإجمالي' => $volDiversity['total_pct'] ?? '',
          ]);
          ?>
        </div>
      </div>
      <?php if (!empty($volRecs['rec_main'])): ?>
        <div class="sec-bar orange" style="margin-top:10px">التوصيات</div>
        <div style="border:1px solid var(--line);padding:8px 12px;font-size:12px"><?= nl2br(h((string)$volRecs['rec_main'])) ?></div>
      <?php endif; ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== التموين الطبي والمستودعات ===== -->
  <?php dividerSlide('التموين الطبي والمستودعات', 'خالد دغيم'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>التموين الطبي والمستودعات</h2><p>آخر تقرير: <?= h((string)($supplyRow['created_at'] ?? 'لا يوجد')) ?></p></div>
      <div class="two-col">
        <div>
          <div class="sec-bar blue">🌡️ درجات الحرارة والرطوبة</div>
          <?php renderTable($supplyTemps, 10); ?>
        </div>
        <div>
          <div class="sec-bar green">🫁 الأكسجين</div>
          <?php
          if (!$supplyOxy) echo '<div class="empty">لا توجد بيانات</div>';
          else kvTable([
              'الأسطوانات الكبيرة (إجمالي)' => $supplyOxy['large_total'] ?? '', 'كبيرة معبأة' => $supplyOxy['large_filled'] ?? '',
              'كبيرة فارغة' => $supplyOxy['large_empty'] ?? '', 'الأسطوانات الصغيرة (إجمالي)' => $supplyOxy['small_total'] ?? '',
              'صغيرة معبأة' => $supplyOxy['small_filled'] ?? '', 'صغيرة فارغة' => $supplyOxy['small_empty'] ?? '',
              'إجمالي طلبات الشهر' => $supplyOxy['total_requests_month'] ?? '',
          ]);
          ?>
          <?php if ($supplyData): ?>
            <div class="sec-bar orange" style="margin-top:8px">📦 بيانات التقرير اليومي</div>
            <?php kvTable($supplyData); ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== تشغيل وصيانة الأسطول ===== -->
  <?php dividerSlide('تشغيل وصيانة الأسطول', 'سلطان النجدي'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>توزيع الأسطول وحالته</h2></div>
      <div class="kpi-strip">
        <div class="kpi k-green"><div class="circle">🚑</div><b><?= h((string)($fleetDist['amb_total'] ?? '—')) ?></b><span>سيارات الإسعاف</span></div>
        <div class="kpi k-blue"><div class="circle">🚗</div><b><?= h((string)($fleetDist['service_total'] ?? '—')) ?></b><span>سيارات الخدمة</span></div>
        <div class="kpi k-gold"><div class="circle">🚙</div><b><?= h((string)($fleetDist['fourwd_total'] ?? '—')) ?></b><span>الدفع الرباعي</span></div>
        <div class="kpi k-red"><div class="circle">🛠</div><b><?= h((string)($fleetDist['broken_total'] ?? '—')) ?></b><span>المتعطلة</span></div>
        <div class="kpi k-purple"><div class="circle">✅</div><b><?= h((string)($fleetDist['maint_done'] ?? '—')) ?></b><span>صيانة منجزة</span></div>
        <div class="kpi k-slate"><div class="circle">📦</div><b><?= h((string)($fleetDist['backup_total'] ?? '—')) ?></b><span>الاحتياط</span></div>
      </div>
      <div class="sec-bar">🔧 مركبات تحت الصيانة</div>
      <?php renderTable($fleetMaint, 6); ?>
      <?php if ($fleetSupport): ?>
        <div class="sec-bar blue" style="margin-top:8px">🎫 طلبات الدعم الفني</div>
        <?php renderTable($fleetSupport, 5); ?>
      <?php endif; ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== تشغيل وصيانة المرافق ===== -->
  <?php dividerSlide('تشغيل وصيانة المرافق', 'ماجد دغيم'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>قسم تشغيل وصيانة المرافق</h2></div>
      <div class="kpi-strip">
        <div class="kpi k-gold"><div class="circle">📋</div><b><?= h((string)($opsStats['total'] ?? '—')) ?></b><span>إجمالي الطلبات</span></div>
        <div class="kpi k-blue"><div class="circle">🔄</div><b><?= h((string)($opsStats['active'] ?? '—')) ?></b><span>قائمة</span></div>
        <div class="kpi k-green"><div class="circle">✅</div><b><?= h((string)($opsStats['done'] ?? '—')) ?></b><span>منفذة</span></div>
      </div>
      <div class="sec-bar">🏢 طلبات الصيانة</div>
      <?php renderTable($opsReqs, 8); ?>
      <?php if (!empty($opsRecs['rec_main'])): ?>
        <div class="sec-bar orange" style="margin-top:8px">التوصيات</div>
        <div style="border:1px solid var(--line);padding:8px 12px;font-size:12px"><?= nl2br(h((string)$opsRecs['rec_main'])) ?></div>
      <?php endif; ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== الاتصال المؤسسي ===== -->
  <?php dividerSlide('الاتصال المؤسسي', 'خلود الحويطي'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>الاتصال المؤسسي</h2><p>آخر تقرير: <?= h((string)($commRow['created_at'] ?? 'لا يوجد')) ?></p></div>
      <?php
      if (!$commData) echo '<div class="empty">لا توجد بيانات محفوظة للاتصال المؤسسي</div>';
      else kvTable($commData);
      ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== الخدمات التقنية ===== -->
  <?php dividerSlide('الوضع التشغيلي للخدمات التقنية', 'فيصل الجماز'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>الوضع التشغيلي للخدمات التقنية</h2></div>
      <div class="two-col">
        <div>
          <div class="sec-bar blue">💻 الأجهزة التشغيلية</div>
          <?php renderTable($techDevices, 7); ?>
        </div>
        <div>
          <div class="sec-bar green">📞 وسائل الاتصال</div>
          <?php renderTable($techLines, 7); ?>
        </div>
      </div>
      <div class="two-col" style="margin-top:8px">
        <div>
          <div class="sec-bar orange">🎫 طلبات الدعم الفني</div>
          <?php renderTable($techSupport, 5); ?>
        </div>
        <div>
          <div class="sec-bar">🛠 أعطال الأجهزة</div>
          <?php renderTable($techFaults, 5); ?>
        </div>
      </div>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== إدارة الالتزام ===== -->
  <?php dividerSlide('إدارة الالتزام', 'عبدالعزيز عقيلي'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>إدارة الالتزام</h2></div>
      <div class="sec-bar">📋 الشكاوى والتذاكر</div>
      <?php renderTable($compComplaints, 6); ?>
      <div class="sec-bar orange" style="margin-top:8px">⚠️ المخالفات</div>
      <?php renderTable($compViolations, 5); ?>
      <?php if (!empty($compRecs['rec_main'])): ?>
        <div class="sec-bar green" style="margin-top:8px">التوصيات</div>
        <div style="border:1px solid var(--line);padding:8px 12px;font-size:12px"><?= nl2br(h((string)$compRecs['rec_main'])) ?></div>
      <?php endif; ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== الإدارة القانونية ===== -->
  <?php dividerSlide('الإدارة القانونية', 'ماجد السديس'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>الإدارة القانونية</h2></div>
      <div class="kpi-strip">
        <div class="kpi k-gold"><div class="circle">📁</div><b><?= h((string)($legalReport['total_transactions'] ?? '—')) ?></b><span>إجمالي المعاملات</span></div>
        <div class="kpi k-blue"><div class="circle">🔄</div><b><?= h((string)($legalReport['in_progress_transactions'] ?? '—')) ?></b><span>تحت الإجراء</span></div>
        <div class="kpi k-green"><div class="circle">✅</div><b><?= h((string)($legalReport['closed_or_returned_transactions'] ?? '—')) ?></b><span>مغلقة / معادة</span></div>
      </div>
      <div class="sec-bar">⚖️ المعاملات القانونية</div>
      <?php renderTable($legalTrans, 8); ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== صوت الموظف ===== -->
  <?php dividerSlide('صوت الموظف', 'ماجد السديس'); ?>

  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="s-head"><h2>صوت الموظف</h2><p><?= h((string)($voiceReport['month_year'] ?? '')) ?></p></div>
      <?php renderTable($voiceItems, 9); ?>
    </div>
    <?php slideFooter(); ?>
  </section>

  <!-- ===== النهاية ===== -->
  <section class="slide">
    <div class="slide-inner">
      <?php slideLogo(); ?>
      <div class="divider-body"><div class="divider-title">النهاية</div>
        <div class="divider-name">شكراً لكم — هيئة الهلال الأحمر السعودي فرع تبوك</div></div>
      <div class="big-slash"><div class="s2"></div><div class="s1"></div></div>
    </div>
    <?php slideFooter(); ?>
  </section>

</div><!-- /deck -->

<div class="nav">
  <button onclick="go(1)">‹</button>
  <span id="counter">1 / 1</span>
  <button onclick="go(-1)">›</button>
  <button class="print-btn" onclick="window.print()">🖨️ طباعة / PDF</button>
</div>

<script>
const slides = document.querySelectorAll('.slide');
let current = 0;
function show(i){
  slides.forEach(s => s.classList.remove('active'));
  current = (i + slides.length) % slides.length;
  slides[current].classList.add('active');
  document.getElementById('counter').textContent = (current + 1) + ' / ' + slides.length;
}
function go(d){ show(current + d); }
document.addEventListener('keydown', e => {
  if (e.key === 'ArrowLeft' || e.key === 'PageDown' || e.key === ' ') go(1);
  if (e.key === 'ArrowRight' || e.key === 'PageUp') go(-1);
  if (e.key === 'Home') show(0);
});
show(0);
</script>
</body>
</html>
