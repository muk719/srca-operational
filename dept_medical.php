<?php
$REQUIRED_DEPT = 'إدارة الشؤون الطبية';
$DEPT_ICON  = '🏥';
$DEPT_TITLE = 'إدارة الشؤون الطبية';
$DEPT_COLOR = '#dc2626';
$DEPT_BG    = '#fef2f2';

require_once __DIR__ . '/_base.php';
$department = $_SESSION['op_department'] ?? $REQUIRED_DEPT;
$userName   = $_SESSION['op_full_name'] ?? '';
$msg        = '';
// ===== حفظ البيانات =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_medical'])) {
    $notes = trim($_POST['notes'] ?? '');
    try {
        $stmt = pdo()->prepare("
            INSERT INTO operational_medical_daily
            (user_id,department,report_date,trauma_path,ecg,aspirin,cath_cases,stroke,occupational_health,cpr,notes)
            VALUES (?,?,CURDATE(),?,?,?,?,?,?,?,?)
        ");
      $stmt->execute([
    $_SESSION['op_user_id'], 
    $department,

    trim($_POST['medical']['trauma_path'] ?? '') !== '' 
        ? trim($_POST['medical']['trauma_path']) 
        : 'لا توجد حالات',

    trim($_POST['medical']['ecg'] ?? '') !== '' 
        ? trim($_POST['medical']['ecg']) 
        : 'لا توجد حالات',

    trim($_POST['medical']['aspirin'] ?? '') !== '' 
        ? trim($_POST['medical']['aspirin']) 
        : 'لا توجد حالات',

    trim($_POST['medical']['cath_cases'] ?? '') !== '' 
        ? trim($_POST['medical']['cath_cases']) 
        : 'لا توجد حالات',

    trim($_POST['medical']['stroke'] ?? '') !== '' 
        ? trim($_POST['medical']['stroke']) 
        : 'لا توجد حالات',

    trim($_POST['medical']['occupational_health'] ?? '') !== '' 
        ? trim($_POST['medical']['occupational_health']) 
        : 'لا توجد حالات',

    trim($_POST['medical']['cpr'] ?? '') !== '' 
        ? trim($_POST['medical']['cpr']) 
        : 'لا توجد حالات',

    $notes
]);
        $dailyId = pdo()->lastInsertId();

        if (!empty($_POST['cardiac']) && is_array($_POST['cardiac'])) {
            foreach ($_POST['cardiac'] as $row) {
                if (!array_filter(array_map('trim',$row))) continue;
                pdo()->prepare("INSERT INTO operational_medical_cardiac (daily_id,team_type,center,protocol_applied,teams_count,rosc) VALUES (?,?,?,?,?,?)")
                     ->execute([$dailyId,trim($row['type']??''),trim($row['center']??''),trim($row['proto']??''),(int)($row['teams']??0),trim($row['rosc']??'')]);
            }
        }
        if (!empty($_POST['trauma']) && is_array($_POST['trauma'])) {
            foreach ($_POST['trauma'] as $row) {
                if (!array_filter(array_map('trim',$row))) continue;
                pdo()->prepare("INSERT INTO operational_medical_trauma (daily_id,center,case_classification,hospital,reason) VALUES (?,?,?,?,?)")
                     ->execute([$dailyId,trim($row['center']??''),trim($row['classify']??''),trim($row['hospital']??''),trim($row['reason']??'')]);
            }
        }

        // رد تلقائي على الملاحظات إن وجدت
        if (!empty($_POST['note_reply']) && trim($_POST['note_reply']) !== '') {
            try {
                pdo()->prepare("UPDATE operational_notes SET department_reply=?, replied_at=NOW(), is_read=1 WHERE department=? AND (is_read=0 OR is_read IS NULL)")
                     ->execute([trim($_POST['note_reply']), $department]);
            } catch(Throwable $e){}
        }

        $msg = "✅ تم حفظ بيانات إدارة الشؤون الطبية وإرسالها للأدمن";
    } catch(Throwable $e) {
        $msg = "⚠️ خطأ في الحفظ: " . $e->getMessage();
    }
}

// ===== جلب الملاحظات غير المقروءة =====
$unreadNotes = [];
try {
    $stmt = pdo()->prepare("
        SELECT * FROM operational_notes
        WHERE department = ? AND (is_read = 0 OR is_read IS NULL)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$department]);
    $unreadNotes = $stmt->fetchAll();
} catch(Throwable $e){ $unreadNotes = []; }

if (isset($_GET['mark_read'])) {
    try {
        pdo()->prepare("UPDATE operational_notes SET is_read=1 WHERE department=?")->execute([$department]);
        header("Location: ".$_SERVER['PHP_SELF']); exit;
    } catch(Throwable $e){}
}

// ===== آخر 5 إرسالات سابقة =====
$prevEntries = [];
try {
    $stmt = pdo()->prepare("
        SELECT report_date, trauma_path, ecg, aspirin, cath_cases, stroke, occupational_health, cpr, notes, created_at
        FROM operational_medical_daily
        WHERE department = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$department]);
    $prevEntries = $stmt->fetchAll();
} catch(Throwable $e){ $prevEntries = []; }

// حساب الوقت المتبقي للرد (24 ساعة)
if (!function_exists('hoursLeft')) {
    function hoursLeft($createdAt) {
        $diff = strtotime($createdAt) + 86400 - time();
        if($diff <= 0) return null;
        $h = floor($diff/3600);
        $m = floor(($diff%3600)/60);
        return "{$h}س {$m}د";
    }
}
$months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة الشؤون الطبية — الملف التشغيلي</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--accent:#dc2626;--line:#e5e7eb;--bg:#f3f4f6;--dark:#111827;--gray:#6b7280;--white:#fff;--r:14px}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:var(--bg);color:var(--dark);direction:rtl;font-size:14px}
.wrap{
    width:100%;
    max-width:100%;
    margin:0;
    padding:20px;
}
/* ===== HEADER أبيض ===== */
.top-bar{
  background:#fff;border-bottom:1px solid var(--line);
  padding:0 24px;height:60px;display:flex;align-items:center;
  justify-content:space-between;position:sticky;top:0;z-index:50;
  box-shadow:0 2px 8px rgba(0,0,0,.06)
}
.brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:38px;height:38px;border-radius:10px;background:#fef2f2;border:1px solid #fca5a5;display:flex;align-items:center;justify-content:center;font-size:20px}
.brand-name{font-size:15px;font-weight:900;color:var(--dark)}
.brand-sub{font-size:11px;color:var(--gray)}
.header-right{display:flex;align-items:center;gap:8px}
.user-pill{background:#f3f4f6;border:1px solid var(--line);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;color:var(--dark)}
.logout-btn{background:#fef2f2;border:1px solid #fca5a5;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;color:#dc2626;text-decoration:none}
.logout-btn:hover{background:#fee2e2}
.notif-badge{background:#dc2626;color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:800;cursor:pointer;animation:pulse-red 1.5s infinite}
@keyframes pulse-red{0%,100%{opacity:1}50%{opacity:.6}}

/* ===== تبويبات ===== */
.tabs{display:flex;gap:6px;margin-bottom:16px;border-bottom:2px solid var(--line);padding-bottom:0}
.tab-btn{height:38px;padding:0 18px;border:none;background:transparent;font-family:inherit;font-size:13px;font-weight:700;color:var(--gray);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .15s}
.tab-btn.active{color:#dc2626;border-bottom-color:#dc2626}
.tab-btn:hover:not(.active){color:var(--dark)}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* ===== البطاقات ===== */
.card{background:var(--white);border:1px solid var(--line);border-radius:var(--r);margin-bottom:14px;overflow:hidden}
.card-head{padding:11px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;background:#fff}
.card-title{font-size:14px;font-weight:900;color:var(--dark);display:flex;align-items:center;gap:7px}
.card-body{padding:16px}

/* ===== إشعار الملاحظات ===== */
.notif-box{
  background:linear-gradient(135deg,#fefce8,#fef9c3);
  border:2px solid #f59e0b;border-radius:14px;padding:16px 18px;
  margin-bottom:16px;animation:pulse-border 2s infinite
}
@keyframes pulse-border{0%,100%{border-color:#f59e0b}50%{border-color:#dc2626}}
@keyframes bell{0%,100%{transform:rotate(0)}25%{transform:rotate(15deg)}75%{transform:rotate(-15deg)}}
.notif-header{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.notif-bell{font-size:26px;animation:bell 1s ease-in-out infinite}
.notif-title{font-size:14px;font-weight:900;color:#92400e}
.notif-note{background:rgba(0,0,0,.06);border-radius:8px;padding:10px 14px;margin-bottom:8px}
.notif-note-text{font-size:13px;color:#78350f;font-weight:600;margin-bottom:5px}
.notif-note-meta{display:flex;align-items:center;gap:8px;font-size:11px;color:#a16207}
.timer-badge{background:#dc2626;color:#fff;border-radius:6px;padding:2px 8px;font-size:10px;font-weight:800}
.timer-badge.urgent{background:#f59e0b}
.reply-area{margin-top:8px}
.reply-area textarea{width:100%;border:1.5px solid #f59e0b;border-radius:8px;padding:8px 10px;font-family:inherit;font-size:13px;resize:none;outline:none;background:#fffbeb}
.reply-area textarea:focus{border-color:#dc2626}

/* ===== تاريخ اليوم ===== */
.date-banner{text-align:center;margin-bottom:18px}
.date-banner-inner{display:inline-flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 24px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.date-day{font-size:26px;font-weight:900;color:#dc2626}
.date-text{font-size:14px;font-weight:700;color:var(--dark)}
.date-badge{background:#dc2626;color:#fff;border-radius:8px;padding:4px 12px;font-size:12px;font-weight:800}

.circles-grid{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:nowrap;
    gap:20px;
}

.circle-ring{
    width:95px;
    height:95px;
}

.circle-input{
    width:95px;
}
.circle-ring{width:86px;height:86px;border-radius:50%;margin:0 auto;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.2);border:3px solid rgba(255,255,255,.3)}
.circle-ring span{font-size:26px;filter:drop-shadow(0 2px 3px rgba(0,0,0,.3))}
.circle-label{font-size:9px;font-weight:800;color:#fff;padding:3px 5px;border-radius:5px;margin:4px auto;max-width:86px;line-height:1.3;text-align:center}
.circle-input{width:86px;border:none;border-radius:0 0 8px 8px;background:rgba(0,0,0,.07);font-family:inherit;font-size:13px;font-weight:900;text-align:center;padding:5px 4px;outline:none}
.circle-input:focus{background:rgba(0,0,0,.12)}

/* ===== الجداول ===== */
.tbl-inp{width:100%;border:none;background:transparent;font-family:inherit;font-size:12px;color:#111827;padding:6px 8px;outline:none;border-radius:4px}
.tbl-inp:focus{background:#fef2f2;outline:1px solid #dc2626}
.tbl-inp::placeholder{color:#9ca3af;font-size:11px}

/* ===== السابقة ===== */
.prev-card{border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:10px}
.prev-head{background:#f8fafc;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line)}
.prev-date{font-size:13px;font-weight:800;color:var(--dark)}
.prev-time{font-size:11px;color:var(--gray)}
.prev-body{display:grid;grid-template-columns:repeat(4,1fr);gap:0}
@media(max-width:600px){.prev-body{grid-template-columns:repeat(2,1fr)}}
.prev-kpi{padding:10px 12px;border-left:1px solid var(--line);border-bottom:1px solid var(--line);text-align:center}
.prev-kpi:nth-child(4n){border-left:none}
.prev-kpi-val{font-size:18px;font-weight:900;color:#dc2626}
.prev-kpi-lbl{font-size:10px;color:var(--gray);font-weight:700;margin-top:2px}
.prev-notes{padding:8px 14px;font-size:12px;color:var(--gray);border-top:1px solid var(--line);background:#fafafa}

/* ===== شريط التقدم ===== */
.prog-bar{height:8px;background:#f3f4f6;border-radius:999px;overflow:hidden;margin:10px 0 4px}
.prog-fill{height:100%;background:linear-gradient(90deg,#dc2626,#f97316);border-radius:999px;width:0%;transition:width .3s}

/* ===== رسائل ===== */
.msg-ok{background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:10px;padding:11px 16px;font-weight:800;margin-bottom:14px;font-size:13px}
.msg-warn{background:#fefce8;color:#713f12;border:1px solid #fde68a;border-radius:10px;padding:11px 16px;font-weight:800;margin-bottom:14px;font-size:13px}

.btn-save{height:44px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;border-radius:999px;padding:0 28px;font-family:inherit;font-size:14px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn-save:hover{filter:brightness(1.08)}
.btn-outline{height:44px;background:#fff;color:var(--dark);border:1px solid var(--line);border-radius:999px;padding:0 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer}
.btn-outline:hover{background:#f3f4f6}
.btn-add{height:28px;border-radius:7px;padding:0 12px;border:none;color:#fff;font-family:inherit;font-size:11px;font-weight:800;cursor:pointer}
</style>
</head>
<body>

<!-- ===== HEADER أبيض ===== -->
<div class="top-bar">
  <div class="brand">
    <div class="brand-icon">🏥</div>
    <div>
      <div class="brand-name">إدارة الشؤون الطبية</div>
      <div class="brand-sub">الملف التشغيلي — هيئة الهلال الأحمر السعودي</div>
    </div>
  </div>
  <div class="header-right">
    <?php if(!empty($unreadNotes)): ?>
      <span class="notif-badge" onclick="switchTab('notes')">
        🔔 <?= count($unreadNotes) ?> ملاحظة جديدة
      </span>
    <?php endif; ?>
    <span class="user-pill">👤 <?= h($userName) ?></span>
    <a class="logout-btn" href="operational_logout.php">خروج</a>
  </div>
</div>

<div class="wrap">

  <?php if($msg): ?>
    <div class="<?= str_starts_with($msg,'✅')?'msg-ok':'msg-warn' ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- ===== تبويبات ===== -->
  <div class="tabs">
    <button class="tab-btn active" id="tab-today" onclick="switchTab('today')">📝 اليوم</button>
    <button class="tab-btn" id="tab-prev"  onclick="switchTab('prev')">📋 المرسلة سابقاً</button>
    <?php if(!empty($unreadNotes)): ?>
      <button class="tab-btn" id="tab-notes" onclick="switchTab('notes')">
        🔔 الملاحظات <span style="background:#dc2626;color:#fff;border-radius:999px;padding:1px 7px;font-size:10px"><?= count($unreadNotes) ?></span>
      </button>
    <?php endif; ?>
  </div>

  <!-- ===== تبويب اليوم ===== -->
  <div class="tab-panel active" id="panel-today">

    <!-- التاريخ -->
    <div class="date-banner">
      <div class="date-banner-inner">
        <div class="date-day"><?= date('j') ?></div>
        <div>
          <div class="date-text"><?= $months[date('n')-1] ?> <?= date('Y') ?>م</div>
          <div style="font-size:11px;color:var(--gray)"><?= ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][date('w')] ?></div>
        </div>
        <div class="date-badge">اليوم</div>
      </div>
    </div>

    <form method="post">
    <input type="hidden" name="save_medical" value="1">

    <!-- المؤشرات الدائرية -->
    <div class="card">
      <div class="card-head">
        <div class="card-title">
          <span style="width:7px;height:7px;border-radius:50%;background:#dc2626;display:inline-block"></span>
          المؤشرات الطبية اليومية
        </div>
      </div>
      <div class="card-body">
        <div class="circles-grid">
          <?php
          $circles = [
            ['key'=>'trauma_path',        'label'=>'مسار الإصابات',                             'icon'=>'🚗', 'grad'=>'radial-gradient(circle at 35% 35%,#ef4444,#991b1b)', 'lbg'=>'#991b1b'],
            ['key'=>'ecg',                'label'=>'مؤشر تخطيط القلب',                          'icon'=>'❤️', 'grad'=>'radial-gradient(circle at 35% 35%,#fbbf24,#b45309)', 'lbg'=>'#b45309'],
            ['key'=>'aspirin',            'label'=>'مؤشر استخدام الأسبرين',                     'icon'=>'💊', 'grad'=>'radial-gradient(circle at 35% 35%,#22c55e,#15803d)', 'lbg'=>'#15803d'],
            ['key'=>'cath_cases',         'label'=>'الحالات المنقولة لمركز القسطرة القلبية',    'icon'=>'🫀', 'grad'=>'radial-gradient(circle at 35% 35%,#3b82f6,#1d4ed8)', 'lbg'=>'#1d4ed8'],
            ['key'=>'stroke',             'label'=>'حالات السكتة الدماغية',                     'icon'=>'🧠', 'grad'=>'radial-gradient(circle at 35% 35%,#f97316,#c2410c)', 'lbg'=>'#c2410c'],
            ['key'=>'occupational_health','label'=>'الصحة المهنية',                              'icon'=>'🩺', 'grad'=>'radial-gradient(circle at 35% 35%,#a855f7,#7e22ce)', 'lbg'=>'#7e22ce'],
            ['key'=>'cpr',                'label'=>'حالات CPR',                                 'icon'=>'🫁', 'grad'=>'radial-gradient(circle at 35% 35%,#fbbf24,#b45309)', 'lbg'=>'#b45309'],
          ];
          foreach($circles as $c): ?>
          <div class="circle-item">
            <div class="circle-ring" style="background:<?= $c['grad'] ?>">
              <span><?= $c['icon'] ?></span>
            </div>
            <div class="circle-label" style="background:<?= $c['lbg'] ?>"><?= $c['label'] ?></div>
            <input class="circle-input" style="color:<?= $c['lbg'] ?>"
                   name="medical[<?= $c['key'] ?>]"
                   placeholder="لا توجد حالات"
                   onfocus="this.style.background='rgba(0,0,0,.12)'"
                   onblur="this.style.background='rgba(0,0,0,.07)'">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- جدول القلب والتنفس -->
    <div class="card">
      <div class="card-head">
        <div class="card-title" style="color:#991b1b;font-size:15px">حالات توقف القلب والتنفس</div>
        <button type="button" class="btn-add" style="background:#1d4ed8" onclick="addCardiacRow()">+ إضافة صف</button>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;font-size:11px;width:34px">#</th>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;font-size:11px">نوع الفرقة</th>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;font-size:11px">المركز الإسعافي</th>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;font-size:11px">تطبيق البروتوكول الطبي</th>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;font-size:11px">عدد الفرق الإسعافية</th>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;font-size:11px">ROSC عودة النبض</th>
              <th style="background:#1d4ed8;color:#fff;padding:8px 10px;width:34px"></th>
            </tr>
          </thead>
          <tbody id="cardiacTbody">
            <?php for($i=0;$i<2;$i++): ?>
            <tr>
              <td style="text-align:center;color:#9ca3af;font-weight:700;border-bottom:1px solid #e5e7eb"><?= $i+1 ?></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[<?=$i?>][type]"   class="tbl-inp" placeholder="نوع الفرقة"></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[<?=$i?>][center]" class="tbl-inp" placeholder="المركز"></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[<?=$i?>][proto]"  class="tbl-inp" placeholder="نعم / لا"></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[<?=$i?>][teams]"  class="tbl-inp" placeholder="العدد" type="number"></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[<?=$i?>][rosc]"   class="tbl-inp" placeholder="نعم / لا"></td>
              <td style="border-bottom:1px solid #e5e7eb;text-align:center">
                <button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:15px">✕</button>
              </td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- جدول عدم تفعيل مسار الإصابات -->
    <div class="card">
      <div class="card-head">
        <div class="card-title" style="color:#7e22ce;font-size:15px">حالات عدم تفعيل مسار الإصابات</div>
        <button type="button" class="btn-add" style="background:#7e22ce" onclick="addTraumaRow()">+ إضافة صف</button>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th style="background:#7e22ce;color:#fff;padding:8px 10px;font-size:11px;width:34px">#</th>
              <th style="background:#7e22ce;color:#fff;padding:8px 10px;font-size:11px">المركز الإسعافي</th>
              <th style="background:#7e22ce;color:#fff;padding:8px 10px;font-size:11px">تصنيف الحالة</th>
              <th style="background:#7e22ce;color:#fff;padding:8px 10px;font-size:11px">المستشفى المنقولة له الحالة</th>
              <th style="background:#7e22ce;color:#fff;padding:8px 10px;font-size:11px">سبب عدم تفعيل المسار</th>
              <th style="background:#7e22ce;color:#fff;padding:8px 10px;width:34px"></th>
            </tr>
          </thead>
          <tbody id="traumaTbody">
            <?php for($i=0;$i<2;$i++): ?>
            <tr>
              <td style="text-align:center;color:#9ca3af;font-weight:700;border-bottom:1px solid #e5e7eb"><?= $i+1 ?></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="trauma[<?=$i?>][center]"   class="tbl-inp" placeholder="المركز"></td>
              <td style="border-bottom:1px solid #e5e7eb">
                <select name="trauma[<?=$i?>][classify]" class="tbl-inp">
                  <option value="">التصنيف</option>
                  <option>كود أحمر</option><option>كود أصفر</option>
                  <option>الحرة</option><option>بدا</option>
                </select>
              </td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="trauma[<?=$i?>][hospital]" class="tbl-inp" placeholder="اسم المستشفى"></td>
              <td style="border-bottom:1px solid #e5e7eb"><input name="trauma[<?=$i?>][reason]"   class="tbl-inp" placeholder="سبب عدم التفعيل"></td>
              <td style="border-bottom:1px solid #e5e7eb;text-align:center">
                <button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:15px">✕</button>
              </td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ملاحظات + رد على الأدمن -->
    <div class="card">
      <div class="card-head">
        <div class="card-title">ملاحظات عامة</div>
      </div>
      <div class="card-body">
        <textarea name="notes" placeholder="أي ملاحظات أو معلومات إضافية للأدمن..."
          style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:12px;font-family:inherit;font-size:13px;min-height:80px;resize:vertical;outline:none"
          onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
        <?php if(!empty($unreadNotes)): ?>
          <div style="margin-top:10px">
            <label style="font-size:12px;font-weight:800;color:#92400e;display:block;margin-bottom:5px">↩️ ردّ على ملاحظات الأدمن</label>
            <textarea name="note_reply" placeholder="اكتب ردّك هنا..."
              style="width:100%;border:1.5px solid #f59e0b;border-radius:10px;padding:10px;font-family:inherit;font-size:13px;min-height:60px;resize:vertical;outline:none;background:#fffbeb"
              onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#f59e0b'"></textarea>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- شريط التقدم + حفظ -->
    <div class="card">
      <div class="card-body" style="padding:14px 18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
          <span style="font-size:12px;font-weight:700;color:var(--gray)">تقدم الإدخال</span>
          <span id="progTxt" style="font-size:12px;font-weight:800;color:#dc2626">0 حقل مكتمل</span>
        </div>
        <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="btn-save" type="submit">💾 حفظ وإرسال للأدمن</button>
          <button class="btn-outline" type="reset"
            onclick="document.getElementById('progFill').style.width='0%';document.getElementById('progTxt').textContent='0 حقل مكتمل'">
            مسح
          </button>
        </div>
      </div>
    </div>

    </form>
  </div><!-- /panel-today -->

  <!-- ===== تبويب المرسلة سابقاً ===== -->
  <div class="tab-panel" id="panel-prev">
    <?php if(empty($prevEntries)): ?>
      <div style="text-align:center;padding:40px;color:var(--gray)">
        <div style="font-size:40px;margin-bottom:10px">📭</div>
        <div style="font-size:15px;font-weight:700">لا توجد بيانات مرسلة سابقاً</div>
      </div>
    <?php else: ?>
      <?php foreach($prevEntries as $e):
        $dateStr = $months[(int)date('n',strtotime($e['report_date']))-1] . ' ' . date('Y',strtotime($e['report_date']));
        $dayStr  = date('j',strtotime($e['report_date']));
      ?>
      <div class="prev-card">
        <div class="prev-head">
          <div class="prev-date">
            📅 <?= $dayStr ?> <?= $dateStr ?>
          </div>
          <div class="prev-time"><?= h(substr($e['created_at'],11,5)) ?></div>
        </div>
        <div class="prev-body">
          <?php
          $kpis = [
            'مسار الإصابات'=>$e['trauma_path'],
            'تخطيط القلب'=>$e['ecg'],
            'الأسبرين'=>$e['aspirin'],
            'القسطرة'=>$e['cath_cases'],
            'السكتة الدماغية'=>$e['stroke'],
            'الصحة المهنية'=>$e['occupational_health'],
            'CPR'=>$e['cpr'],
          ];
          foreach($kpis as $lbl=>$val): ?>
          <div class="prev-kpi">
            <div class="prev-kpi-val"><?= (int)$val ?></div>
            <div class="prev-kpi-lbl"><?= h($lbl) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if($e['notes']): ?>
          <div class="prev-notes">📝 <?= h($e['notes']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div><!-- /panel-prev -->

  <!-- ===== تبويب الملاحظات ===== -->
  <?php if(!empty($unreadNotes)): ?>
  <div class="tab-panel" id="panel-notes">
    <div class="notif-box">
      <div class="notif-header">
        <div class="notif-bell">🔔</div>
        <div>
          <div class="notif-title">لديك <?= count($unreadNotes) ?> ملاحظة من الأدمن تتطلب الرد خلال 24 ساعة</div>
          <div style="font-size:11px;color:#a16207;margin-top:3px">يرجى الرد في أسرع وقت ممكن</div>
        </div>
        <a href="?mark_read=1" style="margin-right:auto;background:#f59e0b;color:#fff;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;text-decoration:none">تم القراءة ✓</a>
      </div>

      <?php foreach($unreadNotes as $n):
        $hl = hoursLeft($n['created_at']);
        $isUrgent = $hl && (int)explode('س',$hl)[0] < 6;
      ?>
      <div class="notif-note">
        <div class="notif-note-text">📋 <?= h($n['note_text'] ?? $n['message'] ?? '') ?></div>
        <div class="notif-note-meta">
          <span>🕐 <?= h(substr($n['created_at'],0,16)) ?></span>
          <?php if($hl): ?>
            <span class="timer-badge <?= $isUrgent?'urgent':'' ?>">
              ⏰ متبقي <?= $hl ?>
            </span>
          <?php else: ?>
            <span class="timer-badge" style="background:#dc2626">⚠️ انتهى وقت الرد</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <form method="post" style="margin-top:12px">
        <input type="hidden" name="save_medical" value="1">
        <div class="reply-area">
          <label style="font-size:12px;font-weight:800;color:#92400e;display:block;margin-bottom:5px">↩️ ردّك على الملاحظات:</label>
          <textarea name="note_reply" rows="3" placeholder="اكتب ردّك هنا..."></textarea>
        </div>
        <div style="margin-top:8px;display:flex;gap:8px">
          <button class="btn-save" type="submit" style="height:38px;font-size:13px">إرسال الرد</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /wrap -->

<style>
.tbl-inp{width:100%;border:none;background:transparent;font-family:inherit;font-size:12px;color:#111827;padding:6px 8px;outline:none;border-radius:4px}
.tbl-inp:focus{background:#fef2f2;outline:1px solid #dc2626}
.tbl-inp::placeholder{color:#9ca3af;font-size:11px}
select.tbl-inp{cursor:pointer}
</style>

<script>
let cardiacCount=2, traumaCount=2;

function switchTab(name){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('panel-'+name)?.classList.add('active');
  document.getElementById('tab-'+name)?.classList.add('active');
}

function addCardiacRow(){
  cardiacCount++;
  const tbody=document.getElementById('cardiacTbody');
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td style="text-align:center;color:#9ca3af;font-weight:700;border-bottom:1px solid #e5e7eb">${cardiacCount}</td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[${cardiacCount-1}][type]"   class="tbl-inp" placeholder="نوع الفرقة"></td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[${cardiacCount-1}][center]" class="tbl-inp" placeholder="المركز"></td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[${cardiacCount-1}][proto]"  class="tbl-inp" placeholder="نعم / لا"></td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[${cardiacCount-1}][teams]"  class="tbl-inp" placeholder="العدد" type="number"></td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="cardiac[${cardiacCount-1}][rosc]"   class="tbl-inp" placeholder="نعم / لا"></td>
    <td style="border-bottom:1px solid #e5e7eb;text-align:center"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:15px">✕</button></td>
  `;
  tbody.appendChild(tr); updateProgress();
}

function addTraumaRow(){
  traumaCount++;
  const tbody=document.getElementById('traumaTbody');
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td style="text-align:center;color:#9ca3af;font-weight:700;border-bottom:1px solid #e5e7eb">${traumaCount}</td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="trauma[${traumaCount-1}][center]"   class="tbl-inp" placeholder="المركز"></td>
    <td style="border-bottom:1px solid #e5e7eb">
      <select name="trauma[${traumaCount-1}][classify]" class="tbl-inp">
        <option value="">التصنيف</option>
        <option>كود أحمر</option><option>كود أصفر</option><option>الحرة</option><option>بدا</option>
      </select>
    </td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="trauma[${traumaCount-1}][hospital]" class="tbl-inp" placeholder="اسم المستشفى"></td>
    <td style="border-bottom:1px solid #e5e7eb"><input name="trauma[${traumaCount-1}][reason]"   class="tbl-inp" placeholder="سبب عدم التفعيل"></td>
    <td style="border-bottom:1px solid #e5e7eb;text-align:center"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:15px">✕</button></td>
  `;
  tbody.appendChild(tr); updateProgress();
}

function removeRow(btn){ btn.closest('tr').remove(); updateProgress(); }

function updateProgress(){
  const inputs=document.querySelectorAll('#panel-today form input.tbl-inp, #panel-today form input[name^="medical"], #panel-today form textarea[name="notes"]');
  const filled=Array.from(inputs).filter(el=>el.value.trim()!=='').length;
  const total=inputs.length;
  const pct=total?Math.round(filled/total*100):0;
  document.getElementById('progFill').style.width=pct+'%';
  document.getElementById('progTxt').textContent=filled+' / '+total+' حقل مكتمل';
}

document.querySelectorAll('form input,form textarea,form select').forEach(el=>el.addEventListener('input',updateProgress));
updateProgress();

// فتح تبويب الملاحظات تلقائياً إن وجدت
<?php if(!empty($unreadNotes)): ?>
// switchTab('notes'); // علّق هذا إذا تريد البدء بتبويب اليوم
<?php endif; ?>
</script>
</body>
</html>