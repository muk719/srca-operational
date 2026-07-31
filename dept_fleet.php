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

    if ($action === 'send_dist') {
        try {
            pdo()->prepare("INSERT INTO fleet_distribution (amb_total,service_total,spec_total,fourwd_total,broken_amb,broken_fourwd,broken_total,maint_done,maint_active,outside_total,outside_riyadh,outside_mecca,backup_total,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
                 ->execute([$_POST['amb_total']??49,$_POST['service_total']??32,$_POST['spec_total']??3,$_POST['fourwd_total']??7,$_POST['broken_amb']??5,$_POST['broken_fourwd']??1,$_POST['broken_total']??6,$_POST['maint_done']??16,$_POST['maint_active']??6,$_POST['outside_total']??6,$_POST['outside_riyadh']??2,$_POST['outside_mecca']??4,$_POST['backup_total']??19,$_SESSION['op_user_id']??null]);
            $msg = 'تم إرسال بيانات توزيع المركبات';
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'send_maint') {
        try {
            $stmt = pdo()->prepare("INSERT INTO fleet_maintenance (veh_type,veh_num,veh_class,description,location,action_taken,request_date,days_out,readiness,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $saved = 0;
            foreach(($_POST['vtype']??[]) as $i=>$v){
                if(trim($v)==='') continue;
                $stmt->execute([$v,$_POST['vnum'][$i]??'',$_POST['vclass'][$i]??'',$_POST['desc'][$i]??'',$_POST['loc'][$i]??'',$_POST['action'][$i]??'',$_POST['rdate'][$i]??null,$_POST['days_out'][$i]??0,$_POST['ready'][$i]??'',$_POST['notes'][$i]??'',$_SESSION['op_user_id']??null]);
                $saved++;
            }
            $msg = "تم إرسال $saved طلب صيانة";
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'send_service') {
        try {
            $stmt = pdo()->prepare("INSERT INTO fleet_service_vehicles (veh_type,veh_num,veh_class,description,location,action_taken,request_date,days_out,readiness,notes,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,'active',?,NOW())");
            $saved = 0;
            foreach(($_POST['vtype']??[]) as $i=>$v){
                if(trim($v)==='') continue;
                $stmt->execute([$v,$_POST['vnum'][$i]??'',$_POST['vclass'][$i]??'',$_POST['desc'][$i]??'',$_POST['loc'][$i]??'',$_POST['action'][$i]??'',$_POST['rdate'][$i]??null,$_POST['days_out'][$i]??0,$_POST['ready'][$i]??'',$_POST['notes'][$i]??'',$_SESSION['op_user_id']??null]);
                $saved++;
            }
            $msg = "تم إرسال $saved مركبة خدمة";
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'send_tech') {
        try {
            $stmt = pdo()->prepare("INSERT INTO fleet_tech_support (ticket_num,department,description,veh_card,model,frame_num,submit_time,status,close_time,rating,request_type,action_taken,completion_method,reviewed_by,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $saved = 0;
            foreach(($_POST['ticket']??[]) as $i=>$t){
                if(trim($t)==='') continue;
                $stmt->execute([$t,$_POST['dept'][$i]??'',$_POST['tdesc'][$i]??'',$_POST['vcard'][$i]??'',$_POST['model'][$i]??'',$_POST['frame'][$i]??'',$_POST['submit_t'][$i]??'',$_POST['tstatus'][$i]??'',$_POST['close_t'][$i]??'',$_POST['rating'][$i]??'',$_POST['req_type'][$i]??'',$_POST['taction'][$i]??'',$_POST['method'][$i]??'',$_POST['reviewed'][$i]??'',$_SESSION['op_user_id']??null]);
                $saved++;
            }
            $msg = "تم إرسال $saved طلب دعم فني";
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'send_budget') {
        try {
            pdo()->prepare("INSERT INTO fleet_budget (requests_count,actual_budget,total_budget,spending,created_by,created_at) VALUES (?,?,?,?,?,NOW())")
                 ->execute([$_POST['b_requests']??0,$_POST['b_actual']??0,$_POST['b_total']??0,$_POST['b_spending']??0,$_SESSION['op_user_id']??null]);
            $msg = 'تم إرسال بيانات البنود والرصيد';
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'save_rec') {
        try {
            pdo()->prepare("INSERT INTO fleet_recommendations (rec_main,rec_risks,rec_actions,rec_notes,created_by,created_at) VALUES (?,?,?,?,?,NOW())")
                 ->execute([$_POST['rec_main']??'',$_POST['rec_risks']??'',$_POST['rec_actions']??'',$_POST['rec_notes']??'',$_SESSION['op_user_id']??null]);
            $msg = 'تم حفظ التوصيات بنجاح';
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }
}

$tab = $_GET['tab'] ?? 'chart';
$lastRec = [];
try { $lastRec = pdo()->query("SELECT * FROM fleet_recommendations ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}

$maintData = [
    ['فوردفان','4653','نقل','عطل بالمحرك','الصيبدة','متوقفة من المركز الرئيسي','2026-04-02',70,'جاري التقرير','بمحاضرة مكتبية'],
    ['ترانزيت','4075','نقل','عطل بالمحرك','الصيبدة','متوقفة من المركز الرئيسي','2026-04-29',45,'جاري التقرير','بمحاضرة مكتبية'],
    ['ترانزيت','4664','نقل','عطل بالمحرك','الصيبدة','متوقفة من المركز الرئيسي','2026-05-06',6,'جاري التقرير','بمحاضرة مكتبية'],
    ['ترانزيت','4057','متوسط','حرارة وقرامل','ورشة خارجية','انتظار القطع','2026-06-09',2,'جاري التقرير',''],
    ['ترانزيت','6539','متوسط','شبك عام','ورشة خارجية','تحت الفحص','2026-06-11',1,'جاري التقرير',''],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>قسم تشغيل وصيانة الأسطول</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#f3f4f6;direction:rtl;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #e5e7eb;padding:10px 16px;display:flex;align-items:center;justify-content:space-between}
.org-name{font-size:13px;font-weight:700;color:#dc2626}
.org-sub{font-size:9px;color:#9ca3af}
.tabs-bar{display:flex;border-bottom:1px solid #e5e7eb;background:#f9fafb;padding:0 10px;overflow-x:auto}
.tab-link{padding:9px 12px;font-size:11px;font-weight:700;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none;white-space:nowrap;display:inline-block}
.tab-link.active{color:#dc2626;border-bottom-color:#dc2626}
.tab-link:hover:not(.active){color:#374151}
.section{padding:14px}
.public-badge{text-align:center;font-size:10px;color:#9ca3af;padding:3px 0 8px}
.public-badge span{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:4px;padding:2px 10px}
.sec-title{display:flex;align-items:center;gap:6px;margin-bottom:10px}
.sec-title .bar{width:4px;height:20px;border-radius:2px;background:#dc2626}
.sec-title h2{font-size:13px;font-weight:700;color:#dc2626}

/* شارت */
.chart-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:12px}
.chart-title{font-size:14px;font-weight:700;color:#dc2626;text-align:center;margin-bottom:10px}
canvas#lineChart{width:100%!important;display:block}
.chart-legend{display:flex;justify-content:center;gap:24px;margin-top:8px}
.leg-item{display:flex;align-items:center;gap:5px;font-size:11px;color:#374151}
.leg-line{width:28px;height:3px;border-radius:2px}

/* بطاقات التوزيع */
.cards-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:600px){.cards-grid{grid-template-columns:1fr}}
.card-block{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}
.cbt{padding:7px 12px;font-size:12px;font-weight:700;text-align:center;color:#fff}
.cbt.gray{background:#9ca3af}.cbt.red{background:#dc2626}.cbt.dark{background:#6b7280}
.cbb{display:grid}
.cbb.c4{grid-template-columns:repeat(4,1fr)}
.cbb.c3{grid-template-columns:repeat(3,1fr)}
.cbb.c2{grid-template-columns:repeat(2,1fr)}
.cs{padding:10px 8px;text-align:center;border-left:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb}
.cs:last-child{border-left:none}
.cs-lbl{font-size:10px;color:#6b7280;margin-bottom:4px;line-height:1.3}
.cs-val{font-size:22px;font-weight:700;color:#111827}
.cs-val input{font-size:22px;font-weight:700;color:#111827;border:none;background:transparent;outline:none;text-align:center;width:60px;font-family:inherit}
.cs-val input:focus{background:#fef2f2;border-radius:4px}

/* دوائر */
.dist-section{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:12px}
.dist-grid{display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:0;padding:10px;align-items:start}
.dist-pie-row{display:flex;align-items:center;gap:8px;padding:4px 4px 8px}
.dist-pie-lbl{font-size:10px;color:#6b7280;line-height:1.4}
.dist-pie-lbl strong{font-size:13px;color:#111827}
.dist-right-panel{border-right:1px solid #e5e7eb;padding:10px}

/* الجداول */
.tbl-wrap{overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff;margin-bottom:12px}
table{width:100%;border-collapse:collapse;font-size:10.5px}
th{background:#9ca3af;color:#fff;padding:7px 7px;font-weight:600;text-align:center;white-space:nowrap;border-left:1px solid rgba(255,255,255,.2)}
th:last-child{border-left:none}
th.teal{background:#0d9488}
td{padding:6px 7px;border-bottom:1px solid #f0f0f0;color:#111827;text-align:center;vertical-align:middle;border-left:1px solid #f0f0f0}
td:last-child{border-left:none}
tbody tr:nth-child(even) td{background:#f9fafb}
tbody tr:hover td{background:#fef2f2}
td input,td textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:10.5px;color:#111827;text-align:center;resize:none}
td textarea{min-height:26px;line-height:1.3;text-align:right}
td input:focus,td textarea:focus{background:#fef2f2;border-radius:3px}
.nc{color:#9ca3af;font-size:9px}
.pending{color:#dc2626;font-size:10px;font-weight:600}
.done-badge{background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:3px;padding:1px 6px;font-size:9px;font-weight:700}
.active-badge{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:3px;padding:1px 6px;font-size:9px;font-weight:700}
.vtype{background:#e5e7eb;border-radius:3px;padding:1px 6px;font-size:9px;font-weight:700;color:#374151}
.add-btn{display:flex;align-items:center;gap:4px;padding:6px 10px;font-size:10px;color:#dc2626;cursor:pointer;background:#fff;border:none;border-top:1px solid #f0f0f0;width:100%;font-family:inherit}
.add-btn:hover{background:#fef2f2}

/* البنود */
.budget-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px}
@media(max-width:600px){.budget-cards{grid-template-columns:1fr 1fr}}
.budget-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;text-align:center}
.bc-icon{font-size:22px;margin-bottom:4px}
.bc-val{font-size:14px;font-weight:700;color:#111827}
.bc-val input{font-size:14px;font-weight:700;color:#111827;border:none;background:transparent;outline:none;text-align:center;width:100%;font-family:inherit}
.bc-lbl{font-size:10px;color:#6b7280;margin-top:3px}

.send-bar{display:flex;justify-content:flex-end;padding:10px 0 2px}
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
</style>
</head>
<body>

<div class="page-header">
  <div>
    <div class="org-name">قسم تشغيل وصيانة الأسطول</div>
    <div class="org-sub">SAUDI RED CRESCENT AUTHORITY</div>
  </div>
  <svg width="38" height="38" viewBox="0 0 44 44">
    <circle cx="22" cy="22" r="20" fill="#fef2f2" stroke="#fca5a5" stroke-width="1"/>
    <path d="M22 7 a15 15 0 0 1 0 30 a11 11 0 0 0 0-30z" fill="#991b1b"/>
    <polygon points="26,13 27.2,16.8 31,16.8 28,19.2 29.2,23 26,20.8 22.8,23 24,19.2 21,16.8 24.8,16.8" fill="#991b1b"/>
  </svg>
</div>

<?php if($msg): ?>
<div class="msg <?= str_contains($msg,'تم')||str_contains($msg,'نجاح')?'ok':'err' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="tabs-bar">
  <a class="tab-link <?= $tab==='chart'?'active':'' ?>" href="?tab=chart">📈 الوضع التشغيلي</a>
  <a class="tab-link <?= $tab==='dist'?'active':'' ?>" href="?tab=dist">🚑 توزيع المركبات</a>
  <a class="tab-link <?= $tab==='maint'?'active':'' ?>" href="?tab=maint">🔧 طلبات الصيانة</a>
  <a class="tab-link <?= $tab==='service'?'active':'' ?>" href="?tab=service">🚐 مركبات الخدمة</a>
  <a class="tab-link <?= $tab==='tech'?'active':'' ?>" href="?tab=tech">🛠️ الدعم الفني</a>
  <a class="tab-link <?= $tab==='budget'?'active':'' ?>" href="?tab=budget">💰 البنود والرصيد</a>
  <a class="tab-link <?= $tab==='rec'?'active':'' ?>" href="?tab=rec">📌 التوصيات</a>
</div>

<!-- ===== الوضع التشغيلي ===== -->
<?php if($tab==='chart'): ?>
<div class="section">
  <div class="public-badge"><span>🔒 Public —</span></div>
  <div class="chart-wrap">
    <div class="chart-title">الوضع التشغيلي لسيارات الإسعاف والخدمة</div>
    <canvas id="lineChart" height="200"></canvas>
    <div class="chart-legend">
      <div class="leg-item"><div class="leg-line" style="background:#2563eb"></div> سيارات الإسعاف</div>
      <div class="leg-item"><div class="leg-line" style="background:#f97316"></div> سيارات الخدمة</div>
    </div>
  </div>
  <div class="send-bar"><button class="btn-send" onclick="sendData()">إرسال للأدمن</button></div>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== توزيع المركبات ===== -->
<?php if($tab==='dist'): ?>
<div class="section">
  <div class="public-badge"><span>🔒 Public —</span></div>
  <form method="post">
  <input type="hidden" name="action" value="send_dist">
  <div class="cards-grid">
    <div class="card-block">
      <div class="cbt gray">المركبات</div>
      <div class="cbb c4">
        <div class="cs"><div class="cs-lbl">مركبات اسعافية</div><div class="cs-val"><input name="amb_total" type="number" value="49"></div></div>
        <div class="cs"><div class="cs-lbl">مركبات بنفتي دفع رباعي</div><div class="cs-val"><input name="fourwd_total" type="number" value="7"></div></div>
        <div class="cs"><div class="cs-lbl">مركبات خدمة</div><div class="cs-val"><input name="service_total" type="number" value="32"></div></div>
        <div class="cs"><div class="cs-lbl">استجابة نوعية</div><div class="cs-val"><input name="spec_total" type="number" value="3"></div></div>
      </div>
    </div>
    <div class="card-block">
      <div class="cbt red">المركبات المتعطلة</div>
      <div class="cbb c3">
        <div class="cs"><div class="cs-lbl">مركبات بنفتي دفع رباعي</div><div class="cs-val"><input name="broken_fourwd" type="number" value="1"></div></div>
        <div class="cs"><div class="cs-lbl">اسعافي</div><div class="cs-val"><input name="broken_amb" type="number" value="5"></div></div>
        <div class="cs"><div class="cs-lbl">إجمالي</div><div class="cs-val" style="color:#dc2626"><input name="broken_total" type="number" value="6" style="color:#dc2626"></div></div>
      </div>
    </div>
  </div>

  <div class="card-block" style="margin-bottom:12px">
    <div class="cbt dark">طلبات الصيانة</div>
    <div class="cbb c2">
      <div class="cs"><div class="cs-lbl">قائم</div><div class="cs-val"><input name="maint_active" type="number" value="6"></div></div>
      <div class="cs"><div class="cs-lbl">تم الحل</div><div class="cs-val"><input name="maint_done" type="number" value="16"></div></div>
    </div>
  </div>

  <!-- توزيع المركبات الإسعافية في القطاعات -->
  <div class="dist-section">
    <div class="cbt gray">توزيع المركبات الإسعافية في القطاعات</div>
    <div class="dist-grid">
      <div>
        <div class="dist-pie-row">
          <canvas id="pie1" width="60" height="60"></canvas>
          <div class="dist-pie-lbl">تبوك ١<br><strong>8</strong> إسعافي + <strong>5</strong> تظاهر</div>
        </div>
        <div class="dist-pie-row">
          <canvas id="pie2" width="60" height="60"></canvas>
          <div class="dist-pie-lbl">تبوك ٢<br><strong>12</strong> إسعافي + <strong>3</strong> تظاهر</div>
        </div>
      </div>
      <div>
        <div class="dist-pie-row">
          <canvas id="pie3" width="60" height="60"></canvas>
          <div class="dist-pie-lbl">تبوك ٣<br><strong>7</strong> إسعافي + <strong>2</strong> تظاهر</div>
        </div>
        <div class="dist-pie-row">
          <canvas id="pie4" width="60" height="60"></canvas>
          <div class="dist-pie-lbl">تبوك ٤<br><strong>5</strong> إسعافي + <strong>2</strong> تظاهر</div>
        </div>
      </div>
      <div class="dist-right-panel">
        <div style="font-size:11px;font-weight:700;color:#374151;text-align:center;margin-bottom:10px">توزيع المركبات الإسعافية<br>خارج المنطقة</div>
        <div style="display:flex;gap:16px;justify-content:center;margin-bottom:8px">
          <div style="text-align:center"><div style="font-size:24px;font-weight:700;color:#2563eb"><input name="outside_total" type="number" value="6" style="font-size:20px;font-weight:700;color:#2563eb;border:none;background:transparent;outline:none;text-align:center;width:50px;font-family:inherit"></div><div style="font-size:9px;color:#6b7280">الإجمالي</div></div>
          <div style="font-size:11px;color:#374151;line-height:1.8">
            <input name="outside_riyadh" type="number" value="2" style="width:30px;border:none;font-family:inherit;font-size:11px;font-weight:700;outline:none;text-align:center;background:#f3f4f6;border-radius:4px"> دعم الرياض<br>
            <input name="outside_mecca" type="number" value="4" style="width:30px;border:none;font-family:inherit;font-size:11px;font-weight:700;outline:none;text-align:center;background:#f3f4f6;border-radius:4px"> دعم مكة
          </div>
        </div>
        <div style="border-top:1px solid #f0f0f0;padding-top:8px;display:flex;justify-content:center;align-items:center;gap:10px">
          <div style="font-size:10px;color:#6b7280">إجمالي المركبات الاحتياطية</div>
          <div style="font-size:28px;font-weight:700;color:#dc2626"><input name="backup_total" type="number" value="19" style="font-size:24px;font-weight:700;color:#dc2626;border:none;background:transparent;outline:none;text-align:center;width:50px;font-family:inherit"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== طلبات الصيانة ===== -->
<?php if($tab==='maint'): ?>
<div class="section">
  <div class="public-badge"><span>🔒 Public —</span></div>
  <form method="post">
  <input type="hidden" name="action" value="send_maint">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th style="width:18px">م</th><th>نوع المركبة</th><th style="width:45px">الرقم</th><th style="width:55px">النوع</th>
        <th>الوصف</th><th style="width:65px">موقع المركبة</th><th>الإجراء المتخذ</th>
        <th style="width:78px">تاريخ الطلب</th><th style="width:45px">خروج عن الخدمة</th><th style="width:65px">الجاهزية</th><th>الملاحظة</th>
      </tr></thead>
      <tbody id="maint-body">
        <?php foreach($maintData as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="vtype[]" value="<?= h($r[0]) ?>" class="vtype" style="background:#e5e7eb;border-radius:3px;text-align:center;padding:1px 4px;font-weight:700;font-size:10px"></td>
          <td><input name="vnum[]" value="<?= h($r[1]) ?>"></td>
          <td><input name="vclass[]" value="<?= h($r[2]) ?>"></td>
          <td><input name="desc[]" value="<?= h($r[3]) ?>"></td>
          <td><input name="loc[]" value="<?= h($r[4]) ?>"></td>
          <td><textarea name="action[]" rows="2"><?= h($r[5]) ?></textarea></td>
          <td><input type="date" name="rdate[]" value="<?= h($r[6]) ?>"></td>
          <td><input type="number" name="days_out[]" value="<?= h($r[7]) ?>"></td>
          <td><input name="ready[]" value="<?= h($r[8]) ?>" class="pending" style="font-weight:600;font-size:10px;color:#dc2626"></td>
          <td><textarea name="notes[]" rows="2" style="color:#dc2626"><?= h($r[9]) ?></textarea></td>
        </tr>
        <?php endforeach; ?>
        <?php for($i=count($maintData);$i<10;$i++): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="vtype[]" placeholder="—"></td><td><input name="vnum[]" placeholder="—"></td>
          <td><input name="vclass[]" placeholder="—"></td><td><input name="desc[]" placeholder="—"></td>
          <td><input name="loc[]" placeholder="—"></td><td><textarea name="action[]" rows="2" placeholder="..."></textarea></td>
          <td><input type="date" name="rdate[]"></td><td><input type="number" name="days_out[]" value="0"></td>
          <td><input name="ready[]" placeholder="—"></td><td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addMaint()">+ إضافة</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== مركبات الخدمة ===== -->
<?php if($tab==='service'): ?>
<div class="section">
  <div class="public-badge"><span>🔒 Public —</span></div>
  <form method="post">
  <input type="hidden" name="action" value="send_service">
  <div style="background:#9ca3af;color:#fff;padding:7px 12px;font-size:12px;font-weight:700;text-align:center;border-radius:8px 8px 0 0">مركبات الخدمة</div>
  <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:14px">
    <table>
      <thead><tr>
        <th style="width:18px">م</th><th>نوع المركبة</th><th>الرقم</th><th>النوع</th>
        <th>الوصف</th><th>موقع المركبة</th><th>الإجراء المتخذ</th>
        <th style="width:78px">تاريخ الطلب</th><th style="width:45px">خروج عن الخدمة</th><th style="width:65px">الجاهزية</th><th>الملاحظة</th>
      </tr></thead>
      <tbody id="svc-body">
        <tr>
          <td class="nc">1</td>
          <td><input name="vtype[]" value="فرشار"></td><td><input name="vnum[]" value="8991"></td>
          <td><input name="vclass[]" value="حافلة"></td><td><input name="desc[]" value="حادث في الجنب الأيمن"></td>
          <td><input name="loc[]" value="الصيبدة"></td><td><input name="action[]" value="انهاء إجراءات المرور"></td>
          <td><input type="date" name="rdate[]" value="2026-06-06"></td><td><input type="number" name="days_out[]" value="5"></td>
          <td><input name="ready[]" value="جاري التقرير" class="pending" style="font-size:10px;color:#dc2626;font-weight:600"></td>
          <td><textarea name="notes[]" rows="2" style="color:#dc2626">نسبة الخطأ على الزميل يؤمن 100%</textarea></td>
        </tr>
        <?php for($i=2;$i<=5;$i++): ?>
        <tr>
          <td class="nc"><?=$i?></td>
          <td><input name="vtype[]" placeholder="—"></td><td><input name="vnum[]" placeholder="—"></td>
          <td><input name="vclass[]" placeholder="—"></td><td><input name="desc[]" placeholder="—"></td>
          <td><input name="loc[]" placeholder="—"></td><td><input name="action[]" placeholder="—"></td>
          <td><input type="date" name="rdate[]"></td><td><input type="number" name="days_out[]" value="0"></td>
          <td><input name="ready[]" placeholder="—"></td><td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addService()">+ إضافة</button>
  </div>

  <!-- المنجز -->
  <div style="background:#6b7280;color:#fff;padding:7px 12px;font-size:12px;font-weight:700;text-align:center;border-radius:8px 8px 0 0">المنجز</div>
  <div class="tbl-wrap" style="border-radius:0 0 8px 8px;margin-bottom:12px">
    <table>
      <thead><tr>
        <th style="width:18px">م</th><th>نوع المركبة</th><th>الرقم</th><th>النوع</th>
        <th>الوصف</th><th>الموقع</th><th>الإجراء</th><th>تاريخ الطلب</th><th>مدة الإنجاز</th>
      </tr></thead>
      <tbody id="done-svc-body">
        <?php for($i=1;$i<=4;$i++): ?>
        <tr>
          <td class="nc"><?=$i?></td>
          <td><input placeholder="—"></td><td><input placeholder="—"></td><td><input placeholder="—"></td>
          <td><input placeholder="—"></td><td><input placeholder="—"></td><td><input placeholder="—"></td>
          <td><input type="date"></td><td><input placeholder="—"></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addDoneSvc()">+ إضافة</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== الدعم الفني ===== -->
<?php if($tab==='tech'): ?>
<div class="section">
  <div class="public-badge"><span>🔒 Public —</span></div>
  <div style="font-size:16px;font-weight:700;color:#dc2626;text-align:center;margin-bottom:10px">طلبات الدعم الفني</div>
  <form method="post">
  <input type="hidden" name="action" value="send_tech">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th class="teal" style="width:16px">م</th>
        <th class="teal" style="width:80px">رقم الطلب</th>
        <th class="teal">القسم/المركز</th>
        <th class="teal">الوصف</th>
        <th class="teal" style="width:65px">بطاقة المركبة</th>
        <th class="teal" style="width:65px">الموديل</th>
        <th class="teal" style="width:55px">رقم الإطار</th>
        <th class="teal" style="width:90px">وقت التقديم</th>
        <th class="teal" style="width:70px">حالة الطلب</th>
        <th class="teal" style="width:80px">وقت الإغلاق</th>
        <th class="teal" style="width:50px">التقييم</th>
        <th class="teal" style="width:55px">نوع الطلب</th>
        <th class="teal">الإجراء</th>
        <th class="teal" style="width:75px">طريق الإنجاز</th>
        <th class="teal" style="width:75px">دقق من قبل</th>
      </tr></thead>
      <tbody id="tech-body">
        <tr>
          <td class="nc">1</td>
          <td><input name="ticket[]" value="2024/10/20" style="font-size:9px"></td>
          <td><input name="dept[]" value="مدارس طلب الاسعاف" style="font-size:9px"></td>
          <td><input name="tdesc[]" value="طلب بيانات المركبة الاحتياطية لتسليم للمهبط" style="font-size:9px"></td>
          <td><input name="vcard[]" value="108,200" style="font-size:9px"></td>
          <td><input name="model[]" value="PRT 5600" style="font-size:9px"></td>
          <td><input name="frame[]" style="font-size:9px"></td>
          <td><input name="submit_t[]" value="2024/10/20 10:21" style="font-size:9px"></td>
          <td><span class="done-badge">تم الإغلاق</span><input name="tstatus[]" value="تم الإغلاق" type="hidden"></td>
          <td><input name="close_t[]" value="2024/10/20" style="font-size:9px"></td>
          <td><input name="rating[]" style="font-size:9px"></td>
          <td><input name="req_type[]" value="طلب" style="font-size:9px"></td>
          <td><textarea name="taction[]" rows="2" style="font-size:9px">تزويد قسم الاسعاف بملحوظة بيانات المركبة الاحتياطية</textarea></td>
          <td><input name="method[]" value="طريق المكتبي" style="font-size:9px"></td>
          <td><input name="reviewed[]" value="طلبات ملحوظة" style="font-size:9px"></td>
        </tr>
        <?php for($i=2;$i<=8;$i++): ?>
        <tr>
          <td class="nc"><?=$i?></td>
          <?php for($c=0;$c<12;$c++): ?>
          <td><input style="font-size:9px" placeholder="—"></td>
          <?php endfor; ?>
          <td><textarea rows="2" style="font-size:9px" placeholder="..."></textarea></td>
          <td><input style="font-size:9px" placeholder="—"></td>
          <td><input style="font-size:9px" placeholder="—"></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-btn" onclick="addTech()">+ إضافة</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer"><div class="f-s" style="background:#1da1f2">t</div><div class="f-s" style="background:#e1306c">i</div><div class="f-s" style="background:#1877f2">f</div><div class="f-997">997</div></div>
<?php endif; ?>

<!-- ===== البنود والرصيد ===== -->
<?php if($tab==='budget'): ?>
<div class="section">
  <div class="public-badge"><span>🔒 Public —</span></div>
  <div style="font-size:16px;font-weight:700;color:#9ca3af;text-align:center;margin-bottom:12px">البنود والرصيد مع المتعهد</div>
  <div style="text-align:left;font-size:11px;color:#6b7280;margin-bottom:10px">رقم التعاقد</div>
  <form method="post">
  <input type="hidden" name="action" value="send_budget">
  <div class="budget-cards">
    <div class="budget-card"><div class="bc-icon">🏛️</div><div class="bc-val"><input name="b_requests" type="number" value="5616"></div><div class="bc-lbl">الطلبات<br>قائمة طلبات المراقبة</div></div>
    <div class="budget-card"><div class="bc-icon" style="color:#dc2626">👥</div><div class="bc-val" style="color:#dc2626"><input name="b_actual" value="22.33" style="color:#dc2626"></div><div class="bc-lbl">الموازنة الفعلية<br>قائمة طلبات المراقبة</div></div>
    <div class="budget-card"><div class="bc-icon">👤</div><div class="bc-val"><input name="b_total" value="1,331,516.77" style="font-size:12px"></div><div class="bc-lbl">الموازنة<br>قائمة طلبات المراقبة</div></div>
    <div class="budget-card"><div class="bc-icon">👤</div><div class="bc-val"><input name="b_spending" value="1,331,494.44" style="font-size:12px"></div><div class="bc-lbl">الصرف<br>قائمة طلبات المراقبة</div></div>
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
  <div class="rec-box"><div class="rec-head">⭐ التوصيات</div><textarea name="rec_main" placeholder="التوصيات..."><?=h($lastRec['rec_main']??'')?></textarea></div>
  <div class="rec-box"><div class="rec-head">⚠️ المخاطر والتحديات</div><textarea name="rec_risks" placeholder="المخاطر..."><?=h($lastRec['rec_risks']??'')?></textarea></div>
  <div class="rec-box"><div class="rec-head">✅ الإجراءات المقترحة</div><textarea name="rec_actions" placeholder="الإجراءات..."><?=h($lastRec['rec_actions']??'')?></textarea></div>
  <div class="rec-box"><div class="rec-head">📝 ملاحظات عامة</div><textarea name="rec_notes" placeholder="ملاحظات..."><?=h($lastRec['rec_notes']??'')?></textarea></div>
  <div class="send-bar"><button type="submit" class="btn-send">💾 حفظ التوصيات</button></div>
  </form>
</div>
<?php endif; ?>

<script>
function drawLineChart(){
  const canvas=document.getElementById('lineChart');
  if(!canvas)return;
  const ctx=canvas.getContext('2d');
  const W=canvas.parentElement.offsetWidth-28||560, H=200;
  canvas.width=W; canvas.height=H;
  const labels=['01-Jun','02-Jun','03-Jun','04-Jun','05-Jun','06-Jun','07-Jun','08-Jun','09-Jun','10-Jun','11-Jun'];
  const amb=[91.11,91.11,91.83,91.83,91.83,91.83,89.79,89.79,85.71,89.79,85.79];
  const svc=[100,100,94.11,94.11,100,97.43,97.43,97.43,97.43,97.43,97.43];
  const PAD={top:16,right:10,bottom:28,left:48};
  const cW=W-PAD.left-PAD.right, cH=H-PAD.top-PAD.bottom;
  const mn=75, mx=105, range=mx-mn;
  ctx.clearRect(0,0,W,H);
  ctx.fillStyle='#fff'; ctx.fillRect(0,0,W,H);
  [80,85,90,95,100,105].forEach(v=>{
    const y=PAD.top+(1-(v-mn)/range)*cH;
    ctx.strokeStyle='#f0f0f0'; ctx.lineWidth=1;
    ctx.beginPath(); ctx.moveTo(PAD.left,y); ctx.lineTo(W-PAD.right,y); ctx.stroke();
    ctx.fillStyle='#9ca3af'; ctx.font='9px sans-serif'; ctx.textAlign='right';
    ctx.fillText(v,PAD.left-4,y+3);
  });
  function drawLine(data,color){
    const pts=data.map((v,i)=>({x:PAD.left+i/(data.length-1)*cW,y:PAD.top+(1-(v-mn)/range)*cH}));
    ctx.beginPath(); ctx.strokeStyle=color; ctx.lineWidth=2; ctx.lineJoin='round';
    pts.forEach((p,i)=>i===0?ctx.moveTo(p.x,p.y):ctx.lineTo(p.x,p.y)); ctx.stroke();
    pts.forEach((p,i)=>{
      ctx.beginPath(); ctx.arc(p.x,p.y,3,0,Math.PI*2); ctx.fillStyle=color; ctx.fill();
      ctx.fillStyle=color; ctx.font='bold 8px sans-serif'; ctx.textAlign='center';
      ctx.fillText(data[i],p.x,p.y-6);
    });
  }
  drawLine(amb,'#2563eb'); drawLine(svc,'#f97316');
  labels.forEach((l,i)=>{
    const x=PAD.left+i/(labels.length-1)*cW;
    ctx.fillStyle='#9ca3af'; ctx.font='8px sans-serif'; ctx.textAlign='center';
    ctx.fillText(l,x,H-6);
  });
}

function drawPie(id,a,b){
  const c=document.getElementById(id); if(!c)return;
  const ctx=c.getContext('2d');
  const cx=30,cy=30,r=25,total=a+b;
  ctx.clearRect(0,0,60,60);
  let start=-Math.PI/2;
  [[a,'#2563eb'],[b,'#f97316']].forEach(([v,col])=>{
    const ang=v/total*Math.PI*2;
    ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,start,start+ang);
    ctx.closePath(); ctx.fillStyle=col; ctx.fill(); start+=ang;
  });
}

let mn=<?=count($maintData)+5?>, sn=5, dsn=4, tn=8;

function addMaint(){mn++;const tr=document.createElement('tr');tr.innerHTML=`<td class="nc">${mn}</td>`+'<td><input name="vtype[]" placeholder="—"></td><td><input name="vnum[]" placeholder="—"></td><td><input name="vclass[]" placeholder="—"></td><td><input name="desc[]" placeholder="—"></td><td><input name="loc[]" placeholder="—"></td><td><textarea name="action[]" rows="2" placeholder="..."></textarea></td><td><input type="date" name="rdate[]"></td><td><input type="number" name="days_out[]" value="0"></td><td><input name="ready[]" placeholder="—"></td><td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>';document.getElementById('maint-body')?.appendChild(tr);}

function addService(){sn++;const tr=document.createElement('tr');tr.innerHTML=`<td class="nc">${sn}</td>`+'<td><input name="vtype[]" placeholder="—"></td><td><input name="vnum[]" placeholder="—"></td><td><input name="vclass[]" placeholder="—"></td><td><input name="desc[]" placeholder="—"></td><td><input name="loc[]" placeholder="—"></td><td><input name="action[]" placeholder="—"></td><td><input type="date" name="rdate[]"></td><td><input type="number" name="days_out[]" value="0"></td><td><input name="ready[]" placeholder="—"></td><td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>';document.getElementById('svc-body')?.appendChild(tr);}

function addDoneSvc(){dsn++;const tr=document.createElement('tr');tr.innerHTML=`<td class="nc">${dsn}</td>`+'<td><input placeholder="—"></td>'.repeat(8);document.getElementById('done-svc-body')?.appendChild(tr);}

function addTech(){tn++;const tr=document.createElement('tr');tr.innerHTML=`<td class="nc">${tn}</td>`+'<td><input style="font-size:9px" placeholder="—"></td>'.repeat(12)+'<td><textarea rows="2" style="font-size:9px" placeholder="..."></textarea></td>'+'<td><input style="font-size:9px" placeholder="—"></td>'.repeat(2);document.getElementById('tech-body')?.appendChild(tr);}

function sendData(){alert('تم الإرسال ✅');}

setTimeout(()=>{
  drawLineChart();
  drawPie('pie1',8,5); drawPie('pie2',12,3); drawPie('pie3',7,2); drawPie('pie4',5,2);
},150);
window.addEventListener('resize',drawLineChart);
</script>

</body>
</html>