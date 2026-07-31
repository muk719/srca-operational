<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

// حماية: الأدمن فقط
if (empty($_SESSION['op_user_id'])) {
    header("Location: operational_login.php");
    exit;
}
if (($_SESSION['op_role'] ?? '') !== 'admin') {
    header("Location: dept_router.php");
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function tableExists(string $table): bool {
    try {
        $s = pdo()->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $s->execute([$table]);
        return ((int)$s->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function columnExists(string $table, string $column): bool {
    try {
        $s = pdo()->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $s->execute([$column]);
        return (bool)$s->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function latestRow(string $table, string $department = ''): array {
    if (!tableExists($table)) return [];

    try {
        if ($department !== '' && columnExists($table, 'department')) {
            $s = pdo()->prepare("
                SELECT *
                FROM `$table`
                WHERE department = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $s->execute([$department]);
        } else {
            $s = pdo()->query("
                SELECT *
                FROM `$table`
                ORDER BY id DESC
                LIMIT 1
            ");
        }

        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
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
    } catch (Throwable $e) {
        return [];
    }
}

function latestRows(string $table, int $limit = 20, string $department = ''): array {
    if (!tableExists($table)) return [];

    try {
        $limit = max(1, min(200, $limit));

        if ($department !== '' && columnExists($table, 'department')) {
            $s = pdo()->prepare("
                SELECT *
                FROM `$table`
                WHERE department = ?
                ORDER BY id DESC
                LIMIT {$limit}
            ");
            $s->execute([$department]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        }

        $s = pdo()->query("
            SELECT *
            FROM `$table`
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function niceLabel(string $key): string {
    $map = [
        'report_date' => 'تاريخ التقرير',
        'department' => 'القسم',
        'trauma_path' => 'مسار الإصابات',
        'ecg' => 'مؤشر تخطيط القلب',
        'aspirin' => 'مؤشر استخدام الأسبرين',
        'cath_cases' => 'الحالات المنقولة لمركز القسطرة',
        'stroke' => 'حالات السكتة الدماغية',
        'occupational_health' => 'الصحة المهنية',
        'cpr' => 'حالات CPR',
        'notes' => 'ملاحظات',
        'created_at' => 'وقت الإرسال',
        'team_type' => 'نوع الفرقة',
        'center' => 'المركز الإسعافي',
        'protocol_applied' => 'تطبيق البروتوكول الطبي',
        'teams_count' => 'عدد الفرق الإسعافية',
        'rosc' => 'ROSC عودة النبض',
        'case_classification' => 'تصنيف الحالة',
        'hospital' => 'المستشفى المنقولة له الحالة',
        'reason' => 'السبب',
        'created_by' => 'المدخل',
        'user_id' => 'رقم المستخدم',
        'id' => '#',
    ];

    return $map[$key] ?? str_replace('_', ' ', $key);
}

function renderGenericTable(array $rows, string $empty = 'لا توجد بيانات'): void {
    if (!$rows) {
        echo '<div class="empty">' . h($empty) . '</div>';
        return;
    }

    $skip = ['password', 'token'];
    $cols = [];
    foreach ($rows as $r) {
        foreach (array_keys($r) as $c) {
            if (!in_array($c, $skip, true) && !in_array($c, $cols, true)) {
                $cols[] = $c;
            }
        }
    }

    echo '<div class="tbl-wrap"><table><thead><tr>';
    foreach ($cols as $c) echo '<th>' . h(niceLabel($c)) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($cols as $c) {
            echo '<td>' . h($r[$c] ?? '') . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

/* ===== عرض منسق لبيانات إدارة القطاعات ===== */
function sectorsSimpleTable(array $headers, array $rows): void {
    if (!$rows) { echo '<div class="empty">لا توجد بيانات</div>'; return; }
    echo '<div class="tbl-wrap"><table><thead><tr>';
    foreach ($headers as $hd) echo '<th>' . h($hd) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($r as $c) echo '<td>' . h((string)$c) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function renderSectorsPanel(): void {
    $row = latestRow('operational_sectors_daily', 'إدارة القطاعات');
    if (!$row) { echo '<div class="empty">لا توجد بيانات محفوظة لإدارة القطاعات</div>'; return; }

    $d = json_decode((string)($row['data_json'] ?? ''), true);
    if (!is_array($d)) $d = [];
    $v = fn($k) => trim((string)($d[$k] ?? '')) !== '' ? (string)$d[$k] : '—';

    echo '<div class="section-title">🕒 آخر تقرير مُرسل: ' . h((string)($row['created_at'] ?? '')) . '</div>';

    // مؤشرات الاستجابة
    $respLabels = [
        1 => 'خلال 8 دقائق (إيكو – دلتا توقف قلب وتنفس – كود 1) — التجمعات السكانية',
        2 => 'خلال 10 دقائق (دلتا – برافو – غير معروف – كود 2) — التجمعات السكانية',
        3 => 'خلال 15 دقيقة (تشارلي – برافو – كود 3) — التجمعات السكانية',
        4 => 'خلال 12 دقيقة (إيكو – دلتا توقف قلب وتنفس – كود 1) — الضواحي',
        5 => 'خلال 15 دقيقة (دلتا – برافو – غير معروف – كود 2) — الضواحي',
        6 => 'خلال 20 دقيقة (تشارلي – برافو – كود 3) — الضواحي',
        7 => 'خلال 20 دقيقة (إيكو – دلتا توقف قلب وتنفس – كود 1) — المناطق النائية',
        8 => 'خلال 25 دقيقة (دلتا – برافو – غير معروف – كود 2) — المناطق النائية',
        9 => 'خلال 30 دقيقة (تشارلي – برافو – كود 3) — المناطق النائية',
    ];

    $respTables = [
        ['p',   '📊 مؤشرات الاستجابة من ' . $v('ind_date_from') . ' إلى ' . $v('ind_date_to')],
        ['ytd', '📊 مؤشرات الاستجابة من بداية السنة حتى تاريخه'],
    ];

    foreach ($respTables as [$k, $title]) {
        echo '<div class="section-title">' . h($title) . '</div>';
        $rows = [];
        foreach ($respLabels as $i => $lbl) {
            $rows[] = [$lbl, $v("resp_total_{$k}_{$i}"), $v("resp_ok_{$k}_{$i}"), $v("resp_pct_{$k}_{$i}")];
        }
        sectorsSimpleTable(['المؤشر', 'إجمالي البلاغات', 'المحققة', 'النسبة'], $rows);
    }

    // مؤشرات الرحلة الإسعافية
    $tripLabels = [
        'انتظار الترحيل','زمن القبول','زمن التحرك','الزمن من التحرك إلى الوصول للموقع',
        'زمن الانتظار قبل المباشرة','زمن تقديم الخدمة الاسعافية في الموقع','زمن الوصول للمستشفى',
        'زمن تسليم المريض','زمن الاغلاق','زمن العودة للجاهزية',
    ];
    echo '<div class="section-title">🚑 مؤشرات الرحلة الإسعافية بمنطقة تبوك</div>';
    $rows = [];
    foreach ($tripLabels as $i => $lbl) {
        $rows[] = [$lbl, $v("trip_ytd_{$i}"), $v("trip_month_{$i}"), $v("trip_day_{$i}")];
    }
    $rows[] = ['نسبة بلاغات الطوارئ المستجاب لها خلال 8 دقائق (عام المنطقة)', $v('trip8_ytd'), $v('trip8_month'), $v('trip8_day')];
    sectorsSimpleTable(['اسم مؤشر الأداء', 'من بداية السنة', 'الشهر الحالي', 'خلال 24 ساعة'], $rows);

    // المركبات
    $secNames = ['tabuk' => 'تبوك', 'tayma' => 'تيماء', 'neom' => 'نيوم', 'coast' => 'الساحل'];
    echo '<div class="section-title">🚐 المركبات بالقطاعات</div>';
    $active = ['عاملة']; $backup = ['احتياط'];
    foreach ($secNames as $key => $n) { $active[] = $v("veh_active_{$key}"); $backup[] = $v("veh_backup_{$key}"); }
    sectorsSimpleTable(['النوع', 'تبوك', 'تيماء', 'نيوم', 'الساحل'], [$active, $backup]);

    // الدعم الفني والبلاغات المتأثرة
    echo '<div class="section-title">🔧 الدعم الفني</div>';
    sectorsSimpleTable(['رقم الطلب', 'السبب', 'الإجراء', 'الملاحظات'], [[$v('sup1'), $v('sup2'), $v('sup3'), $v('sup4')]]);
    echo '<div class="section-title">⚠️ البلاغات المتأثرة بعوامل استثنائية</div>';
    sectorsSimpleTable(['رقم البلاغ', 'نوع الأثر', 'الأثر', 'الإجراء'], [[$v('inc1'), $v('inc2'), $v('inc3'), $v('inc4')]]);

    // الجداول الديناميكية
    $dyn = [
        ['out_rows', '⏱️ ساعات الخروج عن الخدمة من ' . $v('out_date_from') . ' إلى ' . $v('out_date_to'),
         ['out_time' => 'ساعة الخروج', 'back_time' => 'ساعة العودة', 'period' => 'الفترة', 'reason' => 'الأسباب', 'sector' => 'القطاع']],
        ['tmz_rows', '📍 تمركز الفرق الإسعافية من ' . $v('tmz_date_from') . ' إلى ' . $v('tmz_date_to'),
         ['count' => 'عدد حالات التمركز', 'places' => 'أماكن التمركز']],
        ['custody_rows', '🏥 العهد الطبية في المستشفيات',
         ['item' => 'اسم الصنف', 'note' => 'الملاحظة']],
        ['challenge_rows', '🚨 التحديات في زمن تسليم الحالة للمستشفى',
         ['report_no' => 'رقم البلاغ', 'challenge' => 'التحدي']],
        ['handover_rows', '⏳ مؤشر تسليم الحالة حتى مغادرة المستشفى',
         ['date_range' => 'التاريخ', 'value' => 'القيمة المحققة']],
    ];

    foreach ($dyn as [$key, $title, $cols]) {
        echo '<div class="section-title">' . h($title) . '</div>';
        $list = is_array($d[$key] ?? null) ? $d[$key] : [];
        $rows = [];
        foreach ($list as $r) {
            if (!is_array($r)) continue;
            $line = [];
            foreach ($cols as $ck => $cl) $line[] = trim((string)($r[$ck] ?? '')) !== '' ? (string)$r[$ck] : '—';
            if (implode('', $line) !== str_repeat('—', count($cols))) $rows[] = $line;
        }
        sectorsSimpleTable(array_values($cols), $rows);
    }

    // الملاحظات
    if (trim((string)($d['notes'] ?? '')) !== '') {
        echo '<div class="section-title">📝 ملاحظات عامة</div>';
        echo '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px;font-size:13px">' . nl2br(h((string)$d['notes'])) . '</div>';
    }
}

function ensureOperationalNotesTable(): void {
    try {
        pdo()->exec("CREATE TABLE IF NOT EXISTS operational_notes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            department VARCHAR(150) NOT NULL,
            note_text TEXT NULL,
            message TEXT NULL,
            department_reply TEXT NULL,
            replied_at DATETIME NULL,
            is_read TINYINT(1) NULL DEFAULT 0,
            recommendation_status VARCHAR(50) NULL DEFAULT 'لا',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $cols = [
            'note_text' => 'TEXT NULL',
            'message' => 'TEXT NULL',
            'department_reply' => 'TEXT NULL',
            'replied_at' => 'DATETIME NULL',
            'is_read' => "TINYINT(1) NULL DEFAULT 0",
            'recommendation_status' => "VARCHAR(50) NULL DEFAULT 'لا'",
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ];

        foreach ($cols as $col => $def) {
            if (!columnExists('operational_notes', $col)) {
                try { pdo()->exec("ALTER TABLE operational_notes ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}
}

ensureOperationalNotesTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_recommendation'])) {
    $recDept = trim($_POST['department'] ?? '');
    $recText = trim($_POST['recommendation_text'] ?? '');

    if ($recDept !== '' && $recText !== '') {
        try {
            $stmt = pdo()->prepare("
                INSERT INTO operational_notes
                (department, note_text, message, recommendation_status, is_read, created_at)
                VALUES (?, ?, ?, 'لا', 0, NOW())
            ");
            $stmt->execute([$recDept, $recText, $recText]);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
            exit;
        } catch (Throwable $e) {}
    }
}

$year  = (int)($_GET['year'] ?? date('Y'));
$month = trim($_GET['month'] ?? '');
$dept  = trim($_GET['department'] ?? '');

$departments = [
    'إدارة الشؤون الطبية' => [
        'key' => 'medical',
        'icon' => '🏥',
        'tables' => [
            'operational_medical_daily',
            'operational_medical_cardiac',
            'operational_medical_trauma',
        ],
    ],
    'إدارة القطاعات' => [
        'key' => 'sectors',
        'icon' => '🚑',
        'tables' => [
            'operational_sectors_daily',
        ],
    ],
    'إدارة الطوارئ' => [
        'key' => 'emergency',
        'icon' => '🚨',
        'tables' => [
            'emergency_warnings',
            'emergency_comm_channels',
            'emergency_hypothesis_internal',
            'emergency_hypothesis_external',
        ],
    ],
    'إدارة التطوع' => [
        'key' => 'volunteer',
        'icon' => '🤝',
        'tables' => [
            'operational_volunteers',
            'volunteer_registrations',
        ],
    ],
    'التموين الطبي والمستودعات' => [
        'key' => 'supply',
        'icon' => '📦',
        'tables' => [
            'supply_daily_reports',
            'supply_notes',
            'supply',
        ],
    ],
    'تشغيل وصيانة الأسطول' => [
        'key' => 'fleet',
        'icon' => '🚗',
        'tables' => [
            'fleet_daily_reports',
            'fleet_notes',
            'operational_vehicles',
        ],
    ],
'تشغيل وصيانة المرافق' => [
    'key' => 'facilities',
    'icon' => '🏢',
    'tables' => [
        'ops_statistics',
        'ops_requests',
        'ops_recommendations',
    ],
],
    'الاتصال المؤسسي' => [
    'key' => 'comm',
    'icon' => '📣',
    'tables' => [
        'communication_daily_reports',
        'communication_history',
        'communication_recommendations',
    ],

    ],
  'الوضع التشغيلي للخدمات التقنية' => [
    'key' => 'it',
    'icon' => '💻',
    'tables' => [
        'tech_support_devices',
        'tech_support_requests',
        'tech_communication_lines',
        'tech_operational_devices',
        'tech_recommendations',
    ],
],
  'إدارة الالتزام' => [
    'key' => 'compliance',
    'icon' => '✅',
    'tables' => [
        'compliance_complaints',
        'compliance_violations',
        'compliance_recommendations',
    ],
],
    'الإدارة القانونية' => [
        'key' => 'legal',
        'icon' => '⚖️',
        'tables' => [
            'legal_department_reports',
            'legal_department_transactions',
            'legal_department_notes',
        ],
    ],
    'صوت الموظف' => [
        'key' => 'employee_voice',
        'icon' => '🗣️',
        'tables' => [
            'employee_voice_reports',
            'employee_voice_items',
            'employee_voice_notes',
        ],
    ],
];

$todayCount = 0;
$lastSent = 'لا يوجد';
$deptStatus = [];

foreach ($departments as $name => $info) {
    $sentAt = '';
    foreach ($info['tables'] as $t) {
        $r = latestRow($t, $name);
        if (!empty($r['created_at']) && $r['created_at'] > $sentAt) {
            $sentAt = $r['created_at'];
        }
    }

    $isToday = $sentAt && date('Y-m-d', strtotime($sentAt)) === date('Y-m-d');
    if ($isToday) $todayCount++;
    if ($sentAt && ($lastSent === 'لا يوجد' || $sentAt > $lastSent)) $lastSent = $sentAt;

    $deptStatus[$name] = [
        'sent_at' => $sentAt,
        'is_today' => $isToday,
    ];
}

$notes = [];
try {
    $notes = latestRows('operational_notes', 100);
} catch (Throwable $e) {
    $notes = [];
}

$medical = latestRow('operational_medical_daily', 'إدارة الشؤون الطبية');
$medicalId = (int)($medical['id'] ?? 0);
$cardiacRows = rowsByDailyId('operational_medical_cardiac', $medicalId);
$traumaRows  = rowsByDailyId('operational_medical_trauma', $medicalId);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة الأدمن — الملف التشغيلي</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#166534;
  --green2:#16a34a;
  --soft:#f0fdf4;
  --line:#e2e8f0;
  --bg:#f8fafc;
  --text:#111827;
  --muted:#64748b;
  --white:#fff;
  --red:#dc2626;
  --r:16px;
}
body{
  font-family:Arial,Tahoma,sans-serif;
  direction:rtl;
  background:var(--bg);
  color:var(--text);
  font-size:14px;
  line-height:1.7;
}
.wrap{width:100%;max-width:100%;padding:18px 24px;margin:0 auto}
.hero{
  background:#fff;border:1px solid var(--line);border-radius:var(--r);
  padding:18px 22px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px
}
.hero h1{font-size:22px;color:var(--green);font-weight:900}
.hero p{font-size:12px;color:var(--muted)}
.top-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 12px;background:var(--soft);border:1px solid #bbf7d0;color:var(--green);font-weight:800;font-size:12px}
.btn{
  height:36px;border:none;border-radius:999px;background:var(--green);color:#fff;
  padding:0 16px;font-family:inherit;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px
}
.btn.light{background:#fff;color:var(--green);border:1px solid var(--line)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}
.stat{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:14px}
.stat small{display:block;color:var(--muted);font-weight:800;font-size:11px}
.stat b{display:block;font-size:26px;color:var(--green);font-weight:900}
.tabs{
  background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:10px;
  display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;position:sticky;top:0;z-index:10
}
.tab{
  border:1px solid var(--line);background:#fff;color:var(--muted);border-radius:999px;
  height:36px;padding:0 14px;font-family:inherit;font-weight:800;cursor:pointer
}
.tab.active{background:var(--green);color:#fff;border-color:var(--green)}
.panel{display:none}
.panel.active{display:block}
.card{
  background:#fff;border:1px solid var(--line);border-radius:var(--r);margin-bottom:14px;overflow:hidden
}
.card-head{padding:14px 18px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.card-title{font-weight:900;color:var(--green);display:flex;align-items:center;gap:8px}
.card-body{padding:16px}
.empty{padding:18px;border:1px dashed var(--line);border-radius:12px;background:#f8fafc;color:var(--muted);text-align:center;font-weight:700}
.tbl-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}
table{width:100%;border-collapse:collapse;background:#fff}
th{background:var(--green);color:#fff;padding:10px;font-size:12px;text-align:center;white-space:nowrap}
td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:center;font-size:12px}
tbody tr:hover td{background:var(--soft)}
.med-circles-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:14px;margin-bottom:20px}
.med-circle{text-align:center;display:flex;flex-direction:column;align-items:center}
.med-circle-ring{
  width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:34px;color:#fff;box-shadow:0 8px 18px rgba(0,0,0,.15);border:4px solid rgba(255,255,255,.45)
}
.med-circle-label{
  width:102px;min-height:32px;display:flex;align-items:center;justify-content:center;
  margin-top:-2px;border-radius:8px;padding:5px 6px;font-size:11px;font-weight:900;color:#fff;line-height:1.3
}
.med-circle-value{
  width:102px;border:none;border-radius:0 0 8px 8px;padding:7px 4px;text-align:center;font-family:inherit;font-size:12px;font-weight:900;background:#f8fafc;color:#475569
}
.mc-red .med-circle-ring{background:radial-gradient(circle at 35% 35%,#ef4444,#991b1b)}
.mc-red .med-circle-label{background:#991b1b}
.mc-yellow .med-circle-ring{background:radial-gradient(circle at 35% 35%,#fbbf24,#b45309)}
.mc-yellow .med-circle-label{background:#b45309}
.mc-green .med-circle-ring{background:radial-gradient(circle at 35% 35%,#22c55e,#15803d)}
.mc-green .med-circle-label{background:#15803d}
.mc-blue .med-circle-ring{background:radial-gradient(circle at 35% 35%,#3b82f6,#1d4ed8)}
.mc-blue .med-circle-label{background:#1d4ed8}
.mc-orange .med-circle-ring{background:radial-gradient(circle at 35% 35%,#f97316,#c2410c)}
.mc-orange .med-circle-label{background:#c2410c}
.mc-purple .med-circle-ring{background:radial-gradient(circle at 35% 35%,#a855f7,#7e22ce)}
.mc-purple .med-circle-label{background:#7e22ce}
.mc-teal .med-circle-ring{background:radial-gradient(circle at 35% 35%,#14b8a6,#0f766e)}
.mc-teal .med-circle-label{background:#0f766e}
.section-title{
  margin:16px 0 10px;background:var(--soft);border:1px solid #bbf7d0;color:var(--green);
  padding:9px 12px;border-radius:12px;text-align:center;font-weight:900
}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.note-form{display:grid;grid-template-columns:260px 1fr auto;gap:10px;margin-bottom:12px}
select,textarea,input{
  font-family:inherit;border:1px solid var(--line);border-radius:12px;padding:9px 12px;background:#fff
}
textarea{min-height:44px;resize:vertical}
.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:var(--red)}
.status-dot.ok{background:var(--green2)}
.dept-list{display:grid;gap:8px}
.dept-row{
  display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);
  border-radius:14px;padding:12px 14px
}
.dept-row.sent{background:var(--soft);border-color:#bbf7d0}
.dept-name{font-weight:900;flex:1}
.dept-time{font-size:12px;color:var(--muted)}
@media(max-width:1100px){
  .stats,.grid2{grid-template-columns:1fr}
  .med-circles-grid{grid-template-columns:repeat(3,1fr)}
  .note-form{grid-template-columns:1fr}
}
@media(max-width:600px){
  .med-circles-grid{grid-template-columns:repeat(2,1fr)}
  .wrap{padding:12px}
}
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <div>
      <h1>لوحة الأدمن — الملف التشغيلي</h1>
      <p>الصفحة تقرأ مباشرة من جداول الأقسام المنفصلة وتعرض آخر بيانات محفوظة.</p>
    </div>
    <div class="top-actions">
  <span class="badge">آخر إرسال: <?= h($lastSent) ?></span>
  <span class="badge">👤 <?= h($_SESSION['op_full_name'] ?? 'أدمن') ?></span>

  <a class="btn" href="operational_presentation.php" target="_blank">
    عرض بوربوينت
  </a>

  <a class="btn light" href="operational_users.php">👥 إدارة المستخدمين</a>

  <a class="btn light" href="<?= h($_SERVER['PHP_SELF']) ?>">تحديث</a>

  <a class="btn light" href="operational_logout.php" style="color:#dc2626">🚪 خروج</a>
</div>
  </div>

  <div class="stats">
    <div class="stat"><small>أقسام مرسلة اليوم</small><b><?= (int)$todayCount ?></b></div>
    <div class="stat"><small>إجمالي الأقسام</small><b><?= count($departments) ?></b></div>
    <div class="stat"><small>الملاحظات</small><b><?= count($notes) ?></b></div>
    <div class="stat"><small>السنة</small><b><?= h($year) ?></b></div>
  </div>

  <div class="tabs">
    <button class="tab active" data-panel="today">نموذج اليوم</button>
    <?php foreach($departments as $name => $info): ?>
      <button class="tab" data-panel="<?= h($info['key']) ?>"><?= h($info['icon'] . ' ' . $name) ?></button>
    <?php endforeach; ?>
    <button class="tab" data-panel="notes">الملاحظات</button>
  </div>

  <section class="panel active" id="panel-today">
    <div class="card">
      <div class="card-head"><div class="card-title">📌 حالة إرسال الأقسام اليوم</div></div>
      <div class="card-body">
        <div class="dept-list">
          <?php foreach($departments as $name => $info): $st = $deptStatus[$name] ?? []; ?>
            <div class="dept-row <?= !empty($st['is_today']) ? 'sent' : '' ?>">
              <span class="status-dot <?= !empty($st['is_today']) ? 'ok' : '' ?>"></span>
              <div class="dept-name"><?= h($info['icon'] . ' ' . $name) ?></div>
              <div class="dept-time"><?= h($st['sent_at'] ?: 'لا يوجد') ?></div>
              <button class="btn light" type="button" onclick="openPanel('<?= h($info['key']) ?>')">عرض</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="panel" id="panel-medical">
    <div class="card">
      <div class="card-head">
        <div class="card-title">🏥 إدارة الشؤون الطبية</div>
        <span class="badge">وقت الإرسال: <?= h($medical['created_at'] ?? 'لا يوجد') ?></span>
      </div>
      <div class="card-body">

        <div class="med-circles-grid">
          <?php
          $medicalItems = [
            ['cpr','حالات CPR','🫁','mc-teal'],
            ['occupational_health','الصحة المهنية','🩺','mc-purple'],
            ['stroke','حالات السكتة الدماغية','🧠','mc-orange'],
            ['cath_cases','الحالات المنقولة لمركز القسطرة','🫀','mc-blue'],
            ['aspirin','مؤشر استخدام الأسبرين','💊','mc-green'],
            ['ecg','مؤشر تخطيط القلب','❤️','mc-yellow'],
            ['trauma_path','مسار الإصابات','🚑','mc-red'],
          ];
          foreach($medicalItems as [$key,$label,$icon,$cls]):
              $val = trim((string)($medical[$key] ?? ''));
              if ($val === '') $val = 'لا توجد حالات';
          ?>
            <div class="med-circle <?= h($cls) ?>">
              <div class="med-circle-ring"><?= h($icon) ?></div>
              <div class="med-circle-label"><?= h($label) ?></div>
              <input class="med-circle-value" value="<?= h($val) ?>" readonly>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="section-title">حالات توقف القلب والتنفس</div>
        <?php renderGenericTable($cardiacRows, 'لا توجد بيانات لحالات توقف القلب والتنفس'); ?>

        <div class="section-title">حالات عدم تفعيل مسار الإصابات</div>
        <?php renderGenericTable($traumaRows, 'لا توجد بيانات لمسار الإصابات'); ?>

        <div class="section-title">آخر سجل رئيسي من operational_medical_daily</div>
        <?php renderGenericTable($medical ? [$medical] : [], 'لا توجد بيانات محفوظة لإدارة الشؤون الطبية'); ?>

      </div>
    </div>
  </section>

  <?php foreach($departments as $name => $info): ?>
    <?php if($info['key'] === 'medical') continue; ?>
    <section class="panel" id="panel-<?= h($info['key']) ?>">
      <div class="card">
        <div class="card-head">
          <div class="card-title"><?= h($info['icon'] . ' ' . $name) ?></div>
          <span class="badge">قراءة من الجداول المنفصلة</span>
        </div>
        <div class="card-body">
          <?php if ($info['key'] === 'sectors'): ?>
            <?php renderSectorsPanel(); ?>
          <?php else: ?>
            <?php foreach($info['tables'] as $table): ?>
              <div class="section-title"><?= h($table) ?></div>
              <?php
                if (!tableExists($table)) {
                    echo '<div class="empty">الجدول غير موجود في قاعدة البيانات: ' . h($table) . '</div>';
                } else {
                    $rows = latestRows($table, 20, $name);
                    renderGenericTable($rows, 'لا توجد بيانات محفوظة في هذا الجدول');
                }
              ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="panel" id="panel-notes">
    <div class="card">
      <div class="card-head"><div class="card-title">💡 الملاحظات والتوصيات</div></div>
      <div class="card-body">
        <form method="post" class="note-form">
          <input type="hidden" name="save_admin_recommendation" value="1">
          <select name="department" required>
            <option value="">اختر القسم</option>
            <?php foreach(array_keys($departments) as $d): ?>
              <option value="<?= h($d) ?>"><?= h($d) ?></option>
            <?php endforeach; ?>
          </select>
          <textarea name="recommendation_text" placeholder="اكتب التوصية هنا..." required></textarea>
          <button class="btn" type="submit">حفظ التوصية</button>
        </form>

        <?php renderGenericTable($notes, 'لا توجد ملاحظات أو توصيات'); ?>
      </div>
    </div>
  </section>

</div>

<script>
function openPanel(panel){
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));

  const target = document.getElementById('panel-' + panel);
  if(target) target.classList.add('active');

  const btn = document.querySelector('.tab[data-panel="' + panel + '"]');
  if(btn) btn.classList.add('active');

  window.scrollTo({top:0, behavior:'smooth'});
}

document.querySelectorAll('.tab').forEach(btn => {
  btn.addEventListener('click', () => openPanel(btn.dataset.panel));
});
</script>
</body>
</html>
