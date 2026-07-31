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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_legal') {
    $subjects    = $_POST['subject'] ?? [];
    $caseNumbers = $_POST['case_number'] ?? [];
    $dates       = $_POST['last_update'] ?? [];
    $updates     = $_POST['update_text'] ?? [];
    $saved = 0;


   try {

    $stmtReport = pdo()->prepare("
        SELECT id
        FROM legal_department_reports
        WHERE department = 'الإدارة القانونية'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtReport->execute();
    $reportId = $stmtReport->fetchColumn();

    if (!$reportId) {
        $stmtNewReport = pdo()->prepare("
            INSERT INTO legal_department_reports
            (report_year, report_month, department, total_transactions, in_progress_transactions, closed_or_returned_transactions, notes, created_by, created_at)
            VALUES (?, ?, 'الإدارة القانونية', 0, 0, 0, '', ?, NOW())
        ");
        $stmtNewReport->execute([
            date('Y'),
            date('n'),
            $_SESSION['op_user_id'] ?? null
        ]);

        $reportId = pdo()->lastInsertId();
    }

   $stmt = pdo()->prepare("
INSERT INTO legal_department_transactions
(
    report_id,
    transaction_number,
    violation_subject,
    last_update_date,
    update_text,
    created_at
)
VALUES
(
    ?, ?, ?, ?, ?, NOW()
)
");

        foreach ($subjects as $i => $subject) {
            $subject = trim((string)$subject);
            $caseNo  = trim((string)($caseNumbers[$i] ?? ''));
            $date    = trim((string)($dates[$i] ?? ''));
            $update  = trim((string)($updates[$i] ?? ''));

            if ($subject === '' && $caseNo === '' && $date === '' && $update === '') continue;

    $stmt->execute([
    $reportId,
    $caseNo,
    $subject,
    $date !== '' ? $date : null,
    $update
]);
            $saved++;
        }

        $msg = $saved > 0 ? 'تم إرسال بيانات الإدارة القانونية للأدمن بنجاح' : 'لم يتم إدخال أي بيانات للإرسال';

    } catch (Throwable $e) {
        $msg = 'حدث خطأ أثناء الإرسال: ' . $e->getMessage();
    }
}
$previous = [];
try {
    $previous = pdo()->query("
        SELECT *
        FROM legal_department_transactions
        ORDER BY id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $previous = [];
}

$recommendations = [];
try {
    $stmt = pdo()->prepare("
        SELECT *
        FROM operational_notes
        WHERE department = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    $stmt->execute(['الإدارة القانونية']);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recommendations = [];
}

$lastRec = $recommendations[0] ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>الإدارة القانونية</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#f3f4f6;direction:rtl}

.page-header{background:#fff;border-bottom:1px solid #e5e7eb;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.org-brand{display:flex;align-items:center;gap:10px}
.org-name-ar{font-size:14px;font-weight:700;color:#111827}
.org-name-en{font-size:10px;color:#6b7280;margin-top:1px;letter-spacing:.4px}
.dept-badge{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;padding:5px 14px;font-size:13px;font-weight:700}
.dept-person{font-size:12px;color:#6b7280;margin-top:2px;text-align:center}
.deco-bar{display:flex;height:5px}
.deco-bar span{flex:1}

.tabs-bar{display:flex;gap:0;border-bottom:1px solid #e5e7eb;background:#f9fafb;padding:0 16px}
.tab-btn{padding:11px 18px;font-size:13px;font-weight:600;color:#6b7280;cursor:pointer;border:none;background:transparent;border-bottom:2px solid transparent;margin-bottom:-1px;font-family:inherit;display:flex;align-items:center;gap:6px;white-space:nowrap;text-decoration:none}
.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb}
.tab-btn:hover:not(.active){color:#374151}

.tab-panel{display:none}
.tab-panel.active{display:block}

.body{display:grid;grid-template-columns:minmax(0,1fr) 176px;gap:14px;padding:16px;align-items:start}
@media(max-width:700px){.body{grid-template-columns:1fr}}

.panel{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
.panel-head{background:#2563eb;color:#fff;padding:9px 14px;font-size:12px;font-weight:700;display:grid;grid-template-columns:28px minmax(0,1fr) 110px 110px minmax(0,1fr);gap:0;text-align:center}
.panel-head span:nth-child(2){text-align:right}

.tbl-row{display:grid;grid-template-columns:28px minmax(0,1fr) 110px 110px minmax(0,1fr);border-bottom:1px solid #e5e7eb;align-items:stretch;min-height:44px}
.tbl-row:last-child{border-bottom:none}
.tbl-row:hover{background:#f9fafb}
.cell{padding:8px 10px;font-size:12px;color:#111827;display:flex;align-items:center;justify-content:center;border-right:1px solid #e5e7eb}
.cell:first-child{color:#6b7280;font-size:11px}
.cell:last-child{border-right:none}
.cell.subject{justify-content:flex-end;text-align:right}
.cell textarea,.cell input{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:12px;color:#111827;resize:none;text-align:center;line-height:1.4}
.cell textarea{text-align:right}
.cell input[type=date]{font-size:11px;color:#6b7280}
.cell textarea:focus,.cell input:focus{background:#eff6ff;border-radius:4px;color:#1e40af}

.badge-red{display:inline-block;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;white-space:nowrap}

.row-actions{display:flex;align-items:center;gap:8px;padding:8px 14px;border-top:1px solid #e5e7eb;background:#f9fafb}
.btn-add{display:flex;align-items:center;gap:5px;font-size:12px;color:#2563eb;cursor:pointer;padding:6px 12px;border-radius:8px;border:1px solid #bfdbfe;background:transparent;font-family:inherit;font-weight:600}
.btn-add:hover{background:#eff6ff}
.btn-send{display:flex;align-items:center;gap:5px;font-size:12px;color:#fff;cursor:pointer;padding:6px 14px;border-radius:8px;border:none;background:#166534;font-family:inherit;font-weight:700;margin-right:auto}
.btn-send:hover{background:#14532d}

.sidebar{display:flex;flex-direction:column;gap:10px}
.stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px;text-align:center}
.stat-lbl{font-size:10px;color:#6b7280;line-height:1.5;margin-bottom:6px}
.stat-num{font-size:30px;font-weight:700;color:#111827}
.stat.s-blue{border-color:#93c5fd;background:#eff6ff}
.stat.s-blue .stat-lbl{color:#1d4ed8}
.stat.s-blue .stat-num{color:#1e3a8a}
.stat.s-amber{border-color:#fcd34d;background:#fffbeb}
.stat.s-amber .stat-lbl{color:#92400e}
.stat.s-amber .stat-num{color:#78350f}

.prev-table{width:100%;border-collapse:collapse}
.prev-table th{background:#2563eb;color:#fff;padding:8px 10px;font-size:11px;font-weight:700;text-align:center}
.prev-table th:nth-child(2){text-align:right}
.prev-table td{padding:8px 10px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#111827;text-align:center;vertical-align:top}
.prev-table td:nth-child(2){text-align:right}
.prev-table tbody tr:last-child td{border-bottom:none}
.prev-table tbody tr:hover{background:#f9fafb}
.empty-state{padding:32px;text-align:center;color:#9ca3af;font-size:13px}

.rec-area{padding:16px}
.rec-box{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:12px;background:#fff}
.rec-box-head{background:#f9fafb;padding:9px 14px;font-size:12px;font-weight:700;color:#374151;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:6px}
.rec-box-body{padding:10px 14px}
.rec-box-body textarea{width:100%;border:none;background:transparent;outline:none;font-family:inherit;font-size:13px;color:#111827;resize:vertical;min-height:80px;line-height:1.6}
.rec-box-body textarea:focus{background:#f9fafb;border-radius:4px}
.rec-actions{display:flex;justify-content:flex-end;padding:0 0 4px}
.btn-save-rec{display:flex;align-items:center;gap:5px;font-size:12px;color:#fff;cursor:pointer;padding:7px 16px;border-radius:8px;border:none;background:#2563eb;font-family:inherit;font-weight:700}
.btn-save-rec:hover{background:#1d4ed8}

.footer-bar{display:flex;align-items:center;justify-content:flex-end;gap:6px;padding:10px 16px;border-top:1px solid #e5e7eb}
.f-social{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff}
.f-997{background:#dc2626;color:#fff;border-radius:5px;padding:2px 8px;font-size:12px;font-weight:700}

.msg{margin:12px 16px 0;border-radius:8px;padding:10px 14px;font-size:13px;font-weight:700;border:1px solid}
.msg.ok{background:#f0fdf4;color:#166534;border-color:#86efac}
.msg.warn{background:#fffbeb;color:#92400e;border-color:#fcd34d}
.msg.err{background:#fef2f2;color:#991b1b;border-color:#fca5a5}
</style>
</head>
<body>

<div class="page-header">
  <div class="org-brand">
    <svg width="44" height="44" viewBox="0 0 44 44">
      <circle cx="22" cy="22" r="20" fill="#fef2f2" stroke="#fca5a5" stroke-width="1"/>
      <path d="M22 7 a15 15 0 0 1 0 30 a11 11 0 0 0 0-30z" fill="#991b1b"/>
      <polygon points="26,13 27.2,16.8 31,16.8 28,19.2 29.2,23 26,20.8 22.8,23 24,19.2 21,16.8 24.8,16.8" fill="#991b1b"/>
    </svg>
    <div>
      <div class="org-name-ar">هيئة الهلال الأحمر السعودي</div>
      <div class="org-name-en">SAUDI RED CRESCENT AUTHORITY</div>
    </div>
  </div>
  <div style="text-align:center">
    <div class="dept-badge">الإدارة القانونية</div>
    <div class="dept-person">ماجد السديس</div>
  </div>
</div>

<div class="deco-bar">
  <span style="background:#991b1b;flex:3"></span>
  <span style="background:#b45309;flex:1.5"></span>
  <span style="background:#991b1b;opacity:.5;flex:1"></span>
  <span style="background:#b45309;opacity:.4;flex:.6"></span>
</div>

<?php if($msg): ?>
  <div class="msg <?= str_contains($msg,'نجاح')||str_contains($msg,'تم')?'ok':(str_contains($msg,'خطأ')?'err':'warn') ?>">
    <?= h($msg) ?>
  </div>
<?php endif; ?>

<div class="tabs-bar">
  <a class="tab-btn <?= (!isset($_GET['tab'])||$_GET['tab']==='today')?'active':'' ?>" href="?tab=today">📝 إدخال اليوم</a>
  <a class="tab-btn <?= (($_GET['tab']??'')==='prev')?'active':'' ?>" href="?tab=prev">📋 البيانات المرسلة</a>
  <a class="tab-btn <?= (($_GET['tab']??'')==='rec')?'active':'' ?>" href="?tab=rec">📌 التوصيات</a>
</div>

<!-- تبويب اليوم -->
<?php if(!isset($_GET['tab'])||$_GET['tab']==='today'): ?>
<div class="tab-panel active" id="tab-today">
  <form method="post">
  <input type="hidden" name="action" value="send_legal">
  <div class="body">
    <div>
      <div class="panel">
        <div class="panel-head">
          <span>#</span>
          <span style="text-align:right">موضوع المتابعة</span>
          <span>رقم القضية في نظام عاد</span>
          <span>تاريخ آخر تحديث</span>
          <span>التحديث</span>
        </div>
        <div id="rows">
          <div class="tbl-row">
            <div class="cell">1</div>
            <div class="cell subject"><textarea name="subject[]" rows="2" placeholder="موضوع المتابعة...">معالجة ضد 12 موظف أحالة مرضية غير نظامية</textarea></div>
            <div class="cell"><input name="case_number[]" value="31443-47"></div>
            <div class="cell"><input type="date" name="last_update[]" value="2025-06-30"></div>
            <div class="cell subject"><textarea name="update_text[]" rows="2">إجراءات منظورة لدى المحكمة الجزائية</textarea></div>
          </div>
          <div class="tbl-row">
            <div class="cell">2</div>
            <div class="cell subject"><textarea name="subject[]" rows="2" placeholder="..."></textarea></div>
            <div class="cell"><input name="case_number[]" placeholder="—"></div>
            <div class="cell"><input type="date" name="last_update[]"></div>
            <div class="cell subject"><textarea name="update_text[]" rows="2" placeholder="—"></textarea></div>
          </div>
          <div class="tbl-row">
            <div class="cell">3</div>
            <div class="cell subject"><textarea name="subject[]" rows="2" placeholder="..."></textarea></div>
            <div class="cell"><input name="case_number[]" placeholder="—"></div>
            <div class="cell"><input type="date" name="last_update[]"></div>
            <div class="cell subject"><textarea name="update_text[]" rows="2" placeholder="—"></textarea></div>
          </div>
        </div>
        <div class="row-actions">
          <button type="button" class="btn-add" onclick="addRow()">+ إضافة صف</button>
          <button type="submit" class="btn-send">إرسال للأدمن ←</button>
        </div>
      </div>
      <div class="footer-bar">
        <div class="f-social" style="background:#1da1f2">t</div>
        <div class="f-social" style="background:#e1306c">i</div>
        <div class="f-social" style="background:#1877f2">f</div>
        <div class="f-997">997</div>
      </div>
    </div>
    <div class="sidebar">
      <div class="stat s-blue">
        <div class="stat-lbl">إجمالي المعاملات</div>
        <div class="stat-num" id="totalCount">3</div>
      </div>
      <div class="stat">
        <div class="stat-lbl">المعاملات تحت الإجراء</div>
        <div class="stat-num">1</div>
      </div>
      <div class="stat s-amber">
        <div class="stat-lbl">المعاملات المنفذة أو المتبقية</div>
        <div class="stat-num">2</div>
      </div>
    </div>
  </div>
  </form>
</div>
<?php endif; ?>

<!-- تبويب البيانات المرسلة -->
<?php if(($_GET['tab']??'')==='prev'): ?>
<div class="tab-panel active" id="tab-prev">
  <div style="padding:16px">
    <div class="panel">
      <div style="overflow-x:auto">
        <table class="prev-table">
          <thead>
            <tr>
              <th style="width:28px">#</th>
              <th>موضوع المتابعة</th>
              <th>رقم القضية</th>
              <th>تاريخ التحديث</th>
              <th>التحديث</th>
              <th>وقت الإرسال</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($previous)): ?>
              <tr><td colspan="6"><div class="empty-state">لا توجد بيانات مرسلة بعد</div></td></tr>
            <?php else: ?>
              <?php foreach($previous as $i => $r): ?>
              <tr>
                <td style="color:#6b7280"><?= $i+1 ?></td>
                <td>
<?= h($r['violation_subject']??'') ?>
              </td>
                <td style="color:#1d4ed8;font-weight:700">
<?= h($r['transaction_number']??'') ?>                </td>
                <td style="color:#6b7280">
<?= h($r['last_update_date']??'') ?>                </td>
                <td><?= h($r['update_text']??'') ?></td>
                <td style="color:#9ca3af;font-size:11px"><?= h(substr($r['created_at']??'',0,16)) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- تبويب التوصيات -->
<?php if(($_GET['tab']??'')==='rec'): ?>
<div class="tab-panel active" id="tab-rec">
  <form method="post">
  <input type="hidden" name="action" value="save_rec">
  <div class="rec-area">
    <div class="rec-box">
      <div class="rec-box-head">⭐ التوصيات القانونية</div>
      <div class="rec-box-body">
        <textarea name="rec_legal" placeholder="اكتب التوصيات القانونية هنا..."><?= h($lastRec['rec_legal']??'') ?></textarea>
      </div>
    </div>
    <div class="rec-box">
      <div class="rec-box-head">⚠️ المخاطر والتحديات</div>
      <div class="rec-box-body">
        <textarea name="rec_risks" placeholder="اذكر المخاطر والتحديات القانونية..."><?= h($lastRec['rec_risks']??'') ?></textarea>
      </div>
    </div>
    <div class="rec-box">
      <div class="rec-box-head">✅ الإجراءات المقترحة</div>
      <div class="rec-box-body">
        <textarea name="rec_actions" placeholder="الإجراءات المقترحة لمعالجة القضايا..."><?= h($lastRec['rec_actions']??'') ?></textarea>
      </div>
    </div>
    <div class="rec-box">
      <div class="rec-box-head">📝 ملاحظات عامة</div>
      <div class="rec-box-body">
        <textarea name="rec_notes" placeholder="أي ملاحظات إضافية..."><?= h($lastRec['rec_notes']??'') ?></textarea>
      </div>
    </div>
    <div class="rec-actions">
      <button type="submit" class="btn-save-rec">💾 حفظ التوصيات</button>
    </div>
  </div>
  </form>
</div>
<?php endif; ?>

<script>
let n = 3;
function addRow(){
  n++;
  const div = document.createElement('div');
  div.className = 'tbl-row';
  div.innerHTML = `
    <div class="cell">${n}</div>
    <div class="cell subject"><textarea name="subject[]" rows="2" placeholder="..."></textarea></div>
    <div class="cell"><input name="case_number[]" placeholder="—"></div>
    <div class="cell"><input type="date" name="last_update[]"></div>
    <div class="cell subject"><textarea name="update_text[]" rows="2" placeholder="—"></textarea></div>
  `;
  document.getElementById('rows').appendChild(div);
  document.getElementById('totalCount').textContent = n;
}
</script>

</body>
</html>