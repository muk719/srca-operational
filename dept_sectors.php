<?php
$REQUIRED_DEPT = 'إدارة القطاعات';
$DEPT_ICON  = '📍';
$DEPT_TITLE = 'إدارة القطاعات';
$DEPT_COLOR = '#16a34a';
$DEPT_BG    = '#f0fdf4';

// تسجيل الدخول والجلسة
require_once __DIR__ . '/_base.php';
$department = $_SESSION['op_department'] ?? $REQUIRED_DEPT;

// إنشاء جدول القطاعات إن لم يوجد
try {
    pdo()->exec("CREATE TABLE IF NOT EXISTS operational_sectors_daily (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        department VARCHAR(120) NOT NULL DEFAULT 'إدارة القطاعات',
        data_json LONGTEXT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e) {}

// حفظ البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sectors'])) {
    try {
        $payload = $_POST;
        unset($payload['save_sectors']);

        // صفوف الجداول المرسلة من الجافاسكربت
        if (!empty($payload['rows_json'])) {
            $decoded = json_decode($payload['rows_json'], true);
            unset($payload['rows_json']);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        pdo()->prepare("
            INSERT INTO operational_sectors_daily
            (user_id, department, data_json, notes, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([
            $userId,
            $department,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            trim($_POST['notes'] ?? '')
        ]);

        $msg = "✅ تم حفظ بيانات إدارة القطاعات وإرسالها للأدمن";
    } catch(Throwable $e) {
        $msg = "⚠️ خطأ أثناء الحفظ: " . $e->getMessage();
    }
}

// التقارير المرسلة سابقاً
$prevReports = [];
try {
    $stmt = pdo()->prepare("
        SELECT id, created_at, notes
        FROM operational_sectors_daily
        WHERE department = ?
        ORDER BY id DESC
        LIMIT 15
    ");
    $stmt->execute([$department]);
    $prevReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

$months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة القطاعات — الملف التشغيلي</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--accent:#16a34a;--line:#e5e7eb;--bg:#f3f4f6;--dark:#111827;--gray:#6b7280;--white:#fff;--r:14px}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:var(--bg);color:var(--dark);direction:rtl;font-size:14px}
.wrap{max-width:1200px;margin:0 auto;padding:20px}

/* HEADER */
.top-bar{background:#fff;border-bottom:1px solid var(--line);padding:0 24px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:38px;height:38px;border-radius:10px;background:#f0fdf4;border:1px solid #86efac;display:flex;align-items:center;justify-content:center;font-size:20px}
.brand-name{font-size:15px;font-weight:900;color:var(--dark)}
.brand-sub{font-size:11px;color:var(--gray)}
.header-right{display:flex;align-items:center;gap:8px}
.user-pill{background:#f3f4f6;border:1px solid var(--line);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700}
.logout-btn{background:#fef2f2;border:1px solid #fca5a5;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;color:#dc2626;text-decoration:none}

/* TABS */
.tabs{display:flex;gap:6px;margin-bottom:16px;border-bottom:2px solid var(--line);padding-bottom:0}
.tab-btn{height:38px;padding:0 18px;border:none;background:transparent;font-family:inherit;font-size:13px;font-weight:700;color:var(--gray);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
.tab-btn.active{color:#16a34a;border-bottom-color:#16a34a}
.tab-panel{display:none}.tab-panel.active{display:block}

/* CARDS */
.card{background:#fff;border:1px solid var(--line);border-radius:var(--r);margin-bottom:14px;overflow:hidden}
.card-head{padding:11px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;background:#f8fafc}
.card-title{font-size:14px;font-weight:900;color:var(--dark);display:flex;align-items:center;gap:7px}
.card-dot{width:7px;height:7px;border-radius:50%;background:#16a34a;flex-shrink:0}
.card-body{padding:16px}

/* === صورة 1: شارتات 4 في 2x2 === */
.charts-4grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:700px){.charts-4grid{grid-template-columns:1fr}}
.chart-block{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:12px 14px}
.chart-label{font-size:13px;font-weight:900;color:#dc2626;text-align:center;margin-bottom:10px;line-height:1.4}
.chart-label.green{color:#16a34a}
canvas.chart-main{width:100%!important;display:block}

/* === صورة 2: بطاقات القطاعات === */
.sector-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1px solid var(--line);border-radius:var(--r);overflow:hidden}
@media(max-width:900px){.sector-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.sector-grid{grid-template-columns:1fr}}
.sec-card{border-left:1px solid var(--line);background:#fff}
.sec-card:last-child{border-left:none}
.sec-num{position:absolute;left:10px;top:8px;background:rgba(0,0,0,.15);color:#fff;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:900}
.sec-card-header{padding:9px 12px;font-size:14px;font-weight:900;text-align:center;color:#fff;position:relative}
.sec-map{height:160px;display:flex;align-items:center;justify-content:center;padding:8px;background:#fafafa;border-bottom:1px solid var(--line)}
.sec-map img{max-height:150px;max-width:100%;object-fit:contain}
.sec-kpis{padding:10px 12px;display:flex;flex-direction:column;gap:6px}
.sec-kpi-label{font-size:10px;color:var(--gray);font-weight:700;margin-bottom:2px;text-align:center;line-height:1.3}
.sec-kpi-val{font-size:16px;font-weight:900;text-align:center;color:#dc2626}
.sec-kpi-block{border-top:1px solid var(--line);padding-top:8px;margin-top:4px}

/* === صورة 3: غرفة التحكم === */
.ctrl-title-bar{background:transparent;text-align:center;font-size:14px;font-weight:900;color:#dc2626;padding:10px;border:2px solid #fca5a5;border-radius:8px;margin-bottom:12px}
.ctrl-section-title{font-size:13px;font-weight:900;color:#fff;padding:8px 14px;border-radius:8px 8px 0 0}
.ctrl-boxes-row{display:grid;border:1px solid var(--line);border-top:none;border-radius:0 0 8px 8px;overflow:hidden}
.ctrl-boxes-row.cols4{grid-template-columns:repeat(4,1fr)}
.ctrl-box{border-left:1px solid var(--line)}
.ctrl-box:last-child{border-left:none}
.ctrl-box-head{padding:7px 8px;font-size:11px;font-weight:900;color:#fff;text-align:center}
.ctrl-box-body{padding:10px 8px;min-height:48px;display:flex;align-items:center;justify-content:center}
.ctrl-box-body input{width:100%;border:none;background:transparent;font-family:inherit;font-size:13px;font-weight:700;text-align:center;outline:none;color:var(--dark)}
.ctrl-box-body input:focus{background:#f0fdf4;border-radius:4px}
.ctrl-pair{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:700px){.ctrl-pair{grid-template-columns:1fr}}

/* === صورة 3: جداول المشفى والبلاغات === */
.tbl-section-title{font-size:13px;font-weight:900;color:#dc2626;text-align:center;padding:8px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px 8px 0 0}
.tbl-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:var(--r);margin-bottom:14px}
.tbl-wrap table{width:100%;border-collapse:collapse;background:#fff}
.tbl-wrap th{background:#16a34a;color:#fff;padding:7px 8px;font-size:11px;font-weight:800;text-align:center;white-space:nowrap}
.tbl-wrap th.red{background:#dc2626}
.tbl-wrap th.blue{background:#2563eb}
.tbl-wrap th.orange{background:#f97316}
.tbl-wrap th.gray{background:#6b7280}
.tbl-wrap th.dark{background:#374151}
.tbl-wrap td{padding:6px 8px;border-bottom:1px solid var(--line);text-align:center;font-size:12px}
.tbl-wrap tbody tr:last-child td{border-bottom:none}
.tbl-wrap td input,.tbl-wrap td select{width:100%;border:none;background:transparent;font-family:inherit;font-size:12px;color:var(--dark);text-align:center;outline:none;padding:4px}
.tbl-wrap td input:focus,.tbl-wrap td select:focus{background:#f0fdf4;outline:1px solid #86efac;color:#166534;border-radius:4px}
.add-row{display:flex;align-items:center;gap:6px;padding:7px 12px;cursor:pointer;color:#16a34a;font-size:12px;font-weight:700;border-top:1px solid var(--line);background:#fff}
.add-row:hover{background:#f0fdf4}

/* === صورة 4: خطة التمركز === */
.tmz-title{text-align:center;margin-bottom:6px}
.tmz-title h2{font-size:20px;font-weight:900;color:#dc2626}
.tmz-title p{font-size:12px;color:var(--gray);margin-top:2px}

/* التايم لاين مثل الصورة: شريط ملون أفقي */
.tl-outer{overflow-x:auto;margin-bottom:14px}
.tl-strip{display:flex;align-items:stretch;border-radius:10px;overflow:hidden;border:1px solid var(--line);min-width:700px}
.tl-seg{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 4px;cursor:pointer;border-left:1px solid rgba(255,255,255,.3);position:relative}
.tl-seg:last-child{border-left:none}
.tl-seg-label{font-size:9px;font-weight:900;color:#fff;text-align:center;line-height:1.2;margin-bottom:4px}
.tl-seg-icon{font-size:18px}
.tl-seg-sub{font-size:8px;color:rgba(255,255,255,.8);text-align:center;margin-top:3px}
/* السهم */
.tl-arrow{position:absolute;bottom:-14px;left:50%;transform:translateX(-50%);width:0;height:0;border-left:10px solid transparent;border-right:10px solid transparent}

/* الشريط الأفقي بالسيارات أسفله */
.tl-cars{display:flex;justify-content:space-around;align-items:flex-end;padding:10px 0 4px;min-width:700px;position:relative}
.tl-car-stop{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1}
.tl-car-icon{font-size:20px}
.tl-car-label{font-size:9px;color:var(--gray);text-align:center;font-weight:700}
.tl-line{height:3px;flex:1;background:var(--line);align-self:center;margin:0 -2px;position:relative;z-index:-1}
.tl-cars-row{display:flex;align-items:center;min-width:700px}
.tl-dot-circle{width:12px;height:12px;border-radius:50%;border:2px solid;flex-shrink:0}

/* كتب KPI */
.kpi-green{background:#dcfce7!important;color:#166534!important;border-radius:6px!important;font-weight:900!important}
.kpi-orange{background:#ffedd5!important;color:#9a3412!important;border-radius:6px!important;font-weight:900!important}
.kpi-red{background:#fee2e2!important;color:#991b1b!important;border-radius:6px!important;font-weight:900!important}
.kpi-muted{background:#f8fafc!important;color:#64748b!important;border-radius:6px!important}

/* شريط تقدم + حفظ */
.prog-bar{height:8px;background:#f3f4f6;border-radius:999px;overflow:hidden;margin:10px 0 4px}
.prog-fill{height:100%;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:999px;width:0%;transition:width .3s}
.btn-save{height:44px;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border:none;border-radius:999px;padding:0 28px;font-family:inherit;font-size:14px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn-save:hover{filter:brightness(1.08)}
.btn-outline{height:44px;background:#fff;color:var(--dark);border:1px solid var(--line);border-radius:999px;padding:0 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer}
.msg-ok{background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:10px;padding:11px 16px;font-weight:800;margin-bottom:14px;font-size:13px}
.msg-warn{background:#fefce8;color:#713f12;border:1px solid #fde68a;border-radius:10px;padding:11px 16px;font-weight:800;margin-bottom:14px;font-size:13px}

/* التاريخ */
.date-banner{text-align:center;margin-bottom:18px}
.date-inner{display:inline-flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 24px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.date-day{font-size:26px;font-weight:900;color:#16a34a}
.date-text{font-size:14px;font-weight:700}

/* بلاغات القطاعات - أسفل الصفحة */
.report-meta{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:600px){.report-meta{grid-template-columns:1fr}}
.meta-box{background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:10px 12px}
.meta-box label{font-size:10px;font-weight:900;color:var(--gray);display:block;margin-bottom:4px}
.meta-box input{width:100%;border:none;background:transparent;font-family:inherit;font-size:13px;font-weight:700;outline:none}
.meta-box input:focus{color:#16a34a}

/* شعار 997 */
.logo997{display:flex;align-items:center;justify-content:flex-end;gap:6px;padding:6px 0;font-size:12px;color:var(--gray)}
.logo997 span{background:#dc2626;color:#fff;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:900}
</style>
</head>
<body>

<div class="top-bar">
  <div class="brand">
    <div class="brand-icon">📍</div>
    <div>
      <div class="brand-name">إدارة القطاعات</div>
      <div class="brand-sub">الملف التشغيلي — هيئة الهلال الأحمر السعودي</div>
    </div>
  </div>
  <div class="header-right">
    <span class="user-pill">👤 <?= htmlspecialchars($userName) ?></span>
    <a class="logout-btn" href="operational_logout.php">خروج</a>
  </div>
</div>

<div class="wrap">

  <div class="tabs">
    <button class="tab-btn active" id="tab-today" onclick="switchTab('today')">📝 اليوم</button>
    <button class="tab-btn" id="tab-prev" onclick="switchTab('prev')">📋 المرسلة سابقاً</button>
  </div>

  <!-- ===== تبويب اليوم ===== -->
  <div class="tab-panel active" id="panel-today">

    <?php if($msg): ?>
      <div class="<?= mb_strpos($msg,'✅')!==false ? 'msg-ok' : 'msg-warn' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if(!empty($unreadNotes)): ?>
      <div class="msg-warn">
        📩 ملاحظات من الأدمن:
        <?php foreach($unreadNotes as $n): ?>
          <div style="margin-top:6px;font-weight:700"><?= htmlspecialchars($n['note_text'] ?? $n['message'] ?? '') ?></div>
        <?php endforeach; ?>
        <a href="?mark_read=1" style="color:#166534;font-size:12px">تحديد كمقروءة ✓</a>
      </div>
    <?php endif; ?>

    <!-- التاريخ -->
    <div class="date-banner">
      <div class="date-inner">
        <div class="date-day"><?= date('j') ?></div>
        <div>
          <div class="date-text"><?= $months[date('n')-1] ?> <?= date('Y') ?>م</div>
          <div style="font-size:11px;color:var(--gray)"><?= ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][date('w')] ?></div>
        </div>
        <div style="background:#16a34a;color:#fff;border-radius:8px;padding:4px 12px;font-size:12px;font-weight:800">اليوم</div>
      </div>
    </div>

    <form method="post" id="mainForm" onsubmit="fillRowsJson()">
    <input type="hidden" name="save_sectors" value="1">
    <input type="hidden" name="rows_json" id="rowsJson">

    <!-- ===== مؤشرات الاستجابة حسب التصنيف والمنطقة ===== -->
    <?php
    $respGroups = [
      [
        'title' => 'داخل التجمعات السكانية — أكثر من 50 ألف نسمة',
        'color' => '#16a34a',
        'baseline' => 91, 'target' => 95,
        'rows' => [
          ['8 دقائق',  '(إيكو – دلتا توقف قلب وتنفس – كود 1)'],
          ['10 دقائق', '(دلتا – برافو – غير معروف – كود 2)'],
          ['15 دقيقة', '(تشارلي – برافو – كود 3)'],
        ],
      ],
      [
        'title' => 'الضواحي — من 5000 إلى 50 ألف نسمة',
        'color' => '#2563eb',
        'baseline' => 90, 'target' => 95,
        'rows' => [
          ['12 دقيقة', '(إيكو – دلتا توقف قلب وتنفس – كود 1)'],
          ['15 دقيقة', '(دلتا – برافو – غير معروف – كود 2)'],
          ['20 دقيقة', '(تشارلي – برافو – كود 3)'],
        ],
      ],
      [
        'title' => 'المناطق النائية — أقل من 5000 نسمة',
        'color' => '#f97316',
        'baseline' => 83, 'target' => 85,
        'rows' => [
          ['20 دقيقة', '(إيكو – دلتا توقف قلب وتنفس – كود 1)'],
          ['25 دقيقة', '(دلتا – برافو – غير معروف – كود 2)'],
          ['30 دقيقة', '(تشارلي – برافو – كود 3)'],
        ],
      ],
    ];
    ?>
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="card-dot"></span>مؤشرات نسبة البلاغات المستجاب لها خلال الزمن المستهدف</div>
      </div>
      <div class="card-body">

        <!-- فترة العرض -->
        <div class="report-meta" style="margin-bottom:14px">
          <div class="meta-box">
            <label>📅 الفترة من تاريخ</label>
            <input type="date" name="ind_date_from" value="<?= date('Y-m-01') ?>">
          </div>
          <div class="meta-box">
            <label>📅 إلى تاريخ</label>
            <input type="date" name="ind_date_to" value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <?php
        $respTables = [
          ['key' => 'p',   'title' => '📊 المؤشرات من تاريخ إلى تاريخ',            'bar' => '#2563eb'],
          ['key' => 'ytd', 'title' => '📊 المؤشرات من بداية السنة حتى تاريخه',      'bar' => '#f97316'],
        ];
        foreach($respTables as $t): ?>
        <div style="background:<?= $t['bar'] ?>;color:#fff;font-size:12px;font-weight:900;text-align:center;padding:8px;border-radius:8px 8px 0 0;margin-top:<?= $t['key']==='p'?'0':'16px' ?>">
          <?= $t['title'] ?>
        </div>
        <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:0">
          <table>
            <thead>
              <tr>
                <th style="width:24px">#</th>
                <th style="text-align:right">المؤشر</th>
                <th class="red">المستهدف</th>
                <th class="blue">إجمالي عدد البلاغات</th>
                <th class="blue">البلاغات المحققة</th>
                <th class="orange">النسبة</th>
              </tr>
            </thead>
            <tbody>
              <?php $ri = 0; foreach($respGroups as $g): ?>
              <tr>
                <td colspan="6" style="background:<?= $g['color'] ?>;color:#fff;font-weight:900;font-size:12px;text-align:center;padding:7px">
                  <?= htmlspecialchars($g['title']) ?>
                </td>
              </tr>
              <?php foreach($g['rows'] as [$time, $class]): $ri++; $k = $t['key'].'_'.$ri; ?>
              <tr>
                <td style="color:var(--gray);font-weight:700;font-size:11px"><?= $ri ?></td>
                <td style="text-align:right;font-size:11px;font-weight:700;line-height:1.6">
                  نسبة البلاغات المستجاب لها خلال <b style="color:<?= $g['color'] ?>"><?= $time ?></b>
                  للحالات المصنفة <?= htmlspecialchars($class) ?>
                </td>
                <td style="font-weight:800;color:#dc2626;font-size:11px"><?= $g['target'] ?>%</td>
                <td>
                  <input name="resp_total_<?= $k ?>" placeholder="0" inputmode="numeric" class="resp-total" data-row="<?= $k ?>"
                    style="font-weight:800;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:12px;padding:5px">
                </td>
                <td>
                  <input name="resp_ok_<?= $k ?>" placeholder="0" inputmode="numeric" class="resp-ok" data-row="<?= $k ?>"
                    style="font-weight:800;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:12px;padding:5px">
                </td>
                <td>
                  <input name="resp_pct_<?= $k ?>" placeholder="--%" readonly tabindex="-1" id="pct_<?= $k ?>"
                    data-base="<?= $g['baseline'] ?>" data-target="<?= $g['target'] ?>"
                    class="kpi-muted resp-pct"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:12px;padding:5px;border-radius:6px">
                </td>
              </tr>
              <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endforeach; ?>

        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;font-size:11px;font-weight:700;color:var(--gray)">
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#dcfce7;border:1px solid #86efac"></span> حقق المستهدف</span>
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#ffedd5;border:1px solid #fdba74"></span> فوق خط الأساس ودون المستهدف</span>
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#fee2e2;border:1px solid #fca5a5"></span> دون خط الأساس</span>
        </div>

      </div>
    </div>

    <!-- ===== مؤشرات الرحلة الإسعافية بمنطقة تبوك ===== -->
    <?php
    $tripRows = [
      ['انتظار الترحيل',                          '0:00:10'],
      ['زمن القبول',                              '0:00:11'],
      ['زمن التحرك',                              '0:01:00'],
      ['الزمن من التحرك إلى الوصول للموقع',       '0:06:50'],
      ['زمن الانتظار قبل المباشرة',               '0:01:06'],
      ['زمن تقديم الخدمة الاسعافية في الموقع',    '0:13:07'],
      ['زمن الوصول للمستشفى',                     '0:12:02'],
      ['زمن تسليم المريض',                        '90%'],
      ['زمن الاغلاق',                             '0:01:07'],
      ['زمن العودة للجاهزية',                     '0:01:08'],
    ];
    $curMonthName = $months[date('n')-1];
    ?>
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="card-dot"></span>مؤشرات الرحلة الإسعافية بمنطقة تبوك <?= date('d-m-Y') ?></div>
      </div>
      <div class="card-body">

        <div class="tbl-wrap" style="margin-bottom:14px">
          <table>
            <thead>
              <tr>
                <th style="width:24px">ت</th>
                <th style="text-align:right">اسم مؤشر الأداء</th>
                <th class="red">المستهدف خلال <?= date('Y') ?></th>
                <th>المحقق من بداية السنة حتى تاريخه</th>
                <th>المحقق خلال شهر <?= $curMonthName ?></th>
                <th class="dark">المحقق خلال 24 ساعة</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($tripRows as $ti => [$tname, $ttarget]): ?>
              <tr>
                <td style="color:var(--gray);font-weight:700;font-size:11px"><?= $ti+1 ?></td>
                <td style="text-align:right;font-size:11px;font-weight:800"><?= htmlspecialchars($tname) ?></td>
                <td style="font-weight:800;color:#dc2626;font-size:11px;background:#f0fdf4"><?= $ttarget ?></td>
                <td>
                  <input name="trip_ytd_<?= $ti ?>" placeholder="--" class="kpi-muted trip-val" data-target="<?= $ttarget ?>"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;padding:5px;border-radius:6px">
                </td>
                <td>
                  <input name="trip_month_<?= $ti ?>" placeholder="--" class="kpi-muted trip-val" data-target="<?= $ttarget ?>"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;padding:5px;border-radius:6px">
                </td>
                <td>
                  <input name="trip_day_<?= $ti ?>" placeholder="--" class="kpi-muted trip-val" data-target="<?= $ttarget ?>"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;padding:5px;border-radius:6px">
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- نسبة الاستجابة خلال 8 دقائق (عام المنطقة) -->
        <div class="tbl-wrap" style="margin-bottom:0">
          <table>
            <thead>
              <tr>
                <th style="width:24px">ت</th>
                <th style="text-align:right">اسم مؤشر الأداء</th>
                <th class="red">المستهدف</th>
                <th>المحقق من بداية السنة حتى تاريخه</th>
                <th>المحقق خلال شهر <?= $curMonthName ?></th>
                <th class="dark">المحقق خلال 24 ساعة</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="color:var(--gray);font-weight:700;font-size:11px">1</td>
                <td style="text-align:right;font-size:11px;font-weight:800">نسبة بلاغات الطوارئ التي تم الاستجابة لها خلال 8 دقائق ( عام المنطقة )</td>
                <td style="font-weight:800;color:#dc2626;font-size:11px;background:#f0fdf4">85%</td>
                <td>
                  <input name="trip8_ytd" placeholder="--%" class="kpi-muted trip-val" data-target="85%"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;padding:5px;border-radius:6px">
                </td>
                <td>
                  <input name="trip8_month" placeholder="--%" class="kpi-muted trip-val" data-target="85%"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;padding:5px;border-radius:6px">
                </td>
                <td>
                  <input name="trip8_day" placeholder="--%" class="kpi-muted trip-val" data-target="85%"
                    style="font-weight:900;text-align:center;border:none;outline:none;width:100%;font-family:inherit;font-size:11px;padding:5px;border-radius:6px">
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- ===== صورة 3: غرفة التحكم ===== -->
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="card-dot"></span>وضع غرفة التحكم العمليات التشغيلية/الاتصالات/الأعطال</div>
      </div>
      <div class="card-body">

        <!-- الدعم الفني -->
        <div class="ctrl-section-title" style="background:#dc2626">🔧 الدعم الفني</div>
        <div class="ctrl-boxes-row cols4" style="margin-bottom:14px;border:1px solid var(--line);border-top:none;border-radius:0 0 8px 8px">
          <div class="ctrl-box" style="border-left:none">
            <div class="ctrl-box-head" style="background:#16a34a">رقم طلب الدعم الفني</div>
            <div class="ctrl-box-body"><input name="sup1" placeholder="**"></div>
          </div>
          <div class="ctrl-box">
            <div class="ctrl-box-head" style="background:#f97316">سبب الدعم الفني</div>
            <div class="ctrl-box-body"><input name="sup2" placeholder="**"></div>
          </div>
          <div class="ctrl-box">
            <div class="ctrl-box-head" style="background:#6b7280">الإجراء المتخذ</div>
            <div class="ctrl-box-body"><input name="sup3" placeholder="**"></div>
          </div>
          <div class="ctrl-box">
            <div class="ctrl-box-head" style="background:#374151">الملاحظات</div>
            <div class="ctrl-box-body"><input name="sup4" placeholder="**"></div>
          </div>
        </div>

        <!-- البلاغات المتأثرة -->
        <div class="ctrl-section-title" style="background:#f97316">⚠️ البلاغات المتأثرة بعوامل استثنائية أو ظروف طارئة</div>
        <div class="ctrl-boxes-row cols4" style="margin-bottom:14px;border:1px solid var(--line);border-top:none;border-radius:0 0 8px 8px">
          <div class="ctrl-box" style="border-left:none">
            <div class="ctrl-box-head" style="background:#16a34a">رقم البلاغ</div>
            <div class="ctrl-box-body"><input name="inc1" placeholder="**"></div>
          </div>
          <div class="ctrl-box">
            <div class="ctrl-box-head" style="background:#2563eb">نوع الأثر</div>
            <div class="ctrl-box-body"><input name="inc2" placeholder="**"></div>
          </div>
          <div class="ctrl-box">
            <div class="ctrl-box-head" style="background:#f97316">الأثر</div>
            <div class="ctrl-box-body"><input name="inc3" placeholder="**"></div>
          </div>
          <div class="ctrl-box">
            <div class="ctrl-box-head" style="background:#6b7280">الإجراء</div>
            <div class="ctrl-box-body"><input name="inc4" placeholder="**"></div>
          </div>
        </div>

        <div class="logo997"><span>997</span> @mediasrca</div>

        <!-- جدول الشركات الإسعافية -->
        <div style="margin-top:14px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">

            <!-- عدد الشركات الإسعافية في القطاعات -->
            <div>
              <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center">عدد المركبات العاملة بالقطاعات</div>
              <table style="width:100%;border-collapse:collapse;border:1px solid var(--line);border-top:none;background:#fff">
                <thead>
                  <tr>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع تبوك</th>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع تيماء</th>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع نيوم</th>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع الساحل</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="text-align:center;padding:8px"><input name="veh_active_tabuk" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                    <td style="text-align:center;padding:8px"><input name="veh_active_tayma" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                    <td style="text-align:center;padding:8px"><input name="veh_active_neom" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                    <td style="text-align:center;padding:8px"><input name="veh_active_coast" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- عدد الشركات الإسعافية في المستشفيات -->
            <div>
              <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center">عدد المركبات الاحتياط بالقطاعات</div>
              <table style="width:100%;border-collapse:collapse;border:1px solid var(--line);border-top:none;background:#fff">
                <thead>
                  <tr>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع تبوك</th>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع تيماء</th>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع نيوم</th>
                    <th style="background:#16a34a;color:#fff;padding:6px;font-size:10px;text-align:center">قطاع الساحل</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="text-align:center;padding:8px"><input name="veh_backup_tabuk" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                    <td style="text-align:center;padding:8px"><input name="veh_backup_tayma" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                    <td style="text-align:center;padding:8px"><input name="veh_backup_neom" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                    <td style="text-align:center;padding:8px"><input name="veh_backup_coast" style="width:100%;border:none;text-align:center;font-family:inherit;font-size:13px;font-weight:700;outline:none" placeholder="0"></td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>

          <!-- ساعات الخروج عن الخدمة -->
          <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap">
            <span>ساعات الخروج عن الخدمة من تاريخ</span>
            <input type="date" name="out_date_from" value="<?= date('Y-m-d') ?>" style="border:none;border-radius:6px;padding:2px 6px;font-family:inherit;font-size:11px;font-weight:700">
            <span>إلى</span>
            <input type="date" name="out_date_to" value="<?= date('Y-m-d') ?>" style="border:none;border-radius:6px;padding:2px 6px;font-family:inherit;font-size:11px;font-weight:700">
          </div>
          <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:0">
            <table>
              <thead>
                <tr>
                  <th>ساعة الخروج عن الخدمة</th>
                  <th class="blue">ساعة عودة الخدمة</th>
                  <th class="orange">الفترة</th>
                  <th class="gray">أسباب الخروج عن الخدمة</th>
                  <th class="dark">القطاع</th>
                </tr>
              </thead>
              <tbody id="outBody">
                <tr><td colspan="5"><div style="text-align:center;padding:10px;color:var(--gray);font-size:12px">انقر للإضافة</div></td></tr>
              </tbody>
            </table>
            <div class="add-row" onclick="addOutRow()">+ إضافة صف</div>
          </div>

          <!-- تمركز الفرق الإسعافية -->
          <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:14px">
            <span>تمركز الفرق الإسعافية من</span>
            <input type="date" name="tmz_date_from" value="<?= date('Y-m-d') ?>" style="border:none;border-radius:6px;padding:2px 6px;font-family:inherit;font-size:11px;font-weight:700">
            <span>إلى</span>
            <input type="date" name="tmz_date_to" value="<?= date('Y-m-d') ?>" style="border:none;border-radius:6px;padding:2px 6px;font-family:inherit;font-size:11px;font-weight:700">
          </div>
          <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:0">
            <table>
              <thead>
                <tr>
                  <th style="width:30%">عدد حالات التمركز</th>
                  <th>أماكن التمركز</th>
                </tr>
              </thead>
              <tbody id="tmzBody">
                <tr><td colspan="2"><div style="text-align:center;padding:10px;color:var(--gray);font-size:12px">انقر للإضافة</div></td></tr>
              </tbody>
            </table>
            <div class="add-row" onclick="addTmzRow()">+ إضافة صف</div>
          </div>

          <!-- العهد الطبية في المستشفيات -->
          <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center;margin-top:14px">
            العهد الطبية في المستشفيات
          </div>
          <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:0">
            <table>
              <thead>
                <tr>
                  <th style="width:40%">اسم الصنف</th>
                  <th class="gray">الملاحظة</th>
                </tr>
              </thead>
              <tbody id="custodyBody"></tbody>
            </table>
            <div class="add-row" onclick="addCustodyRow()">+ إضافة صنف</div>
          </div>
        </div>

        <!-- التحديات في زمن تسليم الحالة للمستشفى -->
        <div style="margin-top:12px">
          <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center">
            التحديات في زمن تسليم الحالة للمستشفى
          </div>
          <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:0">
            <table>
              <thead>
                <tr>
                  <th style="width:30%">رقم البلاغ</th>
                  <th class="gray">التحدي</th>
                </tr>
              </thead>
              <tbody id="challengeBody">
                <tr><td colspan="2"><div style="text-align:center;padding:10px;color:var(--gray);font-size:12px">انقر للإضافة</div></td></tr>
              </tbody>
            </table>
            <div class="add-row" onclick="addChallengeRow()">+ إضافة بلاغ</div>
          </div>
        </div>

        <!-- مؤشر تسليم الحالة حتى مغادرة المستشفى -->
        <div style="margin-top:12px">
          <div style="background:#dc2626;color:#fff;font-size:11px;font-weight:900;padding:7px 10px;border-radius:8px 8px 0 0;text-align:center">
            مؤشر تسليم الحالة حتى مغادرة المستشفى
          </div>
          <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:0">
            <table>
              <thead>
                <tr>
                  <th style="width:50%">التاريخ</th>
                  <th class="gray">القيمة المحققة</th>
                </tr>
              </thead>
              <tbody id="handoverBody"></tbody>
            </table>
            <div class="add-row" onclick="addHandoverRow()">+ إضافة صف</div>
          </div>
        </div>

        <div class="logo997" style="margin-top:8px"><span>997</span> @mediasrca</div>
      </div>
    </div>

    <!-- ملاحظات -->
    <div class="card">
      <div class="card-head"><div class="card-title"><span class="card-dot"></span>ملاحظات عامة</div></div>
      <div class="card-body">
        <textarea name="notes" placeholder="أي ملاحظات تشغيلية إضافية..."
          style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:12px;font-family:inherit;font-size:13px;min-height:80px;resize:vertical;outline:none"
          onfocus="this.style.borderColor='#16a34a'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
      </div>
    </div>

    <!-- حفظ -->
    <div class="card">
      <div class="card-body" style="padding:14px 18px">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="font-size:12px;font-weight:700;color:var(--gray)">تقدم الإدخال</span>
          <span id="progTxt" style="font-size:12px;font-weight:800;color:#16a34a">0 حقل مكتمل</span>
        </div>
        <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="btn-save" type="submit">💾 حفظ وإرسال للأدمن</button>
          <button class="btn-outline" type="reset">مسح</button>
        </div>
      </div>
    </div>

    </form>
  </div><!-- /panel-today -->

  <!-- ===== تبويب المرسلة سابقاً ===== -->
  <div class="tab-panel" id="panel-prev">
    <?php if(empty($prevReports)): ?>
    <div style="text-align:center;padding:40px;color:var(--gray)">
      <div style="font-size:40px;margin-bottom:10px">📭</div>
      <div style="font-size:15px;font-weight:700">لا توجد بيانات مرسلة</div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-head"><div class="card-title"><span class="card-dot"></span>التقارير المرسلة</div></div>
      <div class="card-body" style="padding:0">
        <div class="tbl-wrap" style="border:none;margin:0;border-radius:0">
          <table>
            <thead><tr><th style="width:30px">#</th><th>تاريخ الإرسال</th><th>ملاحظات</th></tr></thead>
            <tbody>
              <?php foreach($prevReports as $i => $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td style="font-weight:700"><?= htmlspecialchars($r['created_at']) ?></td>
                <td><?= htmlspecialchars(mb_substr($r['notes'] ?? '', 0, 80)) ?: '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /wrap -->

<script>
function switchTab(name){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('panel-'+name)?.classList.add('active');
  document.getElementById('tab-'+name)?.classList.add('active');
}

// ===== مؤشرات الاستجابة: حساب النسبة تلقائياً + التلوين =====
function colorRespInput(el){
  const v = parseFloat(String(el.value||'').replace('%',''));
  const base = parseFloat(el.dataset.base);
  const target = parseFloat(el.dataset.target);
  el.classList.remove('kpi-green','kpi-orange','kpi-red','kpi-muted');
  if(isNaN(v)){ el.classList.add('kpi-muted'); return; }
  if(v >= target)      el.classList.add('kpi-green');
  else if(v >= base)   el.classList.add('kpi-orange');
  else                 el.classList.add('kpi-red');
}

function calcRespRow(key){
  const total = parseFloat(document.querySelector(`.resp-total[data-row="${key}"]`)?.value);
  const ok    = parseFloat(document.querySelector(`.resp-ok[data-row="${key}"]`)?.value);
  const pctEl = document.getElementById('pct_'+key);
  if(!pctEl) return;
  if(isNaN(total) || total <= 0 || isNaN(ok)){
    pctEl.value = '';
  } else {
    pctEl.value = (ok/total*100).toFixed(2) + '%';
  }
  colorRespInput(pctEl);
}

document.querySelectorAll('.resp-total,.resp-ok').forEach(el=>{
  el.addEventListener('input', ()=>calcRespRow(el.dataset.row));
});
document.querySelectorAll('.resp-pct').forEach(colorRespInput);

// ===== بيانات الجداول =====
let outRows = [];
let tmzRows = [];
let custodyRows = [
  {item:'البوردات', note:'تم تأمينها'},
  {item:'الجبائر',  note:'تم تأمينها'},
];
let challengeRows = [];
let handoverRows = [
  {date_range:'01/01 إلى <?= date('Y/m/d') ?>', value:''},
];

function toSec(s){
  s=String(s||'').trim();
  if(!s)return null;
  if(s.includes(':')){const p=s.split(':');return (parseFloat(p[0])||0)*3600+(parseFloat(p[1])||0)*60+(parseFloat(p[2])||0);}
  return null;
}
function toPct(s){const v=parseFloat(String(s||'').replace('%',''));return isNaN(v)?null:v;}
function colorClass(val,target){
  if(!val||!String(val).trim()||!target||!String(target).trim()) return 'kpi-muted';
  const isPct=String(target).includes('%');
  if(isPct){const v=toPct(val),t=toPct(target);if(v===null||t===null)return 'kpi-muted';return v>=t?'kpi-green':'kpi-red';}
  const v=toSec(val),t=toSec(target);
  if(v===null||t===null)return 'kpi-muted';
  if(v<=t)return 'kpi-green';
  if(v<=t*1.05)return 'kpi-orange';
  return 'kpi-red';
}

// ===== تلوين مؤشرات الرحلة الإسعافية =====
document.querySelectorAll('.trip-val').forEach(el=>{
  const apply = ()=>{
    el.classList.remove('kpi-green','kpi-orange','kpi-red','kpi-muted');
    el.classList.add(colorClass(el.value, el.dataset.target));
  };
  el.addEventListener('input', apply);
  apply();
});

function renderOut(){
  const b=document.getElementById('outBody');
  if(!b)return;
  if(!outRows.length){b.innerHTML=`<tr><td colspan="5"><div style="text-align:center;padding:8px;color:var(--gray);font-size:12px">انقر "+ إضافة صف"</div></td></tr>`;return;}
  b.innerHTML=outRows.map((r,i)=>`<tr>
    <td><input type="time" value="${r.out_time||''}" onchange="outRows[${i}].out_time=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input type="time" value="${r.back_time||''}" onchange="outRows[${i}].back_time=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.period||''}" placeholder="الفترة" onchange="outRows[${i}].period=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.reason||''}" placeholder="السبب" onchange="outRows[${i}].reason=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.sector||''}" placeholder="القطاع" onchange="outRows[${i}].sector=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
  </tr>`).join('');
}

function renderTmz(){
  const b=document.getElementById('tmzBody');
  if(!b)return;
  if(!tmzRows.length){b.innerHTML=`<tr><td colspan="2"><div style="text-align:center;padding:8px;color:var(--gray);font-size:12px">انقر "+ إضافة صف"</div></td></tr>`;return;}
  b.innerHTML=tmzRows.map((r,i)=>`<tr>
    <td><input value="${r.count||''}" placeholder="(2) بلاغ" onchange="tmzRows[${i}].count=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.places||''}" placeholder="أماكن التمركز" onchange="tmzRows[${i}].places=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
  </tr>`).join('');
}

function renderCustody(){
  const b=document.getElementById('custodyBody');
  if(!b)return;
  if(!custodyRows.length){b.innerHTML=`<tr><td colspan="2"><div style="text-align:center;padding:8px;color:var(--gray);font-size:12px">انقر "+ إضافة صنف"</div></td></tr>`;return;}
  b.innerHTML=custodyRows.map((r,i)=>`<tr>
    <td><input value="${r.item||''}" placeholder="اسم الصنف" onchange="custodyRows[${i}].item=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;font-weight:700;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.note||''}" placeholder="الملاحظة" onchange="custodyRows[${i}].note=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
  </tr>`).join('');
}

function renderChallenges(){
  const b=document.getElementById('challengeBody');
  if(!b)return;
  if(!challengeRows.length){b.innerHTML=`<tr><td colspan="2"><div style="text-align:center;padding:8px;color:var(--gray);font-size:12px">انقر "+ إضافة بلاغ"</div></td></tr>`;return;}
  b.innerHTML=challengeRows.map((r,i)=>`<tr>
    <td><input value="${r.report_no||''}" placeholder="رقم البلاغ" onchange="challengeRows[${i}].report_no=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.challenge||''}" placeholder="التحدي" onchange="challengeRows[${i}].challenge=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
  </tr>`).join('');
}

function renderHandover(){
  const b=document.getElementById('handoverBody');
  if(!b)return;
  if(!handoverRows.length){b.innerHTML=`<tr><td colspan="2"><div style="text-align:center;padding:8px;color:var(--gray);font-size:12px">انقر "+ إضافة صف"</div></td></tr>`;return;}
  b.innerHTML=handoverRows.map((r,i)=>`<tr>
    <td><input value="${r.date_range||''}" placeholder="01/01 إلى ..." onchange="handoverRows[${i}].date_range=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;text-align:center;outline:none;width:100%"></td>
    <td><input value="${r.value||''}" placeholder="00:09:11" onchange="handoverRows[${i}].value=this.value;saveLocal()" style="border:none;background:transparent;font-family:inherit;font-size:12px;font-weight:800;text-align:center;outline:none;width:100%"></td>
  </tr>`).join('');
}

// تعبئة صفوف الجداول في حقل مخفي قبل الإرسال للسيرفر
function fillRowsJson(){
  const el = document.getElementById('rowsJson');
  if(el) el.value = JSON.stringify({
    out_rows: outRows,
    tmz_rows: tmzRows,
    custody_rows: custodyRows,
    challenge_rows: challengeRows,
    handover_rows: handoverRows
  });
}

function addOutRow(){outRows.push({out_time:'',back_time:'',period:'',reason:'',sector:''});renderOut();}
function addTmzRow(){tmzRows.push({count:'',places:''});renderTmz();}
function addCustodyRow(){custodyRows.push({item:'',note:''});renderCustody();}
function addChallengeRow(){challengeRows.push({report_no:'',challenge:''});renderChallenges();}
function addHandoverRow(){handoverRows.push({date_range:'',value:''});renderHandover();}

function saveLocal(){
  try{
    localStorage.setItem('sectors_out2',JSON.stringify(outRows));
    localStorage.setItem('sectors_tmz',JSON.stringify(tmzRows));
    localStorage.setItem('sectors_custody',JSON.stringify(custodyRows));
    localStorage.setItem('sectors_challenge',JSON.stringify(challengeRows));
    localStorage.setItem('sectors_handover',JSON.stringify(handoverRows));
  }catch(e){}
}

function loadLocal(){
  try{
    const o=localStorage.getItem('sectors_out2'); if(o)outRows=JSON.parse(o);
    const t=localStorage.getItem('sectors_tmz'); if(t)tmzRows=JSON.parse(t);
    const c=localStorage.getItem('sectors_custody'); if(c)custodyRows=JSON.parse(c);
    const ch=localStorage.getItem('sectors_challenge'); if(ch)challengeRows=JSON.parse(ch);
    const h=localStorage.getItem('sectors_handover'); if(h)handoverRows=JSON.parse(h);
  }catch(e){}
}

function updateProgress(){
  const inputs=document.querySelectorAll('#panel-today form input:not([type=hidden]),#panel-today form textarea');
  const filled=Array.from(inputs).filter(el=>el.value.trim()!=='').length;
  const total=inputs.length;
  const pct=total?Math.round(filled/total*100):0;
  document.getElementById('progFill').style.width=pct+'%';
  document.getElementById('progTxt').textContent=filled+' / '+total+' حقل مكتمل';
}
document.addEventListener('input',updateProgress);

// Init
loadLocal();
renderOut();
renderTmz();
renderCustody();
renderChallenges();
renderHandover();
updateProgress();
</script>
</body>
</html>
