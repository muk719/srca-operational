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

    if ($action === 'send_complaints') {
        try {
            $stmt = pdo()->prepare("
                INSERT INTO compliance_complaints
                (day, report_date, period, receive_date, ticket_num, category, sub_category, center, complainant, ticket_status, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
            ");
            $saved = 0;
            $days    = $_POST['day'] ?? [];
            $dates   = $_POST['report_date'] ?? [];
            foreach ($days as $i => $day) {
                $ticketNum = trim($_POST['ticket_num'][$i] ?? '');
                $category  = trim($_POST['category'][$i] ?? '');
                if ($ticketNum === '' && $category === '') continue;
                $stmt->execute([
                    $day,
                    $_POST['report_date'][$i] ?? null,
                    $_POST['period'][$i] ?? '',
                    $_POST['receive_date'][$i] ?? null,
                    $ticketNum,
                    $category,
                    $_POST['sub_category'][$i] ?? '',
                    $_POST['center'][$i] ?? '',
                    $_POST['complainant'][$i] ?? '',
                    $_POST['ticket_status'][$i] ?? '',
                    $_SESSION['op_user_id'] ?? null,
                ]);
                $saved++;
            }
            $msg = $saved > 0 ? "تم إرسال $saved شكوى للأدمن بنجاح" : 'لم يتم إدخال بيانات';
        } catch (Throwable $e) {
            $msg = 'خطأ: ' . $e->getMessage();
        }
    }

    if ($action === 'send_violations') {
        try {
            $stmt = pdo()->prepare("
                INSERT INTO compliance_violations
                (subject, receive_date, violation_status, raise_date, notes, created_by, created_at)
                VALUES (?,?,?,?,?,?,NOW())
            ");
            $saved = 0;
            $subjects = $_POST['subject'] ?? [];
            foreach ($subjects as $i => $subject) {
                $subject = trim($subject);
                if ($subject === '') continue;
                $stmt->execute([
                    $subject,
                    $_POST['receive_date'][$i] ?? null,
                    $_POST['violation_status'][$i] ?? '',
                    $_POST['raise_date'][$i] ?? null,
                    $_POST['notes'][$i] ?? '',
                    $_SESSION['op_user_id'] ?? null,
                ]);
                $saved++;
            }
            $msg = $saved > 0 ? "تم إرسال $saved مخالفة للأدمن بنجاح" : 'لم يتم إدخال بيانات';
        } catch (Throwable $e) {
            $msg = 'خطأ: ' . $e->getMessage();
        }
    }

    if ($action === 'save_rec') {
        try {
            pdo()->prepare("
                INSERT INTO compliance_recommendations
                (rec_main, rec_risks, rec_actions, rec_notes, created_by, created_at)
                VALUES (?,?,?,?,?,NOW())
            ")->execute([
                $_POST['rec_main']    ?? '',
                $_POST['rec_risks']   ?? '',
                $_POST['rec_actions'] ?? '',
                $_POST['rec_notes']   ?? '',
                $_SESSION['op_user_id'] ?? null,
            ]);
            $msg = 'تم حفظ التوصيات بنجاح';
        } catch (Throwable $e) {
            $msg = 'خطأ: ' . $e->getMessage();
        }
    }
}

$prevComplaints  = [];
$prevViolations  = [];
$lastRec         = [];

try {
    $prevComplaints = pdo()->query("SELECT * FROM compliance_complaints ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

try {
    $prevViolations = pdo()->query("SELECT * FROM compliance_violations ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

try {
    $lastRec = pdo()->query("SELECT * FROM compliance_recommendations ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$tab = $_GET['tab'] ?? 'complaints';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة الالتزام</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#f3f4f6;direction:rtl;font-size:13px}

.page-header{background:#fff;border-bottom:1px solid #e5e7eb;padding:12px 20px;display:flex;align-items:center;justify-content:space-between}
.org-ar{font-size:13px;font-weight:700;color:#111827}
.org-en{font-size:9px;color:#9ca3af;letter-spacing:.4px;margin-top:1px}

.tabs-bar{display:flex;border-bottom:1px solid #e5e7eb;background:#f9fafb;padding:0 16px}
.tab-link{padding:10px 16px;font-size:12px;font-weight:600;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-1px;text-decoration:none;white-space:nowrap;display:inline-block}
.tab-link.active{color:#dc2626;border-bottom-color:#dc2626}
.tab-link:hover:not(.active){color:#374151}

.section{padding:16px}

.stat-card{border:2px solid #dc2626;border-radius:14px;padding:16px 20px;background:#fff;max-width:520px;margin:0 auto 20px}
.stat-card-title{text-align:center;font-size:14px;font-weight:700;color:#111827;border:1.5px solid #dc2626;border-radius:8px;padding:7px 20px;margin-bottom:14px}
.stat-icons-row{display:grid;gap:10px;margin-bottom:12px}
.cols3{grid-template-columns:repeat(3,1fr)}
.cols4{grid-template-columns:repeat(4,1fr)}
.stat-item{display:flex;flex-direction:column;align-items:center;gap:5px}
.stat-icon-box{width:64px;height:64px;border:1.5px solid #e5e7eb;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:26px;background:#fff}
.stat-icon-box.red{border-color:#fca5a5;background:#fef2f2}
.stat-icon-box.green{border-color:#86efac;background:#f0fdf4}
.stat-icon-box.gray{border-color:#d1d5db;background:#f9fafb}
.stat-icon-label{font-size:12px;font-weight:700;text-align:center}
.stat-icon-label.red{color:#dc2626}
.stat-icon-label.green{color:#15803d}
.stat-icon-label.gray{color:#374151}
.stat-vals-row{display:grid;gap:10px}
.stat-val-box{border:1.5px solid #e5e7eb;border-radius:8px;padding:6px 4px;text-align:center;font-size:20px;font-weight:700;color:#111827;min-height:42px}
input.stat-val-box{width:100%;outline:none;font-family:inherit}
input.stat-val-box:focus{border-color:#dc2626;background:#fef2f2}

.tbl-wrap{overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
table{width:100%;border-collapse:collapse;font-size:11px}
th{background:#6b7280;color:#fff;padding:8px;font-weight:600;text-align:center;white-space:nowrap;border-left:1px solid rgba(255,255,255,.2)}
th:last-child{border-left:none}
td{padding:7px 8px;border-bottom:1px solid #e5e7eb;color:#111827;text-align:center;vertical-align:middle;border-left:1px solid #e5e7eb}
td:last-child{border-left:none}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:#f9fafb}
td input,td textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:11px;color:#111827;text-align:center;resize:none}
td textarea{min-height:36px;line-height:1.3;text-align:right}
td input:focus,td textarea:focus{background:#fef2f2;border-radius:3px}
.notes-green{color:#166534;font-size:10px;line-height:1.5;text-align:right}

.add-row-btn{display:flex;align-items:center;gap:5px;padding:7px 12px;font-size:11px;color:#dc2626;cursor:pointer;border-top:1px solid #e5e7eb;background:#fff;border:none;width:100%;font-family:inherit;font-weight:600}
.add-row-btn:hover{background:#fef2f2}
.send-bar{display:flex;justify-content:flex-end;padding:12px 0 4px}
.btn-send{padding:7px 18px;background:#166534;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-send:hover{background:#14532d}

.rec-box{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:12px}
.rec-box-head{background:#f9fafb;padding:8px 14px;font-size:12px;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb}
.rec-box-body textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:13px;color:#111827;resize:vertical;min-height:80px;padding:10px 14px;direction:rtl;display:block}

.footer{display:flex;align-items:center;justify-content:flex-end;gap:5px;padding:8px 16px;border-top:1px solid #e5e7eb;margin-top:8px}
.f-s{width:18px;height:18px;border-radius:50%;font-size:8px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center}
.f-997{background:#dc2626;color:#fff;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:700}

.msg{margin:10px 16px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;border:1px solid}
.msg.ok{background:#f0fdf4;color:#166534;border-color:#86efac}
.msg.warn{background:#fffbeb;color:#92400e;border-color:#fcd34d}
.msg.err{background:#fef2f2;color:#991b1b;border-color:#fca5a5}
.empty{padding:24px;text-align:center;color:#9ca3af;font-size:12px}
</style>
</head>
<body>

<div class="page-header">
  <div>
    <div class="org-ar">هيئة الهلال الأحمر السعودي</div>
    <div class="org-en">SAUDI RED CRESCENT AUTHORITY</div>
  </div>
  <svg width="42" height="42" viewBox="0 0 44 44">
    <circle cx="22" cy="22" r="20" fill="#fef2f2" stroke="#fca5a5" stroke-width="1"/>
    <path d="M22 7 a15 15 0 0 1 0 30 a11 11 0 0 0 0-30z" fill="#991b1b"/>
    <polygon points="26,13 27.2,16.8 31,16.8 28,19.2 29.2,23 26,20.8 22.8,23 24,19.2 21,16.8 24.8,16.8" fill="#991b1b"/>
  </svg>
</div>

<?php if ($msg): ?>
<div class="msg <?= str_contains($msg,'نجاح')||str_contains($msg,'تم')?'ok':(str_contains($msg,'خطأ')?'err':'warn') ?>">
  <?= h($msg) ?>
</div>
<?php endif; ?>

<div class="tabs-bar">
  <a class="tab-link <?= $tab==='complaints'?'active':'' ?>" href="?tab=complaints">📋 شكاوى منصة الشكاوى</a>
  <a class="tab-link <?= $tab==='violations'?'active':'' ?>" href="?tab=violations">⚠️ المخالفات</a>
  <a class="tab-link <?= $tab==='prev'?'active':'' ?>" href="?tab=prev">📂 المرسلة</a>
  <a class="tab-link <?= $tab==='rec'?'active':'' ?>" href="?tab=rec">📌 التوصيات</a>
</div>

<?php if ($tab === 'complaints'): ?>
<div class="section">
  <div class="stat-card">
    <div class="stat-card-title">عدد التذاكر في منصة الشكاوى</div>
    <div class="stat-icons-row cols3">
      <div class="stat-item">
        <div class="stat-icon-box red">🔴</div>
        <div class="stat-icon-label red">المغلقة</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon-box green">📅</div>
        <div class="stat-icon-label green">القائمة</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon-box gray">🕐</div>
        <div class="stat-icon-label gray">الاجمالي</div>
      </div>
    </div>
    <form method="post" id="statsForm">
    <div class="stat-vals-row cols3">
      <input class="stat-val-box" name="stat_closed" placeholder="0" type="number" min="0">
      <input class="stat-val-box" name="stat_active" placeholder="0" type="number" min="0">
      <input class="stat-val-box" name="stat_total"  placeholder="0" type="number" min="0">
    </div>
    </form>
  </div>

  <form method="post">
  <input type="hidden" name="action" value="send_complaints">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:28px">العدد</th>
          <th style="width:50px">اليوم</th>
          <th style="width:70px">التاريخ</th>
          <th style="width:50px">الفترة</th>
          <th style="width:80px">تاريخ الاستلام</th>
          <th style="width:80px">رقم التذكرة</th>
          <th>التصنيف العام</th>
          <th>التصنيف الفرعي</th>
          <th style="width:85px">المركز الاسعافي</th>
          <th style="width:70px">مقدم الشكوى</th>
          <th>حالة التذكرة</th>
        </tr>
      </thead>
      <tbody id="complaints-body">
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr>
          <td style="color:#6b7280"><?= $i ?></td>
          <td><input name="day[]" placeholder="—"></td>
          <td><input type="date" name="report_date[]"></td>
          <td><input name="period[]" placeholder="—"></td>
          <td><input type="date" name="receive_date[]"></td>
          <td><input name="ticket_num[]" placeholder="—"></td>
          <td><input name="category[]" placeholder="—"></td>
          <td><input name="sub_category[]" placeholder="—"></td>
          <td><input name="center[]" placeholder="—"></td>
          <td><input name="complainant[]" placeholder="—"></td>
          <td><input name="ticket_status[]" placeholder="—"></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <button type="button" class="add-row-btn" onclick="addComplaintRow()">+ إضافة صف</button>
  </div>
  <div class="send-bar">
    <button type="submit" class="btn-send">إرسال للأدمن</button>
  </div>
  </form>
</div>
<div class="footer">
  <div class="f-s" style="background:#1da1f2">t</div>
  <div class="f-s" style="background:#e1306c">i</div>
  <div class="f-s" style="background:#1877f2">f</div>
  <div class="f-997">997</div>
</div>
<?php endif; ?>

<?php if ($tab === 'violations'): ?>
<div class="section">
  <div class="stat-card">
    <div class="stat-card-title">المخالفات</div>
    <div class="stat-icons-row cols4">
      <div class="stat-item">
        <div class="stat-icon-box green">🔄</div>
        <div class="stat-icon-label green">تحت الاجراء</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon-box red">🔴</div>
        <div class="stat-icon-label red">المغلقة</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon-box green">📅</div>
        <div class="stat-icon-label green">محالة للانضباط</div>
      </div>
      <div class="stat-item">
        <div class="stat-icon-box gray">🕐</div>
        <div class="stat-icon-label gray">الاجمالي</div>
      </div>
    </div>
    <div class="stat-vals-row cols4">
      <input class="stat-val-box" name="v_under"    value="3" type="number">
      <input class="stat-val-box" name="v_closed"   value="0" type="number">
      <input class="stat-val-box" name="v_referred" value="3" type="number">
      <input class="stat-val-box" name="v_total"    value="3" type="number">
    </div>
  </div>

  <form method="post">
  <input type="hidden" name="action" value="send_violations">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:28px">العدد</th>
          <th>موضوع المخالفة</th>
          <th style="width:90px">تاريخ الاستلام</th>
          <th style="width:80px">حالة المخالفة</th>
          <th style="width:90px">تاريخ الرفع</th>
          <th>ملاحظات</th>
        </tr>
      </thead>
      <tbody id="violations-body">
        <tr>
          <td style="color:#6b7280">1</td>
          <td><textarea name="subject[]" rows="2" style="text-align:right">مخالفة عدد 12 موظف اجازات مرضية غير نظامية</textarea></td>
          <td><input type="date" name="receive_date[]" value="2025-02-24"></td>
          <td><input name="violation_status[]" value="تحت الإجراء" style="color:#92400e"></td>
          <td><input type="date" name="raise_date[]" value="2025-02-24"></td>
          <td><textarea name="notes[]" rows="3" class="notes-green">تم احالتها للجنة الانضباط الوظيفي 2025-2-24
برقم مداد (31443) تم الى احالتها الى الجهات الأمنية برقم (36177)
تم احالتها للمحكمة الجزائية</textarea></td>
        </tr>
        <tr>
          <td style="color:#6b7280">2</td>
          <td><textarea name="subject[]" rows="2" style="text-align:right">مخالفة عدم التبليغ عن غياب مسبق</textarea></td>
          <td><input type="date" name="receive_date[]" value="2026-06-07"></td>
          <td><input name="violation_status[]" value="تحت الإجراء" style="color:#92400e"></td>
          <td><input type="date" name="raise_date[]" value="2026-06-07"></td>
          <td><textarea name="notes[]" rows="2" class="notes-green">تم احالتها للجنة الانضباط الوظيفي 2026-6-7
رقم مداد (42190)</textarea></td>
        </tr>
        <tr>
          <td style="color:#6b7280">3</td>
          <td><textarea name="subject[]" rows="2" style="text-align:right">عدم تجديد بطاقة التصنيف المهني</textarea></td>
          <td><input type="date" name="receive_date[]" value="2026-06-07"></td>
          <td><input name="violation_status[]" value="تحت الإجراء" style="color:#92400e"></td>
          <td><input type="date" name="raise_date[]" value="2026-06-07"></td>
          <td><textarea name="notes[]" rows="2" class="notes-green">تم احالتها للجنة الانضباط الوظيفي 2026-6-7
رقم مداد 42533</textarea></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="add-row-btn" onclick="addViolationRow()">+ إضافة صف</button>
  </div>
  <div class="send-bar">
    <button type="submit" class="btn-send">إرسال للأدمن</button>
  </div>
  </form>
</div>
<div class="footer">
  <div class="f-s" style="background:#1da1f2">t</div>
  <div class="f-s" style="background:#e1306c">i</div>
  <div class="f-s" style="background:#1877f2">f</div>
  <div class="f-997">997</div>
</div>
<?php endif; ?>

<?php if ($tab === 'prev'): ?>
<div class="section">
  <h3 style="font-size:13px;font-weight:700;margin-bottom:12px;color:#374151">شكاوى منصة الشكاوى</h3>
  <div class="tbl-wrap" style="margin-bottom:20px">
    <table>
      <thead>
        <tr>
          <th>#</th><th>اليوم</th><th>التاريخ</th><th>رقم التذكرة</th>
          <th>التصنيف العام</th><th>المركز</th><th>الحالة</th><th>وقت الإرسال</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($prevComplaints)): ?>
          <tr><td colspan="8"><div class="empty">لا توجد بيانات</div></td></tr>
        <?php else: ?>
          <?php foreach ($prevComplaints as $i => $r): ?>
          <tr>
            <td style="color:#6b7280"><?= $i+1 ?></td>
            <td><?= h($r['day']??'') ?></td>
            <td style="color:#6b7280;font-size:10px"><?= h($r['report_date']??'') ?></td>
            <td style="color:#1d4ed8;font-weight:700"><?= h($r['ticket_num']??'') ?></td>
            <td><?= h($r['category']??'') ?></td>
            <td><?= h($r['center']??'') ?></td>
            <td><?= h($r['ticket_status']??'') ?></td>
            <td style="color:#9ca3af;font-size:10px"><?= h(substr($r['created_at']??'',0,16)) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h3 style="font-size:13px;font-weight:700;margin-bottom:12px;color:#374151">المخالفات</h3>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>موضوع المخالفة</th>
          <th>تاريخ الاستلام</th>
          <th>الحالة</th>
          <th>الملاحظات</th>
          <th>وقت الإرسال</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($prevViolations)): ?>
          <tr><td colspan="6"><div class="empty">لا توجد بيانات</div></td></tr>
        <?php else: ?>
          <?php foreach ($prevViolations as $i => $r): ?>
          <tr>
            <td style="color:#6b7280"><?= $i+1 ?></td>
            <td style="text-align:right"><?= h($r['subject']??'') ?></td>
            <td style="color:#6b7280;font-size:10px"><?= h($r['receive_date']??'') ?></td>
            <td style="color:#92400e"><?= h($r['violation_status']??'') ?></td>
            <td style="text-align:right;color:#166534;font-size:10px"><?= h($r['notes']??'') ?></td>
            <td style="color:#9ca3af;font-size:10px"><?= h(substr($r['created_at']??'',0,16)) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($tab === 'rec'): ?>
<div class="section">
  <form method="post">
  <input type="hidden" name="action" value="save_rec">
  <div class="rec-box">
    <div class="rec-box-head">⭐ التوصيات</div>
    <div class="rec-box-body"><textarea name="rec_main" placeholder="اكتب التوصيات هنا..."><?= h($lastRec['rec_main']??'') ?></textarea></div>
  </div>
  <div class="rec-box">
    <div class="rec-box-head">⚠️ المخاطر والتحديات</div>
    <div class="rec-box-body"><textarea name="rec_risks" placeholder="المخاطر والتحديات..."><?= h($lastRec['rec_risks']??'') ?></textarea></div>
  </div>
  <div class="rec-box">
    <div class="rec-box-head">✅ الإجراءات المقترحة</div>
    <div class="rec-box-body"><textarea name="rec_actions" placeholder="الإجراءات المقترحة..."><?= h($lastRec['rec_actions']??'') ?></textarea></div>
  </div>
  <div class="rec-box">
    <div class="rec-box-head">📝 ملاحظات عامة</div>
    <div class="rec-box-body"><textarea name="rec_notes" placeholder="ملاحظات عامة..."><?= h($lastRec['rec_notes']??'') ?></textarea></div>
  </div>
  <div class="send-bar">
    <button type="submit" class="btn-send">💾 حفظ التوصيات</button>
  </div>
  </form>
</div>
<?php endif; ?>

<script>
let cRows = 3;
let vRows = 3;

function addComplaintRow(){
  cRows++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td style="color:#6b7280">${cRows}</td>
    <td><input name="day[]" placeholder="—"></td>
    <td><input type="date" name="report_date[]"></td>
    <td><input name="period[]" placeholder="—"></td>
    <td><input type="date" name="receive_date[]"></td>
    <td><input name="ticket_num[]" placeholder="—"></td>
    <td><input name="category[]" placeholder="—"></td>
    <td><input name="sub_category[]" placeholder="—"></td>
    <td><input name="center[]" placeholder="—"></td>
    <td><input name="complainant[]" placeholder="—"></td>
    <td><input name="ticket_status[]" placeholder="—"></td>
  `;
  document.getElementById('complaints-body').appendChild(tr);
}

function addViolationRow(){
  vRows++;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td style="color:#6b7280">${vRows}</td>
    <td><textarea name="subject[]" rows="2" style="text-align:right" placeholder="..."></textarea></td>
    <td><input type="date" name="receive_date[]"></td>
    <td><input name="violation_status[]" placeholder="—" style="color:#92400e"></td>
    <td><input type="date" name="raise_date[]"></td>
    <td><textarea name="notes[]" rows="2" class="notes-green" placeholder="..."></textarea></td>
  `;
  document.getElementById('violations-body')?.appendChild(tr);
}
</script>

</body>
</html>