<?php
$REQUIRED_DEPT = 'إدارة الطوارئ';
$DEPT_ICON  = '🚨';
$DEPT_TITLE = 'إدارة الطوارئ';
$DEPT_COLOR = '#ea580c';
$DEPT_BG    = '#fff7ed';

require_once __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_emg'])) {
    try {
        $notes = trim($_POST['notes'] ?? '');
        $fields = [
            'إجمالي الفرضيات الداخلية القادمة'  => $_POST['in_total'] ?? '',
            'إجمالي الفرضيات الداخلية التي تمت'  => $_POST['in_done']  ?? '',
            'إجمالي الفرضيات الخارجية القادمة'   => $_POST['out_total'] ?? '',
            'إجمالي الفرضيات الخارجية التي تمت'  => $_POST['out_done']  ?? '',
            'التحذيرات'                           => $_POST['warnings']  ?? '',
        ];
        foreach($fields as $title => $val) {
            if(trim($val)==='') continue;
            pdo()->prepare("INSERT INTO operational_entries (report_year,report_month,department,section_name,title,value_1,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                 ->execute([date('Y'),date('m'),$department,'إدارة الطوارئ',$title,$val,$notes,$_SESSION['op_user_id']]);
        }
        if(!empty($_POST['note_reply'])){
            try{ pdo()->prepare("UPDATE operational_notes SET department_reply=?,replied_at=NOW(),is_read=1 WHERE department=? AND (is_read=0 OR is_read IS NULL)")->execute([trim($_POST['note_reply']),$department]); }catch(Throwable $e){}
        }
        $msg = "✅ تم حفظ بيانات إدارة الطوارئ وإرسالها للأدمن";
    } catch(Throwable $e){ $msg = "⚠️ خطأ: ".$e->getMessage(); }
}

$unreadNotes = [];
try {
    $s = pdo()->prepare("SELECT * FROM operational_notes WHERE department=? AND (is_read=0 OR is_read IS NULL) ORDER BY created_at DESC");
    $s->execute([$department]); $unreadNotes = $s->fetchAll();
} catch(Throwable $e){}

if(isset($_GET['mark_read'])){
    try{ pdo()->prepare("UPDATE operational_notes SET is_read=1 WHERE department=?")->execute([$department]); }catch(Throwable $e){}
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

function hoursLeft($t){ $d=strtotime($t)+86400-time(); return $d>0?floor($d/3600).'س '.floor(($d%3600)/60).'د':null; }
$months=['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>إدارة الطوارئ — الملف التشغيلي</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--accent:#ea580c;--line:#e5e7eb;--bg:#f3f4f6;--dark:#111827;--gray:#6b7280;--white:#fff;--r:14px}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:var(--bg);color:var(--dark);direction:rtl;font-size:14px}
.wrap{max-width:1200px;margin:0 auto;padding:20px}
.top-bar{background:#fff;border-bottom:1px solid var(--line);padding:0 24px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.brand{display:flex;align-items:center;gap:10px}
.brand-icon{width:38px;height:38px;border-radius:10px;background:#fff7ed;border:1px solid #fdba74;display:flex;align-items:center;justify-content:center;font-size:20px}
.brand-name{font-size:15px;font-weight:900}.brand-sub{font-size:11px;color:var(--gray)}
.header-right{display:flex;align-items:center;gap:8px}
.user-pill{background:#f3f4f6;border:1px solid var(--line);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700}
.logout-btn{background:#fef2f2;border:1px solid #fca5a5;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;color:#dc2626;text-decoration:none}
.notif-badge{background:#dc2626;color:#fff;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;cursor:pointer;animation:pr 1.5s infinite}
@keyframes pr{0%,100%{opacity:1}50%{opacity:.6}}
@keyframes bell{0%,100%{transform:rotate(0)}25%{transform:rotate(15deg)}75%{transform:rotate(-15deg)}}

.tabs{display:flex;gap:6px;margin-bottom:16px;border-bottom:2px solid var(--line)}
.tab-btn{height:38px;padding:0 18px;border:none;background:transparent;font-family:inherit;font-size:13px;font-weight:700;color:var(--gray);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
.tab-btn.active{color:#ea580c;border-bottom-color:#ea580c}
.tab-panel{display:none}.tab-panel.active{display:block}

.card{background:#fff;border:1px solid var(--line);border-radius:var(--r);margin-bottom:14px;overflow:hidden}
.card-head{padding:11px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;background:#fff7ed}
.card-title{font-size:14px;font-weight:900;color:var(--dark);display:flex;align-items:center;gap:7px}
.cdot{width:7px;height:7px;border-radius:50%;background:#ea580c;flex-shrink:0}
.card-body{padding:16px}

.date-banner{text-align:center;margin-bottom:18px}
.date-inner{display:inline-flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 24px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.date-day{font-size:26px;font-weight:900;color:#ea580c}

.emg-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:800px){.emg-grid{grid-template-columns:1fr}}

.counters-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
.counter-box{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:12px;text-align:center}
.counter-done{background:#f0fdf4;border-color:#86efac}
.counter-lbl{font-size:11px;color:var(--gray);font-weight:700;margin-bottom:6px}
.counter-val{font-size:32px;font-weight:900;color:#ea580c;cursor:text;background:transparent;border:none;width:80px;text-align:center;font-family:inherit;outline:none}
.counter-done .counter-val{color:#16a34a}
.counter-val:focus{background:#fff7ed;border-radius:8px}

.warn-box{background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:12px;margin-top:10px}
.warn-title{font-size:13px;font-weight:900;color:#dc2626;margin-bottom:6px;text-align:center}
.warn-input{width:100%;border:1.5px solid #fca5a5;border-radius:8px;padding:10px;font-family:inherit;font-size:13px;min-height:70px;resize:vertical;outline:none;background:#fff}
.warn-input:focus{border-color:#dc2626}

.tbl-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:var(--r);margin-bottom:10px}
table{width:100%;border-collapse:collapse;background:#fff}
th{background:#ea580c;color:#fff;padding:8px 10px;font-size:11px;font-weight:800;text-align:center;white-space:nowrap}
td{padding:7px 8px;border-bottom:1px solid var(--line);text-align:center;font-size:12px;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
td input,td select,td textarea{width:100%;border:none;background:transparent;font-family:inherit;font-size:12px;color:var(--dark);text-align:center;outline:none;padding:4px;border-radius:4px;cursor:text}
td input:focus,td select:focus{background:#fff7ed;outline:1px solid #fdba74}
.add-row{display:flex;align-items:center;gap:6px;padding:7px 12px;cursor:pointer;color:#ea580c;font-size:12px;font-weight:700;border-top:1px solid var(--line)}
.add-row:hover{background:#fff7ed}
.btn-del{background:none;border:none;color:#9ca3af;cursor:pointer;font-size:15px}
.btn-del:hover{color:#dc2626}

.comm-layout{display:grid;grid-template-columns:1fr 200px;gap:14px;align-items:start}
@media(max-width:800px){.comm-layout{grid-template-columns:1fr}}
.comm-stats{display:flex;flex-direction:column;gap:8px}
.comm-stat-row{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-radius:8px;font-size:13px;font-weight:700}
.stat-green{background:#dcfce7;color:#166534}
.stat-red{background:#fee2e2;color:#991b1b}
.stat-gray{background:#f3f4f6;color:#374151}
.stat-num{font-size:20px;font-weight:900}

.status-sel{border-radius:6px!important;padding:4px 6px!important;font-weight:800!important;font-size:11px!important;border:none!important;cursor:pointer!important}
.s-working{background:#16a34a;color:#fff}
.s-stopped{background:#eab308;color:#fff}
.s-broken{background:#dc2626;color:#fff}
.s-linked{background:#16a34a;color:#fff}
.s-notlinked{background:#dc2626;color:#fff}

.donut-wrap{text-align:center}

.tetra-layout{display:grid;grid-template-columns:1fr 200px;gap:14px;align-items:start}
@media(max-width:800px){.tetra-layout{grid-template-columns:1fr}}

.btn-save{height:44px;background:linear-gradient(135deg,#ea580c,#c2410c);color:#fff;border:none;border-radius:999px;padding:0 28px;font-family:inherit;font-size:14px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn-save:hover{filter:brightness(1.08)}
.btn-outline{height:44px;background:#fff;color:var(--dark);border:1px solid var(--line);border-radius:999px;padding:0 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer}
.prog-bar{height:8px;background:#f3f4f6;border-radius:999px;overflow:hidden;margin:10px 0 4px}
.prog-fill{height:100%;background:linear-gradient(90deg,#ea580c,#f97316);border-radius:999px;width:0%;transition:width .3s}
.msg-ok{background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:10px;padding:11px 16px;font-weight:800;margin-bottom:14px;font-size:13px}
.msg-warn{background:#fefce8;color:#713f12;border:1px solid #fde68a;border-radius:10px;padding:11px 16px;font-weight:800;margin-bottom:14px;font-size:13px}
.notif-box{background:linear-gradient(135deg,#fefce8,#fef9c3);border:2px solid #f59e0b;border-radius:14px;padding:16px;margin-bottom:16px;animation:pb 2s infinite}
@keyframes pb{0%,100%{border-color:#f59e0b}50%{border-color:#dc2626}}
</style>
</head>
<body>

<div class="top-bar">
  <div class="brand">
    <div class="brand-icon">🚨</div>
    <div><div class="brand-name">إدارة الطوارئ</div><div class="brand-sub">الملف التشغيلي — هيئة الهلال الأحمر السعودي</div></div>
  </div>
  <div class="header-right">
    <?php if(!empty($unreadNotes)): ?>
      <span class="notif-badge" onclick="switchTab('notes')">🔔 <?= count($unreadNotes) ?> ملاحظة</span>
    <?php endif; ?>
    <span class="user-pill">👤 <?= h($userName) ?></span>
    <a class="logout-btn" href="operational_logout.php">خروج</a>
  </div>
</div>

<div class="wrap">
<?php if($msg??''): ?>
  <div class="<?= str_starts_with($msg,'✅')?'msg-ok':'msg-warn' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="tabs">
  <button class="tab-btn active" id="tab-today" onclick="switchTab('today')">📝 اليوم</button>
  <button class="tab-btn" id="tab-prev" onclick="switchTab('prev')">📋 المرسلة سابقاً</button>
  <?php if(!empty($unreadNotes)): ?>
    <button class="tab-btn" id="tab-notes" onclick="switchTab('notes')">
      🔔 الملاحظات <span style="background:#dc2626;color:#fff;border-radius:999px;padding:1px 7px;font-size:10px"><?= count($unreadNotes) ?></span>
    </button>
  <?php endif; ?>
</div>

<!-- ===== تبويب اليوم ===== -->
<div class="tab-panel active" id="panel-today">

<div class="date-banner">
  <div class="date-inner">
    <div class="date-day"><?= date('j') ?></div>
    <div>
      <div style="font-size:14px;font-weight:700"><?= $months[date('n')-1] ?> <?= date('Y') ?>م</div>
      <div style="font-size:11px;color:var(--gray)"><?= ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][date('w')] ?></div>
    </div>
    <div style="background:#ea580c;color:#fff;border-radius:8px;padding:4px 12px;font-size:12px;font-weight:800">اليوم</div>
  </div>
</div>

<form method="post" id="mainForm">
<input type="hidden" name="save_emg" value="1">

<!-- ١: الفرضيات -->
<div class="card">
  <div class="card-head"><div class="card-title"><span class="cdot"></span>الفرضيات الداخلية والخارجية</div></div>
  <div class="card-body">
    <div class="emg-grid">

      <!-- داخلية -->
      <div>
        <div style="font-size:13px;font-weight:900;color:#dc2626;text-align:center;padding:8px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;margin-bottom:10px">الفرضيات الداخلية للربع الثاني</div>
        <div class="counters-grid">
          <div class="counter-box">
            <div class="counter-lbl">إجمالي الفرضيات القادمة</div>
            <input class="counter-val" type="number" name="in_total" placeholder="0" min="0">
          </div>
          <div class="counter-box counter-done">
            <div class="counter-lbl">إجمالي الفرضيات التي تمت</div>
            <input class="counter-val" type="number" name="in_done" placeholder="0" min="0">
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>الجهة</th><th>تاريخ الفرضية</th><th>نوع الفرضية</th>
                <th>موقع الفرضية</th><th>تاريخ الاجتماع التنسيقي</th><th></th>
              </tr>
            </thead>
            <tbody id="inBody"></tbody>
          </table>
          <div class="add-row" onclick="addInRow()">+ إضافة صف</div>
        </div>
      </div>

      <!-- خارجية -->
      <div>
        <div style="font-size:13px;font-weight:900;color:#dc2626;text-align:center;padding:8px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;margin-bottom:10px">الفرضيات الخارجية</div>
        <div class="counters-grid">
          <div class="counter-box">
            <div class="counter-lbl">إجمالي الفرضيات القادمة</div>
            <input class="counter-val" type="number" name="out_total" placeholder="0" min="0">
          </div>
          <div class="counter-box counter-done">
            <div class="counter-lbl">إجمالي الفرضيات التي تمت</div>
            <input class="counter-val" type="number" name="out_done" placeholder="0" min="0">
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>الجهة</th><th>تاريخ الفرضية</th><th>نوع الفرضية</th>
                <th>موقع الفرضية</th><th>تاريخ الاجتماع التنسيقي</th><th></th>
              </tr>
            </thead>
            <tbody id="outBody"></tbody>
          </table>
          <div class="add-row" onclick="addOutRow()">+ إضافة صف</div>
        </div>
        <div class="warn-box">
          <div class="warn-title">التحذيرات</div>
          <textarea class="warn-input" name="warnings" placeholder="أدخل التحذيرات هنا..."></textarea>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ٢: وسائل الاتصال -->
<div class="card">
  <div class="card-head"><div class="card-title"><span class="cdot"></span>وسائل الاتصال</div></div>
  <div class="card-body">
    <div class="comm-layout">
      <div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>وسيلة الاتصال</th><th>الرقم</th><th>حالة الارتباط</th></tr></thead>
            <tbody id="commBody"></tbody>
          </table>
        </div>
      </div>
      <div>
        <div class="donut-wrap">
          <canvas id="commDonut" width="160" height="160"></canvas>
        </div>
        <div class="comm-stats" id="commStats"></div>
      </div>
    </div>
  </div>
</div>

<!-- ٣: وسيلة تترا -->
<div class="card">
  <div class="card-head"><div class="card-title"><span class="cdot"></span>وسيلة اتصال تترا مع الجهات الحكومية والشركات ذات العلاقة</div></div>
  <div class="card-body">
    <div class="tetra-layout">
      <div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th style="width:30px">م</th><th>الجهة</th><th>حالة الربط</th><th>الملاحظات</th></tr></thead>
            <tbody id="tetraBody"></tbody>
          </table>
        </div>
      </div>
      <div>
        <div class="donut-wrap">
          <canvas id="tetraDonut" width="160" height="160"></canvas>
        </div>
        <div class="comm-stats" id="tetraStats"></div>
      </div>
    </div>
  </div>
</div>

<!-- ملاحظات + حفظ -->
<div class="card">
  <div class="card-head"><div class="card-title"><span class="cdot"></span>ملاحظات عامة</div></div>
  <div class="card-body">
    <textarea name="notes" placeholder="أي ملاحظات تشغيلية إضافية..."
      style="width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:12px;font-family:inherit;font-size:13px;min-height:80px;resize:vertical;outline:none"
      onfocus="this.style.borderColor='#ea580c'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:14px 18px">
    <div style="display:flex;justify-content:space-between;margin-bottom:4px">
      <span style="font-size:12px;font-weight:700;color:var(--gray)">تقدم الإدخال</span>
      <span id="progTxt" style="font-size:12px;font-weight:800;color:#ea580c">0 حقل مكتمل</span>
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
  <?php
  $prev=[];
  try{$s=pdo()->prepare("SELECT * FROM operational_entries WHERE department=? AND DATE(created_at)=CURDATE() ORDER BY created_at DESC LIMIT 50");$s->execute([$department]);$prev=$s->fetchAll();}catch(Throwable $e){}
  ?>
  <?php if(empty($prev)): ?>
    <div style="text-align:center;padding:40px;color:var(--gray)"><div style="font-size:40px;margin-bottom:10px">📭</div><div style="font-size:15px;font-weight:700">لا توجد بيانات مرسلة اليوم</div></div>
  <?php else: ?>
  <div class="card">
    <div class="card-head"><div class="card-title"><span class="cdot"></span>بيانات إدارة الطوارئ المرسلة اليوم</div></div>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>#</th><th>المؤشر</th><th>القيمة</th><th>الوقت</th></tr></thead>
      <tbody>
        <?php foreach($prev as $i=>$e): ?>
        <tr>
          <td style="color:var(--gray);font-weight:700"><?= $i+1 ?></td>
          <td style="font-weight:700;text-align:right"><?= h($e['title']) ?></td>
          <td style="font-weight:900;color:#ea580c"><?= h($e['value_1']) ?></td>
          <td style="font-size:11px;color:var(--gray)"><?= h(substr($e['created_at'],11,5)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== تبويب الملاحظات ===== -->
<?php if(!empty($unreadNotes)): ?>
<div class="tab-panel" id="panel-notes">
  <div class="notif-box">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <span style="font-size:26px;animation:bell 1s ease-in-out infinite">🔔</span>
      <div><div style="font-size:14px;font-weight:900;color:#92400e">لديك <?= count($unreadNotes) ?> ملاحظة تتطلب الرد خلال 24 ساعة</div></div>
      <a href="?mark_read=1" style="margin-right:auto;background:#f59e0b;color:#fff;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;text-decoration:none">تم القراءة ✓</a>
    </div>
    <?php foreach($unreadNotes as $n):
      $hl=hoursLeft($n['created_at']); $urgent=$hl&&(int)explode('س',$hl)[0]<6;
    ?>
    <div style="background:rgba(0,0,0,.06);border-radius:8px;padding:10px 14px;margin-bottom:8px">
      <div style="font-size:13px;color:#78350f;font-weight:600;margin-bottom:5px">📋 <?= h($n['note_text']??$n['message']??'') ?></div>
      <div style="display:flex;align-items:center;gap:8px;font-size:11px;color:#a16207">
        <span>🕐 <?= h(substr($n['created_at'],0,16)) ?></span>
        <?php if($hl): ?>
          <span style="background:<?= $urgent?'#f59e0b':'#dc2626' ?>;color:#fff;border-radius:6px;padding:2px 8px;font-size:10px;font-weight:800">⏰ متبقي <?= $hl ?></span>
        <?php else: ?><span style="background:#dc2626;color:#fff;border-radius:6px;padding:2px 8px;font-size:10px;font-weight:800">⚠️ انتهى وقت الرد</span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="save_emg" value="1">
      <label style="font-size:12px;font-weight:800;color:#92400e;display:block;margin-bottom:5px">↩️ ردّك:</label>
      <textarea name="note_reply" rows="3" placeholder="اكتب ردّك هنا..."
        style="width:100%;border:1.5px solid #f59e0b;border-radius:8px;padding:8px;font-family:inherit;font-size:13px;resize:none;outline:none;background:#fffbeb"></textarea>
      <button class="btn-save" type="submit" style="height:36px;font-size:13px;margin-top:8px">إرسال الرد</button>
    </form>
  </div>
</div>
<?php endif; ?>

</div>

<script>
function switchTab(n){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('panel-'+n)?.classList.add('active');
  document.getElementById('tab-'+n)?.classList.add('active');
}

// ===== الفرضيات =====
let inRows=[], outRows=[];

function rowHtml(r,i,key){
  return `<tr>
    <td><input value="${r.org||''}"  placeholder="الجهة"   onchange="window['${key}'][${i}].org=this.value;save()"></td>
    <td><input value="${r.date||''}" placeholder="التاريخ" onchange="window['${key}'][${i}].date=this.value;save()"></td>
    <td><input value="${r.type||''}" placeholder="النوع"   onchange="window['${key}'][${i}].type=this.value;save()"></td>
    <td><input value="${r.loc||''}"  placeholder="الموقع"  onchange="window['${key}'][${i}].loc=this.value;save()"></td>
    <td><input value="${r.meet||''}" placeholder="التاريخ" onchange="window['${key}'][${i}].meet=this.value;save()"></td>
    <td><button type="button" class="btn-del" onclick="window['${key}'].splice(${i},1);render${key.charAt(0).toUpperCase()+key.slice(1)}()">✕</button></td>
  </tr>`;
}
function renderInRows(){
  const b=document.getElementById('inBody');
  if(!inRows.length){b.innerHTML='<tr><td colspan="6" style="padding:12px;color:var(--gray);font-size:12px;text-align:center">انقر + لإضافة صف</td></tr>';return;}
  b.innerHTML=inRows.map((r,i)=>rowHtml(r,i,'inRows')).join('');
}
function renderOutRows(){
  const b=document.getElementById('outBody');
  if(!outRows.length){b.innerHTML='<tr><td colspan="6" style="padding:12px;color:var(--gray);font-size:12px;text-align:center">انقر + لإضافة صف</td></tr>';return;}
  b.innerHTML=outRows.map((r,i)=>rowHtml(r,i,'outRows')).join('');
}
function addInRow(){inRows.push({org:'',date:'',type:'',loc:'',meet:''});renderInRows();}
function addOutRow(){outRows.push({org:'',date:'',type:'',loc:'',meet:''});renderOutRows();}

// ===== وسائل الاتصال =====
const COMM_LINES = [
  {name:'الهاتف الثابت',           num:'148619998',   status:'يعمل'},
  {name:'الهاتف المشفر (همس)',      num:'144274803',   status:'يعمل'},
  {name:'الخط الساخن',             num:'144221855',   status:'يعمل'},
  {name:'الجوال',                   num:'536534420',   status:'يعمل'},
  {name:'الثريا',                   num:'-',           status:'موقوف'},
  {name:'الفاكس',                   num:'144230417',   status:'يعمل'},
  {name:'الفاكس المشفر (الأمين)',   num:'144277367',   status:'يعمل'},
  {name:'تترا',                     num:'قناة المركز الوطني الاسعافي', status:'مرتبط'},
  {name:'نظام الاتصال المرئي (بروق)',num:'—',          status:'لا يعمل'},
  {name:'البريد الالكتروني',        num:'sh-sup@srca.org.sa', status:'يعمل'},
  {name:'فاكس تراسل',              num:'0144273565',   status:'متعطل'},
];
let commLines = JSON.parse(localStorage.getItem('emg_comm')||'null') || COMM_LINES.map(l=>({...l}));

function statusClass(s){
  if(s==='يعمل'||s==='مرتبط') return 's-working';
  if(s==='موقوف') return 's-stopped';
  return 's-broken';
}
function statusBg(s){
  if(s==='يعمل'||s==='مرتبط') return '#16a34a';
  if(s==='موقوف') return '#eab308';
  return '#dc2626';
}

function renderComm(){
  const b=document.getElementById('commBody');
  b.innerHTML=commLines.map((r,i)=>`<tr>
    <td style="font-weight:700;text-align:right">${r.name}</td>
    <td><input value="${r.num||''}" placeholder="الرقم" onchange="commLines[${i}].num=this.value;save();updateCommStats()"></td>
    <td>
      <select class="status-sel ${statusClass(r.status)}"
        style="background:${statusBg(r.status)};color:#fff;border-radius:6px;padding:4px 6px;font-weight:800;font-size:11px;border:none;cursor:pointer;width:100%"
        onchange="commLines[${i}].status=this.value;this.className='status-sel ${statusClass(r.status)}';this.style.background=statusBg(this.value);save();updateCommStats()">
        <option ${r.status==='يعمل'?'selected':''}>يعمل</option>
        <option ${r.status==='موقوف'?'selected':''}>موقوف</option>
        <option ${r.status==='لا يعمل'?'selected':''}>لا يعمل</option>
        <option ${r.status==='متعطل'?'selected':''}>متعطل</option>
        <option ${r.status==='مرتبط'?'selected':''}>مرتبط</option>
      </select>
    </td>
  </tr>`).join('');
  updateCommStats();
}

function updateCommStats(){
  const total=commLines.length;
  const linked=commLines.filter(r=>r.status==='يعمل'||r.status==='مرتبط').length;
  const notLinked=total-linked;
  drawDonut('commDonut',linked,notLinked,'#16a34a','#dc2626');
  const s=document.getElementById('commStats');
  if(s) s.innerHTML=`
    <div class="comm-stat-row stat-gray"><span>إجمالي وسائل الاتصال</span><span class="stat-num">${total}</span></div>
    <div class="comm-stat-row stat-green"><span>إجمالي المرتبطة</span><span class="stat-num">${linked}</span></div>
    <div class="comm-stat-row stat-red"><span>إجمالي الغير مرتبطة</span><span class="stat-num">${notLinked}</span></div>`;
}

// ===== تترا =====
const TETRA_ORGS = [
  'المستشفى العسكري تبوك','مستشفى الأمير فهد بن سلطان','الدفاع المدني',
  'عمليات الصحة تبوك','عمليات اماله','عمليات البحر الأحمر','أمن الطرق',
  'المرور','الدوريات الأمنية (الشرطة)','عمليات نيوم','اسعاف جامعة تبوك',
  'قوات الامن والحماية (نيوم)','تجمع تبوك الصحي','فرع الشؤون الصحية بتبوك',
  'مطار الأمير سلطان بن عبد العزيز','شركة ار بي ام','جمعية درع'
];
let tetraOrgs = JSON.parse(localStorage.getItem('emg_tetra')||'null') || TETRA_ORGS.map(n=>({org:n,status:'مرتبط',notes:''}));

function renderTetra(){
  const b=document.getElementById('tetraBody');
  b.innerHTML=tetraOrgs.map((r,i)=>`<tr>
    <td style="color:var(--gray);font-weight:700">${i+1}</td>
    <td style="font-weight:700;text-align:right">${r.org}</td>
    <td>
      <select style="background:${r.status==='مرتبط'?'#16a34a':'#dc2626'};color:#fff;border-radius:6px;padding:4px 6px;font-weight:800;font-size:11px;border:none;cursor:pointer;width:100%"
        onchange="tetraOrgs[${i}].status=this.value;this.style.background=this.value==='مرتبط'?'#16a34a':'#dc2626';save();updateTetraStats()">
        <option ${r.status==='مرتبط'?'selected':''}>مرتبط</option>
        <option ${r.status==='غير مرتبط'?'selected':''}>غير مرتبط</option>
      </select>
    </td>
    <td><input value="${r.notes||''}" placeholder="ملاحظات" onchange="tetraOrgs[${i}].notes=this.value;save()"></td>
  </tr>`).join('');
  updateTetraStats();
}

function updateTetraStats(){
  const total=tetraOrgs.length;
  const linked=tetraOrgs.filter(r=>r.status==='مرتبط').length;
  const pct=total?Math.round(linked/total*100):0;
  drawDonut('tetraDonut',linked,total-linked,'#3b82f6','#e5e7eb');
  const s=document.getElementById('tetraStats');
  if(s) s.innerHTML=`
    <div class="comm-stat-row stat-green"><span>إجمالي الجهات المتصلة</span><span class="stat-num">${linked}</span></div>
    <div class="comm-stat-row stat-gray"><span>نسبة الاتصال</span><span class="stat-num">${pct}%</span></div>`;
}

// ===== دونات =====
function drawDonut(id,val,rest,colA,colB){
  const c=document.getElementById(id);if(!c)return;
  const ctx=c.getContext('2d'),tot=val+rest||1;
  const cx=c.width/2,cy=c.height/2,r=62,ir=38;
  ctx.clearRect(0,0,c.width,c.height);
  ctx.beginPath();ctx.arc(cx,cy,r,0,Math.PI*2);ctx.fillStyle='#f3f4f6';ctx.fill();
  const ea=(val/tot)*Math.PI*2-Math.PI/2;
  ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,r,-Math.PI/2,ea);ctx.closePath();ctx.fillStyle=colA;ctx.fill();
  if(rest>0){ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,r,ea,-Math.PI/2);ctx.closePath();ctx.fillStyle=colB;ctx.fill();}
  ctx.beginPath();ctx.arc(cx,cy,ir,0,Math.PI*2);ctx.fillStyle='#fff';ctx.fill();
  ctx.fillStyle='#111827';ctx.font='bold 15px sans-serif';ctx.textAlign='center';ctx.textBaseline='middle';
  ctx.fillText(tot>0?Math.round(val/tot*100)+'%':'0%',cx,cy);
}

// ===== حفظ محلي =====
function save(){
  localStorage.setItem('emg_comm',JSON.stringify(commLines));
  localStorage.setItem('emg_tetra',JSON.stringify(tetraOrgs));
  localStorage.setItem('emg_in',JSON.stringify(inRows));
  localStorage.setItem('emg_out',JSON.stringify(outRows));
}
function loadLocal(){
  try{
    const c=localStorage.getItem('emg_comm'); if(c) commLines=JSON.parse(c);
    const t=localStorage.getItem('emg_tetra'); if(t) tetraOrgs=JSON.parse(t);
    const i=localStorage.getItem('emg_in'); if(i) inRows=JSON.parse(i);
    const o=localStorage.getItem('emg_out'); if(o) outRows=JSON.parse(o);
  }catch(e){}
}

// ===== شريط التقدم =====
function updateProgress(){
  const inputs=document.querySelectorAll('#panel-today form input:not([type=hidden]):not([type=number]),#panel-today form textarea,#panel-today form input[type=number]');
  const filled=Array.from(inputs).filter(el=>el.value.trim()!=='').length;
  const total=inputs.length;
  const pct=total?Math.round(filled/total*100):0;
  document.getElementById('progFill').style.width=pct+'%';
  document.getElementById('progTxt').textContent=filled+' / '+total+' حقل مكتمل';
}
document.addEventListener('input',updateProgress);
document.addEventListener('change',updateProgress);

// ===== Init =====
loadLocal();
renderInRows();
renderOutRows();
renderComm();
renderTetra();
updateProgress();
setTimeout(()=>{drawDonut('commDonut',commLines.filter(r=>r.status==='يعمل'||r.status==='مرتبط').length,commLines.filter(r=>r.status!=='يعمل'&&r.status!=='مرتبط').length,'#16a34a','#dc2626');drawDonut('tetraDonut',tetraOrgs.filter(r=>r.status==='مرتبط').length,tetraOrgs.filter(r=>r.status!=='مرتبط').length,'#3b82f6','#e5e7eb');},200);
</script>
</body>
</html>
