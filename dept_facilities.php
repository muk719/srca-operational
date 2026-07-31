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

    if ($action === 'send_stats') {
        try {
            pdo()->prepare("INSERT INTO ops_statistics (total,active,done,date_from,date_to,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())")
                 ->execute([$_POST['s_total']??0,$_POST['s_active']??0,$_POST['s_done']??0,$_POST['date_from']??null,$_POST['date_to']??null,$_SESSION['op_user_id']??null]);
            $msg = 'تم إرسال الإحصائيات للأدمن';
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'send_active') {
        try {
            $stmt = pdo()->prepare("INSERT INTO ops_requests (ticket_num,location,request_date,status,duration,completion_date,notes,request_type,created_by,created_at) VALUES (?,?,?,?,?,?,?,'active',?,NOW())");
            $saved = 0;
            foreach (($_POST['ticket']??[]) as $i=>$t) {
                if(trim($t)==='') continue;
                $stmt->execute([$t,$_POST['loc'][$i]??'',$_POST['rdate'][$i]??null,'تحت الإجراء',$_POST['dur'][$i]??'',$_POST['cdate'][$i]??null,$_POST['notes'][$i]??'',$_SESSION['op_user_id']??null]);
                $saved++;
            }
            $msg = "تم إرسال $saved طلب للأدمن";
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'send_done') {
        try {
            $stmt = pdo()->prepare("INSERT INTO ops_requests (ticket_num,location,request_date,status,duration,completion_date,notes,request_type,created_by,created_at) VALUES (?,?,?,?,?,?,?,'done',?,NOW())");
            $saved = 0;
            foreach (($_POST['ticket']??[]) as $i=>$t) {
                if(trim($t)==='') continue;
                $stmt->execute([$t,$_POST['loc'][$i]??'',$_POST['rdate'][$i]??null,'تم الحل',$_POST['dur'][$i]??'',$_POST['cdate'][$i]??null,$_POST['notes'][$i]??'',$_SESSION['op_user_id']??null]);
                $saved++;
            }
            $msg = "تم إرسال $saved طلب للأدمن";
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }

    if ($action === 'save_rec') {
        try {
            pdo()->prepare("INSERT INTO ops_recommendations (rec_main,rec_risks,rec_actions,rec_notes,created_by,created_at) VALUES (?,?,?,?,?,NOW())")
                 ->execute([$_POST['rec_main']??'',$_POST['rec_risks']??'',$_POST['rec_actions']??'',$_POST['rec_notes']??'',$_SESSION['op_user_id']??null]);
            $msg = 'تم حفظ التوصيات بنجاح';
        } catch (Throwable $e) { $msg = 'خطأ: '.$e->getMessage(); }
    }
}

$tab = $_GET['tab'] ?? 'stats';
$lastRec = [];
try { $lastRec = pdo()->query("SELECT * FROM ops_recommendations ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
$prevActive = [];
try { $prevActive = pdo()->query("SELECT * FROM ops_requests WHERE request_type='active' ORDER BY id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$prevDone = [];
try { $prevDone = pdo()->query("SELECT * FROM ops_requests WHERE request_type='done' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$activeData = [
    ['GEN0017162','الصفا المطلوب','2026-05-05','بينة المدخل الرئيسي لمركز اسعاف الصفا المطلوب'],
    ['GEN0017163','الصفا المطلوب','2026-05-05','تركيب افقال الكترونية العدد 2 لمركز اسعاف الصفا المطلوب'],
    ['GEN0017164','غرناطة','2026-05-05','تركيب الافقال الكترونية العدد 2 لمركز اسعاف غرناطة'],
    ['GEN0017165','الراجحي','2026-05-05','تركيب الافقال الكترونية العدد 2 لمركز اسعاف الراجحي'],
    ['GEN0017266','البدع','2026-05-11','تركيب دولاب لتثبيت الفرن الكهربائي السطحي لمركز البدع'],
    ['GEN0017267','نعي','2026-05-11','تركيب دولاب لتثبيت الفرن الكهربائي السطحي لمركز نعي'],
    ['GEN0017268','الحريبة','2026-05-11','تركيب دولاب لتثبيت الفرن الكهربائي السطحي لمركز الحريبة'],
    ['GEN0017270','أملح','2026-05-11','تركيب دولاب لتثبيت الفرن الكهربائي السطحي لمركز أملح'],
    ['GEN0017272','بداء','2026-05-11','استبدال الفرش الثالث لمركز بداء'],
    ['GEN0017273','الحريبة','2026-05-11','استبدال الفرش الثالث لمركز الحريبة'],
    ['GEN0017274','الحرة','2026-05-11','استبدال الفرش الثالث لمركز الحرة'],
    ['GEN0017275','نعي','2026-05-11','استبدال الفرش الثالث لمركز نعي'],
    ['GEN0017276','القلية','2026-05-11','استبدال الفرش الثالث لمركز القلية'],
    ['GEN0017277','ضباء','2026-05-11','استبدال الفرش الثالث لمركز ضباء'],
    ['GEN0017278','الوجه','2026-05-11','استبدال الفرش الثالث لمركز الوجه'],
    ['GEN0017279','الزيتة','2026-05-11','استبدال الفرش الثالث لمركز الزيتة'],
    ['GEN0017281','البدع','2026-05-11','استبدال الفرش الثالث لمركز البدع'],
    ['GEN0017291','الصفا','2026-05-12','بينة مدخل مركز اسعاف الصفا'],
    ['GEN0017292','الراجحي','2026-05-12','بينة مدخل مركز اسعاف الراجحي'],
    ['GEN0017293','غرناطة','2026-05-12','بينة مدخل مركز اسعاف غرناطة'],
    ['GEN0017294','تيماء','2026-05-12','بينة مدخل مركز اسعاف تيماء'],
    ['GEN0017296','المستودع الجديد','2026-05-12','تفصيل وتركيب رفوف التخزين للمستودعات'],
    ['GEN0017297','الفرع','2026-05-12','تركيب مصلات لسيارات الموظفين لمبنى الفرع'],
    ['GEN0017299','غرناطة','2026-05-12','تركيب مصلات لسيارات الموظفين لمركز غرناطة'],
    ['GEN0017300','الصفا','2026-05-12','تركيب مصلات لسيارات الموظفين لمركز الصفا'],
    ['GEN0017300','الراجحي','2026-05-12','تركيب مصلات لسيارات الموظفين لمركز الراجحي'],
    ['GEN0017301','تيماء','2026-05-12','تركيب مصلات لسيارات الموظفين لمركز تيماء'],
    ['GEN0017302','الروضة','2026-05-12','تركيب مصلات لسيارات الموظفين لمركز الروضة'],
    ['GEN0017303','تيماء','2026-05-12','تركيب مصلات لسيارات الموظفين لمركز تيماء'],
    ['GEN0017394','جميع المراكز','2026-05-19','تعديل ملاحظات بوكسات اسطوانات الاكسجين'],
    ['GEN0017460','الفرع','2026-06-24','تركيب عبوات ماء لاجمع مياه ادات مبنى الفرع'],
    ['GEN0017553','حقل','2026-06-07','اصلاح الفرن الكهربائي بالتكييف وتركيب بكرة في فم الاستخدام الخارجي'],
    ['GEN0017582','غرناطة','2026-06-09','تركيب طبق فضائي'],
];

$doneData = [
    ['GEN0017501','املج','2026-06-03','تم الحل','4 أيام 19 الساعات 27 دقيقة','2026-06-08','تركيب كبيل لسيارة الإسعاف'],
    ['GEN0017529','الفرع','2026-06-07','تم الحل','3 الساعات 59 دقيقة','2026-06-07','بحاجة الى توصيله كهربائية / بحاجة الى اصلاح مروحة الشفط'],
    ['GEN0017562','الصفا','2026-06-08','تم الحل','1 يوم 6 الساعات 36 دقيقة','2026-06-07','كرسي المكتب مكسور ويحتاج لصيانة'],
    ['GEN0017578','الفرع','2026-06-09','تم الحل','6 الساعات 10 دقيقة','2026-06-09','تركيب كلون للألواب عدد 4'],
    ['GEN0017065','البدع','2026-04-29','تم الحل','43 يوم 6 الساعات 27 دقيقة','2026-06-11','تركيب قاعدة للمقرر'],
    ['GEN0017546','حقل','2026-06-07','تم الحل','3 أيام 17 الساعات 45 دقيقة','2026-06-11','وجود تسرب مياه حدفيه المطبخ'],
    ['GEN0017552','حقل','2026-06-07','تم الحل','3 أيام 12 الساعات 44 دقيقة','2026-06-11','طلب تركيب جهاز ري الحديقة مع زراعة الأشجار'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>قسم تشغيل وصيانة المرافق</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#f3f4f6;direction:rtl;font-size:13px}

.page-header{background:#fff;border-bottom:1px solid #e5e7eb;padding:12px 20px;display:flex;align-items:center;justify-content:space-between}
.org-name{font-size:14px;font-weight:700;color:#0f6e56}
.org-sub{font-size:9px;color:#9ca3af;letter-spacing:.3px}

.tabs-bar{display:flex;border-bottom:1px solid #e5e7eb;background:#f9fafb;padding:0 14px;overflow-x:auto}
.tab-link{padding:9px 14px;font-size:12px;font-weight:700;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none;white-space:nowrap;display:inline-block}
.tab-link.active{color:#1d9e75;border-bottom-color:#1d9e75}
.tab-link:hover:not(.active){color:#374151}

.section{padding:16px}
.sec-title{display:flex;align-items:center;gap:7px;margin-bottom:12px}
.sec-title .bar{width:4px;height:22px;border-radius:2px}
.sec-title h2{font-size:14px;font-weight:700}

/* الإحصائيات */
.stats-center{text-align:center;margin-bottom:14px}
.stats-label{font-size:12px;font-weight:700;color:#374151;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:6px 20px;display:inline-block}
.stats-pillars{display:flex;justify-content:center;gap:20px;padding:16px 0 10px;flex-wrap:wrap}
.pillar{display:flex;flex-direction:column;align-items:center;width:130px}
.pillar-num{font-size:40px;font-weight:700;color:#d1d5db;line-height:1.1}
.pillar-body{width:114px;padding:10px 0 0;border-radius:8px 8px 0 0;display:flex;flex-direction:column;align-items:center}
.pillar-body.gray{background:#9ca3af}
.pillar-body.teal{background:#1d9e75}
.pillar-body.red{background:#dc2626}
.pillar-label{font-size:13px;font-weight:700;color:#fff;padding:2px 0 6px}
.pillar-circle{background:#fff;border-radius:50%;width:56px;height:56px;display:flex;align-items:center;justify-content:center;margin-bottom:0}
.pillar-val{font-size:22px;font-weight:700;border:none;background:transparent;text-align:center;width:50px;outline:none;font-family:inherit}
.pillar-tail{width:0;height:0;border-left:28px solid transparent;border-right:28px solid transparent}
.pillar-tail.gray{border-top:20px solid #9ca3af}
.pillar-tail.teal{border-top:20px solid #1d9e75}
.pillar-tail.red{border-top:20px solid #dc2626}

.date-range{display:flex;align-items:center;gap:8px;border:1.5px solid #dc2626;border-radius:20px;padding:5px 16px;font-size:11px;color:#dc2626;font-weight:700;width:fit-content;margin:8px auto 14px}
.date-range input[type=date]{border:none;outline:none;font-size:11px;font-family:inherit;color:#dc2626;background:transparent;cursor:pointer}

/* الجداول */
.tbl-wrap{overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff;margin-bottom:12px}
table{width:100%;border-collapse:collapse;font-size:11px}
th{padding:8px 8px;font-weight:700;text-align:center;white-space:nowrap;border-left:1px solid rgba(255,255,255,.2)}
th:last-child{border-left:none}
th.green{background:#1d9e75;color:#fff}
th.red{background:#dc2626;color:#fff}
td{padding:7px 8px;border-bottom:1px solid #f0f0f0;color:#111827;text-align:center;vertical-align:middle;border-left:1px solid #f0f0f0}
td:last-child{border-left:none}
tbody tr:nth-child(even) td{background:#f9fafb}
tbody tr:hover td{background:#f0fdf4}
td input,td textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:11px;color:#111827;text-align:center;resize:none}
td textarea{min-height:30px;line-height:1.3;text-align:right}
td input:focus,td textarea:focus{background:#f0fdf4;border-radius:3px}
.nc{color:#9ca3af;font-size:10px}
.badge-active{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
.badge-done{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;background:#f0fdf4;color:#166534;border:1px solid #86efac}
.add-row{display:flex;align-items:center;gap:5px;padding:7px 12px;font-size:11px;cursor:pointer;background:#fff;border:none;border-top:1px solid #f0f0f0;width:100%;font-family:inherit;font-weight:700}
.add-row:hover{background:#f0fdf4}

.send-bar{display:flex;justify-content:flex-end;padding:10px 0 2px}
.btn-send{padding:7px 20px;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-teal{background:#1d9e75}.btn-teal:hover{background:#0f6e56}
.btn-red{background:#dc2626}.btn-red:hover{background:#991b1b}

.rec-box{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:10px}
.rec-head{background:#f9fafb;padding:8px 14px;font-size:11px;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb}
.rec-box textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:12px;color:#111827;resize:vertical;min-height:72px;padding:8px 12px;direction:rtl;display:block}

.footer{display:flex;align-items:center;gap:5px;padding:10px 16px;border-top:1px solid #e5e7eb;justify-content:flex-end;background:#fff;margin-top:16px}
.f-social{width:18px;height:18px;border-radius:50%;font-size:8px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center}
.f-997{background:#dc2626;color:#fff;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700}

.msg{margin:10px 16px;padding:10px 14px;border-radius:8px;font-size:12px;font-weight:700;border:1px solid}
.msg.ok{background:#f0fdf4;color:#166534;border-color:#86efac}
.msg.err{background:#fef2f2;color:#991b1b;border-color:#fca5a5}
.empty{padding:20px;text-align:center;color:#9ca3af;font-size:11px}
</style>
</head>
<body>

<div class="page-header">
  <div>
    <div class="org-name">قسم تشغيل وصيانة المرافق</div>
    <div class="org-sub">SAUDI RED CRESCENT AUTHORITY</div>
  </div>
  <svg width="40" height="40" viewBox="0 0 44 44">
    <circle cx="22" cy="22" r="20" fill="#e1f5ee" stroke="#5dcaa5" stroke-width="1"/>
    <path d="M22 7 a15 15 0 0 1 0 30 a11 11 0 0 0 0-30z" fill="#0f6e56"/>
    <polygon points="26,13 27.2,16.8 31,16.8 28,19.2 29.2,23 26,20.8 22.8,23 24,19.2 21,16.8 24.8,16.8" fill="#0f6e56"/>
  </svg>
</div>

<?php if($msg): ?>
<div class="msg <?= str_contains($msg,'تم')||str_contains($msg,'نجاح')?'ok':'err' ?>">
  <?= h($msg) ?>
</div>
<?php endif; ?>

<div class="tabs-bar">
  <a class="tab-link <?= $tab==='stats'?'active':'' ?>" href="?tab=stats">📊 الإحصائيات</a>
  <a class="tab-link <?= $tab==='active'?'active':'' ?>" href="?tab=active">⚙️ تحت الإجراء</a>
  <a class="tab-link <?= $tab==='done'?'active':'' ?>" href="?tab=done">✅ تم الحل</a>
  <a class="tab-link <?= $tab==='prev'?'active':'' ?>" href="?tab=prev">📂 المرسلة</a>
  <a class="tab-link <?= $tab==='rec'?'active':'' ?>" href="?tab=rec">📌 التوصيات</a>
</div>

<!-- ===== الإحصائيات ===== -->
<?php if($tab==='stats'): ?>
<div class="section">
  <div class="stats-center">
    <span class="stats-label">احصائيات الطلبيات العامة لدعم الفني</span>
  </div>
  <form method="post">
  <input type="hidden" name="action" value="send_stats">
  <div class="stats-pillars">
    <!-- الإجمالي -->
    <div class="pillar">
      <div class="pillar-num">01</div>
      <div class="pillar-body gray">
        <div class="pillar-label">الاجمالي</div>
        <div class="pillar-circle"><input class="pillar-val" name="s_total" type="number" value="40" min="0"></div>
      </div>
      <div class="pillar-tail gray"></div>
    </div>
    <!-- القائمة -->
    <div class="pillar">
      <div class="pillar-num">02</div>
      <div class="pillar-body red">
        <div class="pillar-label">القائمة</div>
        <div class="pillar-circle"><input class="pillar-val" name="s_active" type="number" value="33" min="0" style="color:#dc2626"></div>
      </div>
      <div class="pillar-tail red"></div>
    </div>
    <!-- المنفذ -->
    <div class="pillar">
      <div class="pillar-num">03</div>
      <div class="pillar-body teal">
        <div class="pillar-label">المنفذ</div>
        <div class="pillar-circle"><input class="pillar-val" name="s_done" type="number" value="07" min="0" style="color:#0f6e56"></div>
      </div>
      <div class="pillar-tail teal"></div>
    </div>
  </div>
  <div class="date-range">
    <span>الفترة من</span>
    <input type="date" name="date_from" value="2026-06-01">
    <span>الى</span>
    <input type="date" name="date_to" value="2026-06-11">
  </div>
  <div class="send-bar"><button type="submit" class="btn-send btn-teal">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer">
  <div class="f-social" style="background:#1da1f2">t</div>
  <div class="f-social" style="background:#e1306c">i</div>
  <div class="f-social" style="background:#1877f2">f</div>
  <div class="f-997">997</div>
</div>
<?php endif; ?>

<!-- ===== تحت الإجراء ===== -->
<?php if($tab==='active'): ?>
<div class="section">
  <div class="sec-title"><div class="bar" style="background:#1d9e75"></div><h2 style="color:#1d9e75">طلبات تحت الإجراء</h2></div>
  <form method="post">
  <input type="hidden" name="action" value="send_active">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th class="green" style="width:22px">م</th>
          <th class="green">رقم الطلب</th>
          <th class="green" style="width:70px">الموقع</th>
          <th class="green" style="width:80px">تاريخ الطلب</th>
          <th class="green" style="width:75px">حالة الطلب</th>
          <th class="green" style="width:45px">مدة الإنجاز</th>
          <th class="green" style="width:80px">تاريخ الإنجاز</th>
          <th class="green">الملاحظات</th>
        </tr>
      </thead>
      <tbody id="abody">
        <?php foreach($activeData as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="ticket[]" value="<?= h($r[0]) ?>" style="color:#1d9e75;font-weight:700"></td>
          <td><input name="loc[]" value="<?= h($r[1]) ?>"></td>
          <td><input type="date" name="rdate[]" value="<?= h($r[2]) ?>"></td>
          <td><span class="badge-active">تحت الإجراء</span></td>
          <td><input name="dur[]" value="00"></td>
          <td><input type="date" name="cdate[]"></td>
          <td><textarea name="notes[]" rows="2"><?= h($r[3]) ?></textarea></td>
        </tr>
        <?php endforeach; ?>
        <?php for($i=count($activeData)+1;$i<=40;$i++): ?>
        <tr>
          <td class="nc"><?= $i ?></td>
          <td><input name="ticket[]" placeholder="GEN00..."></td>
          <td><input name="loc[]" placeholder="—"></td>
          <td><input type="date" name="rdate[]"></td>
          <td><span class="badge-active" style="opacity:.4">تحت الإجراء</span></td>
          <td><input name="dur[]" value="00"></td>
          <td><input type="date" name="cdate[]"></td>
          <td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-row" style="color:#1d9e75" onclick="addActiveRow()">+ إضافة طلب</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send btn-teal">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer">
  <div class="f-social" style="background:#1da1f2">t</div>
  <div class="f-social" style="background:#e1306c">i</div>
  <div class="f-social" style="background:#1877f2">f</div>
  <div class="f-997">997</div>
</div>
<?php endif; ?>

<!-- ===== تم الحل ===== -->
<?php if($tab==='done'): ?>
<div class="section">
  <div class="sec-title"><div class="bar" style="background:#dc2626"></div><h2 style="color:#dc2626">طلبات تم الحل</h2></div>
  <form method="post">
  <input type="hidden" name="action" value="send_done">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th class="red" style="width:22px">م</th>
          <th class="red">رقم الطلب</th>
          <th class="red" style="width:70px">الموقع</th>
          <th class="red" style="width:80px">تاريخ الطلب</th>
          <th class="red" style="width:75px">حالة الطلب</th>
          <th class="red">مدة الإنجاز</th>
          <th class="red" style="width:80px">تاريخ الإنجاز</th>
          <th class="red">الملاحظات</th>
        </tr>
      </thead>
      <tbody id="dbody">
        <?php foreach($doneData as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td><input name="ticket[]" value="<?= h($r[0]) ?>" style="color:#dc2626;font-weight:700"></td>
          <td><input name="loc[]" value="<?= h($r[1]) ?>"></td>
          <td><input type="date" name="rdate[]" value="<?= h($r[2]) ?>"></td>
          <td><span class="badge-done">تم الحل</span></td>
          <td><input name="dur[]" value="<?= h($r[4]) ?>"></td>
          <td><input type="date" name="cdate[]" value="<?= h($r[5]) ?>"></td>
          <td><textarea name="notes[]" rows="2"><?= h($r[6]) ?></textarea></td>
        </tr>
        <?php endforeach; ?>
        <?php for($i=count($doneData)+1;$i<=12;$i++): ?>
        <tr>
          <td class="nc"><?= $i ?></td>
          <td><input name="ticket[]" placeholder="GEN00..."></td>
          <td><input name="loc[]" placeholder="—"></td>
          <td><input type="date" name="rdate[]"></td>
          <td><span class="badge-done" style="opacity:.4">تم الحل</span></td>
          <td><input name="dur[]" placeholder="—"></td>
          <td><input type="date" name="cdate[]"></td>
          <td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-row" style="color:#dc2626" onclick="addDoneRow()">+ إضافة طلب</button>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send btn-red">إرسال للأدمن</button></div>
  </form>
</div>
<div class="footer">
  <div class="f-social" style="background:#1da1f2">t</div>
  <div class="f-social" style="background:#e1306c">i</div>
  <div class="f-social" style="background:#1877f2">f</div>
  <div class="f-997">997</div>
</div>
<?php endif; ?>

<!-- ===== المرسلة ===== -->
<?php if($tab==='prev'): ?>
<div class="section">
  <div class="sec-title"><div class="bar" style="background:#6b7280"></div><h2 style="color:#6b7280">البيانات المرسلة</h2></div>

  <p style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px">طلبات تحت الإجراء</p>
  <div class="tbl-wrap" style="margin-bottom:18px">
    <table>
      <thead><tr>
        <th class="green" style="width:22px">#</th>
        <th class="green">رقم الطلب</th>
        <th class="green" style="width:70px">الموقع</th>
        <th class="green" style="width:80px">تاريخ الطلب</th>
        <th class="green">الملاحظات</th>
        <th class="green" style="width:90px">وقت الإرسال</th>
      </tr></thead>
      <tbody>
        <?php if(empty($prevActive)): ?>
          <tr><td colspan="6"><div class="empty">لا توجد بيانات مرسلة</div></td></tr>
        <?php else: foreach($prevActive as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td style="color:#1d9e75;font-weight:700"><?= h($r['ticket_num']??'') ?></td>
          <td><?= h($r['location']??'') ?></td>
          <td style="color:#6b7280;font-size:10px"><?= h($r['request_date']??'') ?></td>
          <td style="text-align:right"><?= h($r['notes']??'') ?></td>
          <td style="color:#9ca3af;font-size:10px"><?= h(substr($r['created_at']??'',0,16)) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <p style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px">طلبات تم الحل</p>
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th class="red" style="width:22px">#</th>
        <th class="red">رقم الطلب</th>
        <th class="red" style="width:70px">الموقع</th>
        <th class="red" style="width:80px">تاريخ الطلب</th>
        <th class="red">مدة الإنجاز</th>
        <th class="red">الملاحظات</th>
        <th class="red" style="width:90px">وقت الإرسال</th>
      </tr></thead>
      <tbody>
        <?php if(empty($prevDone)): ?>
          <tr><td colspan="7"><div class="empty">لا توجد بيانات مرسلة</div></td></tr>
        <?php else: foreach($prevDone as $i=>$r): ?>
        <tr>
          <td class="nc"><?= $i+1 ?></td>
          <td style="color:#dc2626;font-weight:700"><?= h($r['ticket_num']??'') ?></td>
          <td><?= h($r['location']??'') ?></td>
          <td style="color:#6b7280;font-size:10px"><?= h($r['request_date']??'') ?></td>
          <td><?= h($r['duration']??'') ?></td>
          <td style="text-align:right"><?= h($r['notes']??'') ?></td>
          <td style="color:#9ca3af;font-size:10px"><?= h(substr($r['created_at']??'',0,16)) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ===== التوصيات ===== -->
<?php if($tab==='rec'): ?>
<div class="section">
  <form method="post">
  <input type="hidden" name="action" value="save_rec">
  <div class="rec-box">
    <div class="rec-head">⭐ التوصيات</div>
    <textarea name="rec_main" placeholder="اكتب التوصيات هنا..."><?= h($lastRec['rec_main']??'') ?></textarea>
  </div>
  <div class="rec-box">
    <div class="rec-head">⚠️ المخاطر والتحديات</div>
    <textarea name="rec_risks" placeholder="المخاطر والتحديات..."><?= h($lastRec['rec_risks']??'') ?></textarea>
  </div>
  <div class="rec-box">
    <div class="rec-head">✅ الإجراءات المقترحة</div>
    <textarea name="rec_actions" placeholder="الإجراءات المقترحة..."><?= h($lastRec['rec_actions']??'') ?></textarea>
  </div>
  <div class="rec-box">
    <div class="rec-head">📝 ملاحظات عامة</div>
    <textarea name="rec_notes" placeholder="ملاحظات عامة..."><?= h($lastRec['rec_notes']??'') ?></textarea>
  </div>
  <div class="send-bar"><button type="submit" class="btn-send btn-teal">💾 حفظ التوصيات</button></div>
  </form>
</div>
<?php endif; ?>

<script>
let an = <?= count($activeData) + 7 ?>, dn = <?= count($doneData) + 5 ?>;

function addActiveRow(){
  an++;
  const tr = document.createElement('tr');
  tr.innerHTML = `<td class="nc">${an}</td>
    <td><input name="ticket[]" placeholder="GEN00..." style="color:#1d9e75;font-weight:700"></td>
    <td><input name="loc[]" placeholder="—"></td>
    <td><input type="date" name="rdate[]"></td>
    <td><span class="badge-active">تحت الإجراء</span></td>
    <td><input name="dur[]" value="00"></td>
    <td><input type="date" name="cdate[]"></td>
    <td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>`;
  document.getElementById('abody')?.appendChild(tr);
}

function addDoneRow(){
  dn++;
  const tr = document.createElement('tr');
  tr.innerHTML = `<td class="nc">${dn}</td>
    <td><input name="ticket[]" placeholder="GEN00..." style="color:#dc2626;font-weight:700"></td>
    <td><input name="loc[]" placeholder="—"></td>
    <td><input type="date" name="rdate[]"></td>
    <td><span class="badge-done">تم الحل</span></td>
    <td><input name="dur[]" placeholder="—"></td>
    <td><input type="date" name="cdate[]"></td>
    <td><textarea name="notes[]" rows="2" placeholder="..."></textarea></td>`;
  document.getElementById('dbody')?.appendChild(tr);
}
</script>

</body>
</html>