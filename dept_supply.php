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

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_temp') {
        try {
            $stmt = pdo()->prepare("INSERT INTO medical_temperatures (location_type,location_name,temp_c,humidity_pct,notes,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())");
            $saved = 0;
            foreach (($_POST['loc_name'] ?? []) as $i => $name) {
                if (trim($name) === '') continue;
                $stmt->execute([$_POST['loc_type'][$i] ?? 'warehouse', $name, $_POST['temp_c'][$i] ?? '', $_POST['humidity'][$i] ?? '', $_POST['temp_notes'][$i] ?? '', $_SESSION['op_user_id'] ?? null]);
                $saved++;
            }
            $msg = "تم إرسال $saved قراءة درجة حرارة للأدمن";
        } catch (Throwable $e) { $msg = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'send_oxygen') {
        try {
            pdo()->prepare("INSERT INTO medical_oxygen (large_total,large_filled,large_empty,small_total,small_filled,small_empty,total_requests_month,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                 ->execute([$_POST['large_total'] ?? 35, $_POST['large_filled'] ?? 28, $_POST['large_empty'] ?? 0, $_POST['small_total'] ?? 83, $_POST['small_filled'] ?? 10, $_POST['small_empty'] ?? 0, $_POST['total_requests'] ?? 10, $_SESSION['op_user_id'] ?? null]);
            $msg = 'تم إرسال بيانات أسطوانات الأكسجين';
        } catch (Throwable $e) { $msg = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'send_tech_support') {
        try {
            pdo()->prepare("INSERT INTO medical_tech_support (new_count,in_progress,done,total_month,ticket_num,device_class,action_taken,ticket_status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())")
                 ->execute([$_POST['ts_new'] ?? 0, $_POST['ts_progress'] ?? 1, $_POST['ts_done'] ?? 1, $_POST['ts_total'] ?? 11, $_POST['ticket_num'] ?? '', $_POST['device_class'] ?? '', $_POST['action_taken'] ?? '', $_POST['ticket_status'] ?? '', $_SESSION['op_user_id'] ?? null]);
            $msg = 'تم إرسال بيانات الدعم الفني للأجهزة';
        } catch (Throwable $e) { $msg = 'خطأ: ' . $e->getMessage(); }
    }

    if ($action === 'save_rec') {
        try {
            pdo()->prepare("INSERT INTO medical_recommendations (rec_main,rec_risks,rec_actions,rec_notes,created_by,created_at) VALUES (?,?,?,?,?,NOW())")
                 ->execute([$_POST['rec_main'] ?? '', $_POST['rec_risks'] ?? '', $_POST['rec_actions'] ?? '', $_POST['rec_notes'] ?? '', $_SESSION['op_user_id'] ?? null]);
            $msg = 'تم حفظ التوصيات بنجاح';
        } catch (Throwable $e) { $msg = 'خطأ: ' . $e->getMessage(); }
    }
}

$tab = $_GET['tab'] ?? 'tech';
$lastRec = [];
try {
   $lastRec = pdo()->query("SELECT * FROM medical_recommendations ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}

$warehouseTemps = [
    ['مستودع الأدوية والمحاليل', '21°C', '38%', ''],
    ['مستودع التموين الطبي A', '22°C', '35%', ''],
    ['مستودع التموين الطبي B', '24°C', '27%', ''],
    ['مستودع التموين الطبي C', '23°C', '28%', ''],
    ['مستودع أسطوانات الأكسجين', '20°C', '29%', ''],
    ['المستودع العام', '24°C', '26%', ''],
];

$sector1Centers = [
    ['القيادة والتحكم', '21°C', '27%', ''],
    ['الصيداء', '20°C', '32%', ''],
    ['مواقف التصنيف', '35°C', '27%', ''],
    ['بارن هرماس', '20°C', '38%', ''],
];
$sector2Centers = [
    ['تيماء', '22°C', '31%', ''],
    ['الفراء', '20°C', '28%', ''],
    ['القلبة', '22°C', '27%', ''],
    ['أم حميطة', '24°C', '30%', ''],
    ['المظلم', '24°C', '24%', ''],
];
$sector3Centers = [
    ['الزبنة', '22°C', '28%', ''],
    ['حقل', '23°C', '41%', ''],
    ['البدع', '22°C', '33%', ''],
    ['شرما', '21°C', '36%', ''],
    ['نعي', '21°C', '30%', ''],
];
$sector4Centers = [
    ['الخريبة', '20°C', '36%', ''],
    ['ضباء', '19°C', '52%', ''],
    ['الوجه', '19°C', '64%', ''],
    ['أملج', '20°C', '60%', ''],
    ['الحرة', '23°C', '28%', ''],
    ['بداء', '','', 'هواء متكثف في الشبكة بسبب سوء المقبلة'],
];

$ambulanceTemps = [
    ['تيماء', '3760', 'د ط', '26°C', '18%', 'متصل', ''],
    ['قطاع تبوك الأول', '4262', 'ب س ح', '27°C', '17%', 'متصل', ''],
    ['قطاع تبوك الأول', '6264', 'أ وط', '26°C', 'غير متصل', 'غير متصل', 'لم يتم التشغيل منذ الفترة درجة الحرارة ومداري القائمة'],
    ['قطاع تبوك الأول', '4057', 'أ وط', '', '', 'غير متصل', 'لم يتم التشغيل منذ الفترة درجة الحرارة ومداري القائمة'],
    ['أملج', '9911', 'ح س ح', '37°C', '72%', 'متصل', ''],
    ['الوجه', '3775', 'د ط', '35°C', '68%', 'متصل', ''],
    ['ضباء', '7', 'أ ووأ', '29°C', '62%', 'متصل', ''],
    ['الحرة', '8323', 'أ وه', '', '', 'غير متصل', ''],
    ['نعي', '4899', 'خ ص ع', '25°C', '11%', 'غير متصل', 'دعم فرعية منطقة الرياض'],
    ['البدع', '4037', 'د س ح', '', '', 'غير متصل', 'لم يتم التشغيل منذ الفترة درجة الحرارة ومداري القائمة'],
    ['حقل', '3339', 'ح ن ح', '', '', 'غير متصل', ''],
    ['الزبنة', '4671', 'ب س ح', '27°C', '18%', 'متصل', ''],
    ['أم تحيلة', '5444', 'ع ل ق', '30°C', '18%', 'متصل', ''],
    ['الجراء', '7715', '', '38°C', '13%', 'متصل', ''],
    ['المظلم', '1814', 'د ط', '27°C', '19%', 'متصل', ''],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>التموين الطبي والمستودعات</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#f3f4f6;direction:rtl;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #e5e7eb;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
.page-title{font-size:14px;font-weight:700;color:#dc2626}
.page-sub{font-size:9px;color:#9ca3af}
.tabs-bar{display:flex;border-bottom:1px solid #e5e7eb;background:#f9fafb;padding:0 10px;overflow-x:auto;flex-wrap:nowrap}
.tab-link{padding:8px 12px;font-size:11px;font-weight:700;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none;white-space:nowrap;display:inline-block}
.tab-link.active{color:#dc2626;border-bottom-color:#dc2626}
.tab-link:hover:not(.active){color:#374151}
.section{padding:14px}
.page-section-title{font-size:15px;font-weight:700;color:#dc2626;text-align:center;margin-bottom:4px}
.page-date{font-size:11px;color:#374151;text-align:center;margin-bottom:14px}
.diamond{color:#dc2626;font-size:13px}

/* بطاقات الإحصاء */
.stat-cards{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:14px}
.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;text-align:center;min-width:100px}
.stat-card-icon{font-size:24px;margin-bottom:4px}
.stat-card-val{font-size:20px;font-weight:700;color:#111827}
.stat-card-val input{font-size:20px;font-weight:700;color:#111827;border:none;background:transparent;text-align:center;width:50px;outline:none;font-family:inherit}
.stat-card-val input:focus{background:#fef2f2;border-radius:4px}
.stat-card-lbl{font-size:10px;color:#6b7280;margin-top:3px;line-height:1.4}
.stat-card.green{border-color:#86efac;background:#f0fdf4}.stat-card.green .stat-card-val{color:#166534}
.stat-card.red{border-color:#fca5a5;background:#fef2f2}.stat-card.red .stat-card-val{color:#991b1b}
.stat-card.blue{border-color:#93c5fd;background:#eff6ff}.stat-card.blue .stat-card-val{color:#1e3a8a}

/* الجداول */
.section-title{text-align:center;font-size:13px;font-weight:700;color:#dc2626;margin-bottom:8px;display:flex;align-items:center;justify-content:center;gap:6px}
.tbl-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:14px}
.tbl-head-lbl{background:#1d4ed8;color:#fff;padding:7px 12px;font-size:11px;font-weight:700;text-align:center}
.tbl-head-lbl.red{background:#dc2626}
table{width:100%;border-collapse:collapse;font-size:11px}
th{background:#d1d5db;color:#374151;padding:7px 8px;font-weight:700;text-align:center;white-space:nowrap;border-left:1px solid #e5e7eb}
th:last-child{border-left:none}
th.red-th{background:#dc2626;color:#fff}
th.blue-th{background:#1d4ed8;color:#fff}
td{padding:7px 8px;border-bottom:1px solid #f0f0f0;color:#111827;text-align:center;vertical-align:middle;border-left:1px solid #f0f0f0}
td:last-child{border-left:none}
tbody tr:nth-child(even) td{background:#f9fafb}
tbody tr:hover td{background:#fef2f2}
td input,td textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:11px;color:#111827;text-align:center;resize:none}
td textarea{min-height:28px;line-height:1.3;text-align:right}
td input:focus,td textarea:focus{background:#fef2f2;border-radius:3px}
.nc{color:#9ca3af;font-size:9px}
.temp-red{color:#dc2626;font-weight:700}
.temp-ok{color:#166534;font-weight:700}
.connected{color:#166534;font-size:10px;font-weight:700}
.disconnected{color:#dc2626;font-size:10px;font-weight:700}
.note-text{color:#dc2626;font-size:10px;text-align:right}

/* الشارت */
.chart-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:14px}
.chart-title{font-size:13px;font-weight:700;color:#dc2626;text-align:center;margin-bottom:10px}
canvas{display:block;margin:0 auto}
.chart-legend{display:flex;justify-content:center;gap:16px;margin-top:8px;flex-wrap:wrap}
.leg-item{display:flex;align-items:center;gap:5px;font-size:10px;color:#374151}
.leg-box{width:12px;height:12px;border-radius:2px;flex-shrink:0}

/* أسطوانات */
.oxygen-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:12px;align-items:start}
@media(max-width:600px){.oxygen-grid{grid-template-columns:1fr}}
.oxygen-info{display:flex;flex-direction:column;gap:6px;font-size:11px}
.oxygen-info .item{display:flex;align-items:center;gap:6px}
.oxygen-info .dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

/* دوائر أجهزة الحرارة */
.heat-circles{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:14px}
@media(max-width:600px){.heat-circles{grid-template-columns:1fr}}
.heat-circle-wrap{display:flex;flex-direction:column;align-items:center;gap:8px}
.heat-circle{width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px}
.heat-circle.blue{background:#1d4ed8}.heat-circle.orange{background:#f97316}.heat-circle.gray{background:#9ca3af}
.heat-circle-lbl{font-size:11px;font-weight:700;text-align:center;color:#374151;line-height:1.4}
.heat-circle-val{font-size:13px;font-weight:700;color:#dc2626;text-align:center;line-height:1.5}

/* شبكة المراكز */
.centers-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:600px){.centers-grid{grid-template-columns:1fr}}

/* قطاع عنوان */
.sector-title{font-size:12px;font-weight:700;color:#1d4ed8;margin-bottom:6px;display:flex;align-items:center;gap:5px}

/* الإرسال */
.add-btn{display:flex;align-items:center;gap:4px;padding:6px 10px;font-size:10px;color:#dc2626;cursor:pointer;background:#fff;border:none;border-top:1px solid #f0f0f0;width:100%;font-family:inherit}
.add-btn:hover{background:#fef2f2}
.send-bar{display:flex;justify-content:flex-end;padding:10px 0 4px}
.btn-send{padding:7px 20px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-send:hover{background:#991b1b}
.rec-box{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:10px}
.rec-head{background:#f9fafb;padding:7px 12px;font-size:11px;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb}
.rec-box textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:12px;color:#111827;resize:vertical;min-height:70px;padding:8px 12px;direction:rtl;display:block}
.footer{display:flex;align-items:center;gap:5px;padding:8px 14px;border-top:1px solid #e5e7eb;justify-content:flex-end;background:#fff;margin-top:12px}
.f-s{width:18px;height:18px;border-radius:50%;font-size:8px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center}
.f-997{background:#dc2626;color:#fff;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700}
.msg{margin:10px 14px;padding:9px 14px;border-radius:8px;font-size:12px;font-weight:700;border:1px solid}
.msg.ok{background:#f0fdf4;color:#166534;border-color:#86efac}
.msg.err{background:#fef2f2;color:#991b1b;border-color:#fca5a5}
.deco-bar{display:flex;height:4px}
.deco-bar span{flex:1}
</style>
</head>
<body>

<div class="page-header">
  <div>
    <div class="page-title">❖ التموين الطبي والمستودعات</div>
    <div class="page-sub">SAUDI RED CRESCENT AUTHORITY — تبوك</div>
  </div>
  <svg width="38" height="38" viewBox="0 0 44 44">
    <circle cx="22" cy="22" r="20" fill="#fef2f2" stroke="#fca5a5" stroke-width="1"/>
    <path d="M22 7 a15 15 0 0 1 0 30 a11 11 0 0 0 0-30z" fill="#991b1b"/>
    <polygon points="26,13 27.2,16.8 31,16.8 28,19.2 29.2,23 26,20.8 22.8,23 24,19.2 21,16.8 24.8,16.8" fill="#991b1b"/>
  </svg>
</div>
<div class="deco-bar">
  <span style="background:#dc2626;flex:4"></span>
  <span style="background:#f59e0b;flex:1"></span>
  <span style="background:#dc2626;opacity:.4;flex:.6"></span>
</div>

<?php if($msg): ?>
<div class="msg <?= str_contains($msg,'تم')||str_contains($msg,'نجاح')?'ok':'err' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="tabs-bar">
  <a class="tab-link <?= $tab==='tech'?'active':'' ?>" href="?tab=tech">🔧 الدعم الفني للأجهزة</a>
  <a class="tab-link <?= $tab==='oxygen'?'active':'' ?>" href="?tab=oxygen">🔵 أسطوانات الأكسجين</a>
  <a class="tab-link <?= $tab==='heat_dev'?'active':'' ?>" href="?tab=heat_dev">🌡️ أجهزة الحرارة</a>
  <a class="tab-link <?= $tab==='heat_ware'?'active':'' ?>" href="?tab=heat_ware">🏭 حرارة المستودعات</a>
  <a class="tab-link <?= $tab==='heat_center'?'active':'' ?>" href="?tab=heat_center">🏥 حرارة المراكز</a>
  <a class="tab-link <?= $tab==='heat_amb'?'active':'' ?>" href="?tab=heat_amb">🚑 حرارة سيارات الإسعاف</a>
  <a class="tab-link <?= $tab==='inventory'?'active':'' ?>" href="?tab=inventory">📦 المخزون والأدوية</a>
  <a class="tab-link <?= $tab==='rec'?'active':'' ?>" href="?tab=rec">📌 التوصيات</a>
</div>

<!-- ===== الدعم الفني للأجهزة الطبية ===== -->
<?php if($tab==='tech'): ?>
<div class="section">
  <div class="page-section-title">❖ مؤشرات طلبات الدعم الفني للأجهزة الطبية ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <div style="font-size:12px;font-weight:700;color:#374151;text-align:right;margin-bottom:8px">عدد طلبات الدعم الفني للأجهزة الطبية</div>
  <form method="post">
  <input type="hidden" name="action" value="send_tech_support">
  <div class="stat-cards">
    <div class="stat-card"><div class="stat-card-icon">📋</div><div class="stat-card-val"><input name="ts_new" type="number" value="0"></div><div class="stat-card-lbl">الجديدة = 0</div></div>
    <div class="stat-card red"><div class="stat-card-icon">⚠️</div><div class="stat-card-val"><input name="ts_progress" type="number" value="1" style="color:#991b1b"></div><div class="stat-card-lbl">قيد العمل = 1</div></div>
    <div class="stat-card green"><div class="stat-card-icon">✅</div><div class="stat-card-val"><input name="ts_done" type="number" value="1" style="color:#166534"></div><div class="stat-card-lbl">تم الحل = 1</div></div>
    <div class="stat-card blue"><div class="stat-card-icon">📊</div><div class="stat-card-val"><input name="ts_total" type="number" value="11" style="color:#1e3a8a"></div><div class="stat-card-lbl">إجمالي الطلبات للفترة<br>لـ يونيو - 11</div></div>
  </div>

  <!-- جدول البيانات التي تم حلها -->
  <div class="section-title"><span class="diamond">❖</span> بيانات الطلبات التي تم حلها <span class="diamond">❖</span></div>
  <div class="tbl-card">
    <table>
      <thead><tr>
        <th>رقم الطلب</th>
        <th>المركز الإسعافي</th>
        <th>تصنيف الطلب</th>
        <th>حالة الطلب</th>
      </tr></thead>
      <tbody>
        <tr>
          <td><input name="ticket_num" value="MED 0007269"></td>
          <td><input value="فريق سريع"></td>
          <td><textarea rows="2">جهاز المعدات:<br>LifePak 15<br>SN:50688114</textarea></td>
          <td><textarea rows="2">عدم صلاحية كبل وصمة فحص قياس النبض والأكسجين بالدم. وتم تركيب كبل وصمة جديد الجهاز.</textarea></td>
        </tr>
        <tr>
          <td><input placeholder="—"></td>
          <td><input placeholder="—"></td>
          <td><textarea rows="2" placeholder="..."></textarea></td>
          <td><textarea rows="2" placeholder="..."></textarea></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addTechRow()">+ إضافة</button>
  </div>

  <!-- شارت مؤشرة نسبة طلبات الدعم -->
  <div class="section-title"><span class="diamond">❖</span> مؤشرنسبة طلبات الدعم الفني المغلقة من بداية شهر يونيو خلال... <span class="diamond">❖</span></div>
  <div class="chart-wrap">
    <canvas id="pieChart" width="200" height="200"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-box" style="background:#1d4ed8"></div>3 أيام أو أقل</div>
      <div class="leg-item"><div class="leg-box" style="background:#16a34a"></div>6 أيام أو أقل</div>
      <div class="leg-item"><div class="leg-box" style="background:#f59e0b"></div>9 أيام أو أقل</div>
      <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>10 أيام فأكثر</div>
    </div>
  </div>

  <!-- شارت طلبات الدعم الفني -->
  <div class="section-title"><span class="diamond">❖</span> طلبات الدعم الفني للأجهزة الطبية <span class="diamond">❖</span></div>
  <div class="chart-wrap">
    <canvas id="barChart" width="600" height="220"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>البند الأدنى</div>
      <div class="leg-item"><div class="leg-box" style="background:#f59e0b"></div>نقطة إعادة الطلب</div>
      <div class="leg-item"><div class="leg-box" style="background:#16a34a"></div>البند الأعلى</div>
    </div>
  </div>

  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== أسطوانات الأكسجين ===== -->
<?php if($tab==='oxygen'): ?>
<div class="section">
  <div class="page-section-title">❖ التموين الطبي ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <form method="post">
  <input type="hidden" name="action" value="send_oxygen">

  <!-- طلبات التموين الطبي -->
  <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
    <div class="stat-card blue" style="min-width:160px">
      <div class="stat-card-icon">📋</div>
      <div class="stat-card-val"><input name="total_requests" type="number" value="10" style="color:#1e3a8a"></div>
      <div class="stat-card-lbl">إجمالي عدد الطلبات المنجزة<br>لشهر يونيو = 10</div>
    </div>
  </div>

  <!-- الدائرة + الإحصاء -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center;margin-bottom:16px">
    <div>
      <div class="section-title" style="text-align:right;justify-content:flex-start">إحصائية عدد أسطوانات الأكسجين في مستودع الأسطوانات</div>
      <div class="oxygen-grid" style="grid-template-columns:1fr 1fr;gap:20px;padding:10px">
        <div style="text-align:center">
          <div style="font-size:60px;margin-bottom:8px">🔵</div>
          <div class="oxygen-info" style="text-align:right;font-size:11px;color:#374151">
            <div class="item"><div class="dot" style="background:#dc2626"></div>الأسطوانات الكبيرة مقاس M = <input name="large_total" type="number" value="35" style="width:35px;border:none;font-weight:700;font-family:inherit;font-size:11px;outline:none;text-align:center;background:#f3f4f6;border-radius:3px"></div>
            <div class="item"><div class="dot" style="background:#1d4ed8"></div>الأسطوانات الممتلئة = <input name="large_filled" type="number" value="28" style="width:35px;border:none;font-weight:700;font-family:inherit;font-size:11px;outline:none;text-align:center;background:#f3f4f6;border-radius:3px"></div>
            <div class="item"><div class="dot" style="background:#9ca3af"></div>الأسطوانات الفارغة = <input name="large_empty" type="number" value="0" style="width:35px;border:none;font-weight:700;font-family:inherit;font-size:11px;outline:none;text-align:center;background:#f3f4f6;border-radius:3px"></div>
          </div>
        </div>
        <div style="text-align:center">
          <div style="font-size:48px;margin-bottom:8px">⚪</div>
          <div class="oxygen-info" style="text-align:right;font-size:11px;color:#374151">
            <div class="item"><div class="dot" style="background:#dc2626"></div>الأسطوانات الصغيرة مقاس D = <input name="small_total" type="number" value="83" style="width:35px;border:none;font-weight:700;font-family:inherit;font-size:11px;outline:none;text-align:center;background:#f3f4f6;border-radius:3px"></div>
            <div class="item"><div class="dot" style="background:#1d4ed8"></div>الأسطوانات الممتلئة = <input name="small_filled" type="number" value="10" style="width:35px;border:none;font-weight:700;font-family:inherit;font-size:11px;outline:none;text-align:center;background:#f3f4f6;border-radius:3px"></div>
            <div class="item"><div class="dot" style="background:#9ca3af"></div>الأسطوانات الفارغة = <input name="small_empty" type="number" value="0" style="width:35px;border:none;font-weight:700;font-family:inherit;font-size:11px;outline:none;text-align:center;background:#f3f4f6;border-radius:3px"></div>
          </div>
        </div>
      </div>
    </div>
    <div>
      <canvas id="oxygenPie" width="200" height="200"></canvas>
      <div class="chart-legend">
        <div class="leg-item"><div class="leg-box" style="background:#1d4ed8"></div>منجزة</div>
        <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>غير منجزة</div>
        <div class="leg-item"><div class="leg-box" style="background:#9ca3af"></div>لا يوجد طلبات</div>
      </div>
    </div>
  </div>

  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== أجهزة الحرارة ===== -->
<?php if($tab==='heat_dev'): ?>
<div class="section">
  <div class="page-section-title">❖ أجهزة الحرارة ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <div class="heat-circles">
    <div class="heat-circle-wrap">
      <div class="heat-circle gray">🏥</div>
      <div class="heat-circle-lbl">عدد المراكز المغطاة<br>بأجهزة الحرارة</div>
      <div class="heat-circle-val">19 مركز إسعافي</div>
    </div>
    <div class="heat-circle-wrap">
      <div class="heat-circle orange">🚑</div>
      <div class="heat-circle-lbl">عدد مركبات الإسعاف المغطاة<br>بأجهزة الحرارة</div>
      <div class="heat-circle-val">29 مركبة إسعاف</div>
    </div>
    <div class="heat-circle-wrap">
      <div class="heat-circle blue">🏭</div>
      <div class="heat-circle-lbl">عدد الأجهزة في مستودع<br>التموين الطبي والعام</div>
      <div class="heat-circle-val" style="font-size:11px">10 أجهزة حرارة<br>عدد 8 في التموين الطبي<br>عدد 1 في المستودع العام<br>عدد 1 في مستودع الأكسجين</div>
    </div>
  </div>

  <div class="send-bar"><a href="?tab=heat_ware" class="btn-send" style="text-decoration:none">عرض قراءات الحرارة ←</a></div>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== حرارة المستودعات ===== -->
<?php if($tab==='heat_ware'): ?>
<div class="section">
  <div class="page-section-title">❖ درجات الحرارة بمستودعات التموين الطبي والعام ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <form method="post">
  <input type="hidden" name="action" value="send_temp">
  <div class="tbl-card">
    <table>
      <thead><tr>
        <th style="width:22px">م</th>
        <th>اسم المستودع</th>
        <th style="width:80px">درجة الحرارة</th>
        <th style="width:75px">الرطوبة</th>
        <th>ملاحظات</th>
      </tr></thead>
      <tbody>
        <?php foreach($warehouseTemps as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="loc_name[]" value="<?= h($r[0]) ?>" style="text-align:right"><input type="hidden" name="loc_type[]" value="warehouse"></td>
          <td><input name="temp_c[]" value="<?= h($r[1]) ?>" class="temp-red"></td>
          <td><input name="humidity[]" value="<?= h($r[2]) ?>" class="temp-red"></td>
          <td><textarea name="temp_notes[]" rows="2"><?= h($r[3]) ?></textarea></td>
        </tr>
        <?php endforeach; ?>
        <?php for($i=count($warehouseTemps);$i<10;$i++): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="loc_name[]" placeholder="اسم المستودع" style="text-align:right"><input type="hidden" name="loc_type[]" value="warehouse"></td>
          <td><input name="temp_c[]" placeholder="°C" class="temp-red"></td>
          <td><input name="humidity[]" placeholder="%" class="temp-red"></td>
          <td><textarea name="temp_notes[]" rows="2" placeholder="ملاحظات..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addTempRow()">+ إضافة مستودع</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== حرارة المراكز ===== -->
<?php if($tab==='heat_center'): ?>
<div class="section">
  <div class="page-section-title">❖ درجات الحرارة بمستودعات التموين الطبي بالمراكز ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <form method="post">
  <input type="hidden" name="action" value="send_temp">
  <div class="centers-grid">

    <!-- قطاع تبوك الأول -->
    <div>
      <div class="sector-title">❖ قطاع تبوك الإسعاف الأول</div>
      <div class="tbl-card">
        <table>
          <thead><tr>
            <th class="blue-th" style="width:22px">م</th>
            <th class="blue-th">المركز الإسعافي</th>
            <th class="blue-th" style="width:70px">درجة الحرارة</th>
            <th class="blue-th" style="width:65px">الرطوبة</th>
            <th class="blue-th">ملاحظات</th>
          </tr></thead>
          <tbody>
            <?php foreach($sector1Centers as $i=>$r): ?>
            <tr>
              <td class="nc"><?= $i+1 ?></td>
              <td><input name="loc_name[]" value="<?= h($r[0]) ?>" style="text-align:right"><input type="hidden" name="loc_type[]" value="center1"></td>
              <td><input name="temp_c[]" value="<?= h($r[1]) ?>" class="temp-red"></td>
              <td><input name="humidity[]" value="<?= h($r[2]) ?>" class="temp-red"></td>
              <td><textarea name="temp_notes[]" rows="1"><?= h($r[3]) ?></textarea></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="button" class="add-btn" onclick="addTempRow()">+ إضافة</button>
      </div>
    </div>

    <!-- قطاع تبوك الثاني -->
    <div>
      <div class="sector-title">❖ قطاع تبوك الإسعاف الثاني</div>
      <div class="tbl-card">
        <table>
          <thead><tr>
            <th class="blue-th" style="width:22px">م</th>
            <th class="blue-th">المركز الإسعافي</th>
            <th class="blue-th" style="width:70px">درجة الحرارة</th>
            <th class="blue-th" style="width:65px">الرطوبة</th>
            <th class="blue-th">ملاحظات</th>
          </tr></thead>
          <tbody>
            <?php foreach($sector2Centers as $i=>$r): ?>
            <tr>
              <td class="nc"><?= $i+1 ?></td>
              <td><input name="loc_name[]" value="<?= h($r[0]) ?>" style="text-align:right"><input type="hidden" name="loc_type[]" value="center2"></td>
              <td><input name="temp_c[]" value="<?= h($r[1]) ?>" class="temp-red"></td>
              <td><input name="humidity[]" value="<?= h($r[2]) ?>" class="temp-red"></td>
              <td><textarea name="temp_notes[]" rows="1"><?= h($r[3]) ?></textarea></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="button" class="add-btn" onclick="addTempRow()">+ إضافة</button>
      </div>
    </div>

    <!-- قطاع تبوك الثالث -->
    <div>
      <div class="sector-title">❖ قطاع تبوك الإسعاف الثالث</div>
      <div class="tbl-card">
        <table>
          <thead><tr>
            <th class="blue-th" style="width:22px">م</th>
            <th class="blue-th">المركز الإسعافي</th>
            <th class="blue-th" style="width:70px">درجة الحرارة</th>
            <th class="blue-th" style="width:65px">الرطوبة</th>
            <th class="blue-th">ملاحظات</th>
          </tr></thead>
          <tbody>
            <?php foreach($sector3Centers as $i=>$r): ?>
            <tr>
              <td class="nc"><?= $i+1 ?></td>
              <td><input name="loc_name[]" value="<?= h($r[0]) ?>" style="text-align:right"><input type="hidden" name="loc_type[]" value="center3"></td>
              <td><input name="temp_c[]" value="<?= h($r[1]) ?>" class="temp-red"></td>
              <td><input name="humidity[]" value="<?= h($r[2]) ?>" class="temp-red"></td>
              <td><textarea name="temp_notes[]" rows="1"><?= h($r[3]) ?></textarea></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="button" class="add-btn" onclick="addTempRow()">+ إضافة</button>
      </div>
    </div>

    <!-- قطاع تبوك الرابع -->
    <div>
      <div class="sector-title">❖ قطاع تبوك الإسعاف الرابع</div>
      <div class="tbl-card">
        <table>
          <thead><tr>
            <th class="blue-th" style="width:22px">م</th>
            <th class="blue-th">المركز الإسعافي</th>
            <th class="blue-th" style="width:70px">درجة الحرارة</th>
            <th class="blue-th" style="width:65px">الرطوبة</th>
            <th class="blue-th">ملاحظات</th>
          </tr></thead>
          <tbody>
            <?php foreach($sector4Centers as $i=>$r): ?>
            <tr>
              <td class="nc"><?= $i+1 ?></td>
              <td><input name="loc_name[]" value="<?= h($r[0]) ?>" style="text-align:right"><input type="hidden" name="loc_type[]" value="center4"></td>
              <td><input name="temp_c[]" value="<?= h($r[1]) ?>" class="temp-red"></td>
              <td><input name="humidity[]" value="<?= h($r[2]) ?>" class="temp-red"></td>
              <td><textarea name="temp_notes[]" rows="1" class="<?= $r[3]?'note-text':'' ?>"><?= h($r[3]) ?></textarea></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="button" class="add-btn" onclick="addTempRow()">+ إضافة</button>
      </div>
    </div>

  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== حرارة سيارات الإسعاف ===== -->
<?php if($tab==='heat_amb'): ?>
<div class="section">
  <div class="page-section-title">❖ درجات الحرارة بمركبات الإسعاف ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <form method="post">
  <input type="hidden" name="action" value="send_temp">
  <div class="tbl-card">
    <table>
      <thead><tr>
        <th class="blue-th" style="width:22px">م</th>
        <th class="blue-th">اسم المركز</th>
        <th class="blue-th" style="width:60px">رقم المركبة</th>
        <th class="blue-th" style="width:55px">لوحة</th>
        <th class="blue-th" style="width:70px">الحرارة</th>
        <th class="blue-th" style="width:65px">الرطوبة</th>
        <th class="blue-th" style="width:65px">الحالة</th>
        <th class="blue-th">ملاحظات</th>
      </tr></thead>
      <tbody>
        <?php foreach($ambulanceTemps as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="loc_name[]" value="<?= h($r[0]) ?>" style="text-align:right"><input type="hidden" name="loc_type[]" value="ambulance"></td>
          <td><input name="veh_num[]" value="<?= h($r[1]) ?>"></td>
          <td><input name="plate[]" value="<?= h($r[2]) ?>" style="font-size:10px"></td>
          <td><input name="temp_c[]" value="<?= h($r[3]) ?>" class="<?= $r[3]?'temp-red':'' ?>"></td>
          <td><input name="humidity[]" value="<?= h($r[4]) ?>" class="<?= $r[4]?'temp-red':'' ?>"></td>
          <td>
            <?php if($r[5]==='متصل'): ?>
              <span class="connected">✔ متصل</span>
            <?php elseif($r[5]==='غير متصل'): ?>
              <span class="disconnected">✖ غير متصل</span>
            <?php else: ?>
              <input name="status[]" value="<?= h($r[5]) ?>">
            <?php endif; ?>
            <input type="hidden" name="loc_type[]" value="ambulance">
            <input type="hidden" name="temp_notes[]" value="<?= h($r[6]) ?>">
          </td>
          <td><textarea rows="2" class="<?= $r[6]?'note-text':'' ?>"><?= h($r[6]) ?></textarea></td>
        </tr>
        <?php endforeach; ?>
        <?php for($i=count($ambulanceTemps);$i<32;$i++): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="loc_name[]" placeholder="—" style="text-align:right"><input type="hidden" name="loc_type[]" value="ambulance"></td>
          <td><input name="veh_num[]" placeholder="—"></td>
          <td><input name="plate[]" placeholder="—"></td>
          <td><input name="temp_c[]" placeholder="°C"></td>
          <td><input name="humidity[]" placeholder="%"></td>
          <td><input placeholder="—"></td>
          <td><textarea rows="2" name="temp_notes[]" placeholder="..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addAmbRow()">+ إضافة</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== المخزون والأدوية ===== -->
<?php if($tab==='inventory'): ?>
<div class="section">
  <div class="page-section-title">❖ مستوى المخزون — الأدوية والمستهلكات الطبية ❖</div>
  <div class="page-date">الأربعاء 10 / 6 / 2026 م</div>

  <!-- شارت الأدوية -->
  <div class="chart-wrap">
    <div class="chart-title">❖ الأدوية ❖</div>
    <canvas id="invBar1" width="600" height="200"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>البند الأدنى</div>
      <div class="leg-item"><div class="leg-box" style="background:#f59e0b"></div>نقطة إعادة الطلب</div>
      <div class="leg-item"><div class="leg-box" style="background:#16a34a"></div>البند الأعلى</div>
    </div>
  </div>

  <!-- شارت المستهلكات الطبية -->
  <div class="chart-wrap">
    <div class="chart-title">❖ المستهلكات الطبية ❖</div>
    <canvas id="invBar2" width="600" height="200"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>البند الأدنى</div>
      <div class="leg-item"><div class="leg-box" style="background:#f59e0b"></div>نقطة إعادة الطلب</div>
      <div class="leg-item"><div class="leg-box" style="background:#16a34a"></div>البند الأعلى</div>
    </div>
  </div>

  <!-- البنود التي وصلت إلى حد الطلب -->
  <div class="chart-wrap">
    <div class="chart-title">❖ البنود التي وصلت إلى حد الطلب ❖</div>
    <canvas id="invBar3" width="600" height="200"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>البند الأدنى</div>
      <div class="leg-item"><div class="leg-box" style="background:#f59e0b"></div>نقطة إعادة الطلب</div>
      <div class="leg-item"><div class="leg-box" style="background:#16a34a"></div>البند الأعلى</div>
    </div>
  </div>

  <!-- البنود التي وصلت إلى الحد الأدنى -->
  <div class="chart-wrap">
    <div class="chart-title" style="color:#dc2626">❖ البنود التي وصلت إلى الحد الأدنى ❖</div>
    <canvas id="invBar4" width="600" height="200"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-box" style="background:#dc2626"></div>البند الأدنى</div>
      <div class="leg-item"><div class="leg-box" style="background:#f59e0b"></div>نقطة إعادة الطلب</div>
      <div class="leg-item"><div class="leg-box" style="background:#16a34a"></div>البند الأعلى</div>
    </div>
  </div>

  <!-- جدول إدخال يدوي للمخزون -->
  <div class="section-title">إدخال بيانات المخزون</div>
  <form method="post">
  <input type="hidden" name="action" value="send_temp">
  <div class="tbl-card">
    <table>
      <thead><tr>
        <th style="width:22px">#</th>
        <th>اسم البند</th>
        <th style="width:70px">النوع</th>
        <th style="width:65px">الكمية الحالية</th>
        <th style="width:65px">حد الطلب</th>
        <th style="width:65px">الحد الأدنى</th>
        <th style="width:65px">الحد الأعلى</th>
        <th>ملاحظات</th>
      </tr></thead>
      <tbody id="inv-body">
        <?php for($i=1;$i<=5;$i++): ?>
        <tr>
          <td class="nc"><?=$i?></td>
          <td><input name="loc_name[]" placeholder="اسم الدواء/المستهلك" style="text-align:right"><input type="hidden" name="loc_type[]" value="inventory"></td>
          <td><input placeholder="دواء/مستهلك/جهاز"></td>
          <td><input type="number" name="temp_c[]" placeholder="0"></td>
          <td><input type="number" placeholder="0"></td>
          <td><input type="number" placeholder="0"></td>
          <td><input type="number" placeholder="0"></td>
          <td><textarea name="temp_notes[]" rows="2" placeholder="..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addInvRow()">+ إضافة بند</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== التوصيات ===== -->
<?php if($tab==='rec'): ?>
<div class="section">
  <form method="post">
  <input type="hidden" name="action" value="save_rec">
  <div class="rec-box"><div class="rec-head">⭐ التوصيات</div><textarea name="rec_main" placeholder="التوصيات..."><?= h($lastRec['rec_main']??'') ?></textarea></div>
  <div class="rec-box"><div class="rec-head">⚠️ المخاطر والتحديات</div><textarea name="rec_risks" placeholder="المخاطر..."><?= h($lastRec['rec_risks']??'') ?></textarea></div>
  <div class="rec-box"><div class="rec-head">✅ الإجراءات المقترحة</div><textarea name="rec_actions" placeholder="الإجراءات..."><?= h($lastRec['rec_actions']??'') ?></textarea></div>
  <div class="rec-box"><div class="rec-head">📝 ملاحظات عامة</div><textarea name="rec_notes" placeholder="ملاحظات..."><?= h($lastRec['rec_notes']??'') ?></textarea></div>
  <div class="send-bar"><button type="submit" class="btn-send">💾 حفظ التوصيات</button></div>
  </form>
</div>
<?php endif; ?>

<script>
// ===== الدوائر =====
function drawPie(id, data, colors){
  const canvas = document.getElementById(id); if(!canvas) return;
  const ctx = canvas.getContext('2d');
  const cx=100,cy=100,r=80,total=data.reduce((a,b)=>a+b,0);
  ctx.clearRect(0,0,200,200);
  let start=-Math.PI/2;
  data.forEach((v,i)=>{
    const ang=v/total*Math.PI*2;
    ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,start,start+ang);
    ctx.closePath(); ctx.fillStyle=colors[i]; ctx.fill(); start+=ang;
  });
}

// ===== الشارت الأعمدة =====
function drawBar(id, labels, datasets){
  const canvas=document.getElementById(id); if(!canvas) return;
  const ctx=canvas.getContext('2d');
  const W=canvas.parentElement.offsetWidth-28||580, H=canvas.height||200;
  canvas.width=W;
  ctx.clearRect(0,0,W,H);
  const PAD={top:14,right:10,bottom:40,left:36};
  const cW=W-PAD.left-PAD.right, cH=H-PAD.top-PAD.bottom;
  const barW=Math.max(6, cW/labels.length/datasets.length-4);
  const groupW=barW*datasets.length+6;

  ctx.strokeStyle='#f0f0f0'; ctx.lineWidth=1;
  [0,25,50,75,100].forEach(v=>{
    const y=PAD.top+(1-v/100)*cH;
    ctx.beginPath(); ctx.moveTo(PAD.left,y); ctx.lineTo(W-PAD.right,y); ctx.stroke();
    ctx.fillStyle='#9ca3af'; ctx.font='8px sans-serif'; ctx.textAlign='right';
    ctx.fillText(v,PAD.left-2,y+3);
  });

  labels.forEach((lbl,i)=>{
    const gx=PAD.left+i*(cW/labels.length)+cW/labels.length/2-groupW/2;
    datasets.forEach((ds,di)=>{
      const bx=gx+di*(barW+2);
      const bh=(ds.data[i]||0)/100*cH;
      const by=PAD.top+cH-bh;
      ctx.fillStyle=ds.color; ctx.fillRect(bx,by,barW,bh);
    });
    ctx.fillStyle='#6b7280'; ctx.font='7px sans-serif'; ctx.textAlign='center';
    const x=PAD.left+i*(cW/labels.length)+cW/labels.length/2;
    const words=lbl.split(' ');
    words.slice(0,2).forEach((w,wi)=>ctx.fillText(w,x,H-PAD.bottom+10+wi*9));
  });
}

// ===== إضافة صفوف =====
let techN=2, tempN=<?=count($warehouseTemps)+4?>, ambN=<?=count($ambulanceTemps)+17?>, invN=5;

function addTechRow(){
  techN++;
  const tr=document.createElement('tr');
  tr.innerHTML=`<td><input placeholder="—"></td><td><input placeholder="—"></td><td><textarea rows="2" placeholder="..."></textarea></td><td><textarea rows="2" placeholder="..."></textarea></td>`;
  document.querySelector('#tech-table tbody')?.appendChild(tr);
}
function addTempRow(){
  tempN++;
  const tr=document.createElement('tr');
  tr.innerHTML=`<td class="nc">${tempN}</td><td><input name="loc_name[]" placeholder="—" style="text-align:right"><input type="hidden" name="loc_type[]" value="warehouse"></td><td><input name="temp_c[]" placeholder="°C" class="temp-red"></td><td><input name="humidity[]" placeholder="%" class="temp-red"></td><td><textarea name="temp_notes[]" rows="2" placeholder="..."></textarea></td>`;
  document.querySelectorAll('tbody').forEach(b=>{ if(b.id==='') b.appendChild(tr.cloneNode(true)); });
}
function addAmbRow(){
  ambN++;
  const tr=document.createElement('tr');
  tr.innerHTML=`<td class="nc">${ambN}</td><td><input name="loc_name[]" placeholder="—" style="text-align:right"><input type="hidden" name="loc_type[]" value="ambulance"></td><td><input name="veh_num[]" placeholder="—"></td><td><input name="plate[]" placeholder="—"></td><td><input name="temp_c[]" placeholder="°C"></td><td><input name="humidity[]" placeholder="%"></td><td><input placeholder="—"></td><td><textarea rows="2" name="temp_notes[]" placeholder="..."></textarea></td>`;
  const b=document.querySelector('.tbl-card table tbody'); if(b) b.appendChild(tr);
}
function addInvRow(){
  invN++;
  const tr=document.createElement('tr');
  tr.innerHTML=`<td class="nc">${invN}</td><td><input name="loc_name[]" placeholder="—" style="text-align:right"><input type="hidden" name="loc_type[]" value="inventory"></td><td><input placeholder="—"></td><td><input type="number" name="temp_c[]" placeholder="0"></td><td><input type="number" placeholder="0"></td><td><input type="number" placeholder="0"></td><td><input type="number" placeholder="0"></td><td><textarea name="temp_notes[]" rows="2" placeholder="..."></textarea></td>`;
  document.getElementById('inv-body')?.appendChild(tr);
}

// رسم الشارتات عند التحميل
setTimeout(()=>{
  // دائرة الدعم الفني
  drawPie('pieChart',[100],['#1d4ed8']);
  // دائرة الأكسجين
  drawPie('oxygenPie',[100,0,0],['#1d4ed8','#dc2626','#9ca3af']);

  const medicine_labels=['باراسيتامول','أسبرين 300mg','نيتروجليسرين','أدرينالين','أميودارون','مورفين','كيتامين','ميدازولام','أتروبين','سالبيتامول'];
  const consumable_labels=['قفازات فحص','كمامات جراحية','شاش طبي','لاصق طبي','محاقن','محاليل IV','أنابيب تنفس','كمادات جاهزة','أقطاب ECG','أكياس بول'];
  const ds_green=[{data:medicine_labels.map(()=>100),color:'#16a34a'},{data:medicine_labels.map(()=>30),color:'#f59e0b'},{data:medicine_labels.map(()=>10),color:'#dc2626'}];
  const ds_con=[{data:consumable_labels.map(()=>100),color:'#16a34a'},{data:consumable_labels.map(()=>30),color:'#f59e0b'},{data:consumable_labels.map(()=>10),color:'#dc2626'}];

  drawBar('barChart', medicine_labels, ds_green);
  drawBar('invBar1', medicine_labels, ds_green);
  drawBar('invBar2', consumable_labels, ds_con);
  drawBar('invBar3', ['بند أ','بند ب','بند ج','بند د','بند هـ'], [{data:[30,28,35,29,32],color:'#f59e0b'},{data:[10,8,12,9,11],color:'#dc2626'},{data:[0,0,0,0,0],color:'#16a34a'}]);
  drawBar('invBar4', ['بند أ','بند ب','بند ج'], [{data:[5,3,8],color:'#dc2626'},{data:[0,0,0],color:'#f59e0b'},{data:[0,0,0],color:'#16a34a'}]);
},150);
window.addEventListener('resize',()=>{
  setTimeout(()=>{
    const medicine_labels=['باراسيتامول',
    'أسبرين 300mg',
    'نيتروجليسرين',
    'أدرينالين',
    'أميودارون',
    'مورفين',
    'كيتامين',
    'ميدازولام',
    'أتروبين',
    'سالبيتامول'];
    const consumable_labels=['قفازات فحص','كمامات جراحية','شاش طبي','لاصق طبي','محاقن','محاليل IV','أنابيب تنفس','كمادات جاهزة','أقطاب ECG','أكياس بول'];
    const ds=[{data:medicine_labels.map(()=>100),color:'#16a34a'},{data:medicine_labels.map(()=>30),color:'#f59e0b'},{data:medicine_labels.map(()=>10),color:'#dc2626'}];
    drawBar('barChart',medicine_labels,ds); drawBar('invBar1',medicine_labels,ds);
    drawBar('invBar2',consumable_labels,ds);
  },100);
});
</script>

</body>
</html>