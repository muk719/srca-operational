<?php
$REQUIRED_DEPT = 'صوت الموظف';
$DEPT_TITLE = 'صوت الموظف';

require_once __DIR__ . '/_base.php';

$msg = $msg ?? '';

try {
    pdo()->exec("
        CREATE TABLE IF NOT EXISTS employee_voice_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            department VARCHAR(120) NOT NULL DEFAULT 'صوت الموظف',
            month_year VARCHAR(100) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    pdo()->exec("
        CREATE TABLE IF NOT EXISTS employee_voice_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id INT UNSIGNED NOT NULL,
            branch VARCHAR(150) NULL,
            item_type VARCHAR(100) NULL,
            statement_text TEXT NULL,
            status VARCHAR(100) NULL,
            protection_notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(report_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    try {
        pdo()->exec("ALTER TABLE operational_notes ADD COLUMN recommendation_status VARCHAR(50) NULL DEFAULT 'لا'");
    } catch(Throwable $e) {}

} catch(Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ev'])) {

    try {

        $monthYear = trim($_POST['month_year'] ?? '');
        $items = $_POST['items'] ?? [];

        pdo()->beginTransaction();

        $stmt = pdo()->prepare("
            INSERT INTO employee_voice_reports
            (user_id, department, month_year)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['op_user_id'],
            $department,
            $monthYear
        ]);

        $reportId = pdo()->lastInsertId();

        $savedItems = 0;

        foreach ($items as $r) {

            $type   = trim($r['type'] ?? '');
            $text   = trim($r['text'] ?? '');
            $status = trim($r['status'] ?? '');
            $pnotes = trim($r['protection_notes'] ?? '');

            if (
                $type !== '' ||
                $text !== '' ||
                $status !== '' ||
                $pnotes !== ''
            ) {

                pdo()->prepare("
                    INSERT INTO employee_voice_items
                    (
                        report_id,
                        item_type,
                        statement_text,
                        status,
                        protection_notes,
                        created_at
                    )
                    VALUES
                    (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $reportId,
                    $type,
                    $text,
                    $status,
                    $pnotes
                ]);

                $savedItems++;
            }
        }

        if ($savedItems === 0) {
            throw new Exception('لم يتم إدخال أي بيانات داخل الجدول');
        }

        pdo()->commit();

        $msg = "✅ تم حفظ الملف وإرسال {$savedItems} صف للأدمن";

    } catch(Throwable $e) {

        if (pdo()->inTransaction()) {
            pdo()->rollBack();
        }

        $msg = "⚠️ خطأ أثناء الحفظ: " . $e->getMessage();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recommendation_status'])) {
    try {
        $noteId = (int)($_POST['note_id'] ?? 0);
        $status = trim($_POST['recommendation_status'] ?? 'لا');

        pdo()->prepare("
            UPDATE operational_notes
            SET recommendation_status = ?
            WHERE id = ? AND department = ?
        ")->execute([$status, $noteId, $department]);

        $msg = "✅ تم تحديث حالة التوصية";
    } catch(Throwable $e) {
        $msg = "⚠️ خطأ أثناء تحديث التوصية: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_note_reply'])) {
    try {
        $reply = trim($_POST['note_reply'] ?? '');

        if ($reply !== '') {
            pdo()->prepare("
                UPDATE operational_notes
                SET department_reply = ?, replied_at = NOW(), is_read = 1
                WHERE department = ? AND (is_read = 0 OR is_read IS NULL)
            ")->execute([$reply, $department]);

            $msg = "✅ تم إرسال الرد";
        }
    } catch(Throwable $e) {
        $msg = "⚠️ خطأ أثناء إرسال الرد: " . $e->getMessage();
    }
}

$recommendations = [];
try {
    $stmt = pdo()->prepare("
        SELECT *
        FROM operational_notes
        WHERE department = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$department]);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

$previousReports = [];
try {
    $stmt = pdo()->prepare("
        SELECT *
        FROM employee_voice_reports
        WHERE department = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$department]);
    $previousReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

$months_ar = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
$currentMonthAr = $months_ar[date('n')-1];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>صوت الموظف — الملف التشغيلي</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Segoe UI',Tahoma,Arial,sans-serif;
  background:#eef2f6;
  color:#111827;
  direction:rtl;
  min-height:100vh;
}
.top-hero{
  width:100%;
  background:#ffffff;
  border-bottom:1px solid #d9dee7;
  color:#111827;
  padding:18px 34px;
  box-shadow:0 4px 18px rgba(15,23,42,.06);
}
.hero-inner{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:20px;
  flex-wrap:wrap;
}
.gov-title{
  font-size:24px;
  font-weight:900;
  color:#0f172a;
}
.gov-sub{
  color:#64748b;
  font-size:13px;
  font-weight:700;
  margin-top:4px;
}
.hero-actions{
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
}
.user-chip{
  background:#f8fafc;
  border:1px solid #d9dee7;
  color:#334155;
  border-radius:999px;
  padding:9px 15px;
  font-size:12px;
  font-weight:800;
}
.logout{
  background:#fee2e2;
  border:1px solid #fecaca;
  color:#dc2626;
  border-radius:999px;
  padding:9px 16px;
  font-size:12px;
  font-weight:900;
  text-decoration:none;
}
.wrap{
  width:100%;
  padding:22px 34px;
}
.msg{
  margin-bottom:16px;
  padding:13px 16px;
  border-radius:14px;
  font-size:14px;
  font-weight:900;
}
.msg.ok{
  background:#f0fdf4;
  color:#166534;
  border:1px solid #86efac;
}
.msg.warn{
  background:#fffbeb;
  color:#92400e;
  border:1px solid #fde68a;
}
.tabs{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:10px;
  margin-bottom:18px;
}
.tab-btn{
  height:50px;
  border-radius:14px;
  border:1px solid #d9dee7;
  background:#ffffff;
  color:#475569;
  font-family:inherit;
  font-size:14px;
  font-weight:900;
  cursor:pointer;
  box-shadow:0 4px 12px rgba(15,23,42,.04);
}
.tab-btn.active{
  background:#0f172a;
  color:#fff;
  border-color:#0f172a;
}
.panel{display:none}
.panel.active{display:block}
.card{
  background:#fff;
  border:1px solid #d9dee7;
  border-radius:18px;
  overflow:hidden;
  box-shadow:0 6px 20px rgba(15,23,42,.05);
  margin-bottom:18px;
}
.card-head{
  padding:15px 18px;
  background:#f8fafc;
  border-bottom:1px solid #d9dee7;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.card-title{
  font-size:15px;
  font-weight:900;
  color:#0f172a;
  display:flex;
  align-items:center;
  gap:8px;
}
.dot{
  width:9px;
  height:9px;
  border-radius:50%;
  background:#0f766e;
}
.card-body{padding:18px}
.month-box{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:15px;
  flex-wrap:wrap;
}
.month-box label{
  font-size:13px;
  color:#475569;
  font-weight:900;
}
.month-box input{
  height:44px;
  border:1px solid #d9dee7;
  border-radius:12px;
  padding:0 14px;
  font-family:inherit;
  min-width:230px;
  outline:none;
}
.tbl-wrap{
  width:100%;
  overflow-x:auto;
  border:1px solid #d9dee7;
  border-radius:15px;
}
table{
  width:100%;
  border-collapse:collapse;
  background:#fff;
}
th{
  background:#0f172a;
  color:#fff;
  padding:12px 10px;
  font-size:12px;
  white-space:nowrap;
}
td{
  padding:10px;
  border-bottom:1px solid #e5e7eb;
  font-size:12px;
  text-align:center;
  vertical-align:middle;
}
td input,td select,td textarea{
  width:100%;
  border:1px solid transparent;
  background:#f8fafc;
  border-radius:9px;
  padding:9px;
  font-family:inherit;
  outline:none;
}
td textarea{
  min-height:42px;
  resize:vertical;
}
td input:focus,td select:focus,td textarea:focus{
  background:#fff;
  border-color:#0f766e;
}
.btn-add{
  background:#0f766e;
  color:#fff;
  border:none;
  border-radius:10px;
  height:34px;
  padding:0 14px;
  font-family:inherit;
  font-size:12px;
  font-weight:900;
  cursor:pointer;
}
.btn-del{
  border:none;
  background:#fee2e2;
  color:#dc2626;
  width:30px;
  height:30px;
  border-radius:8px;
  cursor:pointer;
  font-weight:900;
}
.submit-bar{
  display:flex;
  justify-content:flex-end;
  gap:8px;
  background:#fff;
  border:1px solid #d9dee7;
  border-radius:18px;
  padding:15px 18px;
  position:sticky;
  bottom:12px;
  z-index:20;
  box-shadow:0 8px 24px rgba(15,23,42,.08);
}
.btn-save{
  height:44px;
  border-radius:999px;
  padding:0 26px;
  border:none;
  background:#0f766e;
  color:#fff;
  font-family:inherit;
  font-size:13px;
  font-weight:900;
  cursor:pointer;
}
.btn-outline{
  height:44px;
  border-radius:999px;
  padding:0 22px;
  background:#fff;
  border:1px solid #d9dee7;
  color:#111827;
  font-family:inherit;
  font-size:13px;
  font-weight:900;
  cursor:pointer;
}
.rec-card{
  border:1px solid #d9dee7;
  border-radius:14px;
  padding:14px;
  margin-bottom:12px;
  background:#fff;
}
.rec-text{
  font-size:13px;
  font-weight:800;
  line-height:1.8;
}
.rec-date{
  font-size:11px;
  color:#64748b;
  margin-top:5px;
}
.rec-form{
  display:flex;
  gap:8px;
  margin-top:12px;
}
.rec-form select{
  height:38px;
  border:1px solid #d9dee7;
  border-radius:10px;
  padding:0 10px;
  font-family:inherit;
}
.rec-form button{
  height:38px;
  border:none;
  border-radius:10px;
  background:#0f766e;
  color:#fff;
  padding:0 14px;
  font-family:inherit;
  font-weight:900;
  cursor:pointer;
}
.note-box{
  background:#fffbeb;
  border:1px solid #fde68a;
  border-radius:14px;
  padding:14px;
  margin-bottom:12px;
}
.note-box b{color:#92400e}
.note-box p{
  margin-top:8px;
  line-height:1.8;
  color:#78350f;
}
.reply-form textarea{
  width:100%;
  min-height:110px;
  border:1.5px solid #f59e0b;
  border-radius:14px;
  padding:12px;
  font-family:inherit;
  resize:vertical;
}
.empty{
  text-align:center;
  padding:44px;
  color:#64748b;
  font-weight:800;
}
@media(max-width:900px){
  .tabs{grid-template-columns:1fr 1fr}
  .wrap{padding:16px}
}
</style>
</head>

<body>

<div class="top-hero">
  <div class="hero-inner">
    <div>
      <div class="gov-title">صوت الموظف</div>
      <div class="gov-sub">الملف التشغيلي اليومي — عرض وإرسال بيانات القسم</div>
    </div>

    <div class="hero-actions">
      <span class="user-chip">المستخدم: <?= h($userName ?? '') ?></span>
      <a class="logout" href="operational_logout.php">خروج</a>
    </div>
  </div>
</div>

<div class="wrap">

  <?php if($msg): ?>
    <div class="msg <?= str_starts_with($msg,'✅') ? 'ok' : 'warn' ?>">
      <?= h($msg) ?>
    </div>
  <?php endif; ?>

  <div class="tabs">
    <button class="tab-btn active" id="tab-send" onclick="switchTab('send')">إرسال الملف</button>
    <button class="tab-btn" id="tab-prev" onclick="switchTab('prev')">المرسلة سابقاً</button>
    <button class="tab-btn" id="tab-recs" onclick="switchTab('recs')">التوصيات</button>
    <button class="tab-btn" id="tab-notes" onclick="switchTab('notes')">الملاحظات</button>
  </div>

  <div class="panel active" id="panel-send">
    <form method="post" id="mainForm">
      <input type="hidden" name="save_ev" value="1">

      <div class="card">
        <div class="card-head">
          <div class="card-title"><span class="dot"></span>بيانات ملف صوت الموظف</div>
          <button type="button" class="btn-add" onclick="addRow()">+ إضافة صف</button>
        </div>

        <div class="card-body">
          <div class="month-box">
            <label>الشهر والسنة</label>
            <input name="month_year" value="<?= h($currentMonthAr) ?> <?= date('Y') ?>م">
          </div>

          <div class="tbl-wrap">
            <table>
              <thead>
              <tr>
  <th style="width:45px">م</th>
  <th>نوع البيان</th>
  <th>البيان</th>
  <th>الحالة</th>
  <th>التحسين والملاحظات</th>
  <th style="width:45px"></th>
</tr>
              </thead>
<tbody id="evBody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="submit-bar">
        <button class="btn-save" type="submit">حفظ وإرسال للأدمن</button>
        <button class="btn-outline" type="reset">مسح</button>
      </div>
    </form>
  </div>

  <div class="panel" id="panel-prev">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="dot"></span>الملفات المرسلة سابقاً</div>
      </div>
      <div class="card-body">
        <?php if(empty($previousReports)): ?>
          <div class="empty">لا توجد ملفات مرسلة سابقاً</div>
        <?php else: ?>
          <div class="tbl-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>الشهر والسنة</th>
                  <th>تاريخ الإرسال</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($previousReports as $i=>$r): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= h($r['month_year']) ?></td>
                    <td><?= h(substr($r['created_at'],0,16)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="panel" id="panel-recs">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="dot"></span>التوصيات الواردة من الأدمن</div>
      </div>
      <div class="card-body">
        <?php if(empty($recommendations)): ?>
          <div class="empty">لا توجد توصيات حالياً</div>
        <?php else: ?>
          <?php foreach($recommendations as $rec): ?>
            <div class="rec-card">
              <div class="rec-text">
                <?= h($rec['note_text'] ?? $rec['message'] ?? '') ?>
              </div>
              <div class="rec-date">
                <?= h(substr($rec['created_at'] ?? '',0,16)) ?>
              </div>

              <form method="post" class="rec-form">
                <input type="hidden" name="save_recommendation_status" value="1">
                <input type="hidden" name="note_id" value="<?= (int)$rec['id'] ?>">

                <select name="recommendation_status">
                  <option value="لا" <?= (($rec['recommendation_status'] ?? 'لا') === 'لا') ? 'selected' : '' ?>>غير منجزة</option>
                  <option value="نعم" <?= (($rec['recommendation_status'] ?? '') === 'نعم') ? 'selected' : '' ?>>منجزة</option>
                </select>

                <button type="submit">حفظ الحالة</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="panel" id="panel-notes">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><span class="dot"></span>ملاحظات الأدمن</div>
      </div>
      <div class="card-body">
        <?php if(empty($unreadNotes)): ?>
          <div class="empty">لا توجد ملاحظات جديدة</div>
        <?php else: ?>
          <?php foreach($unreadNotes as $n): ?>
            <div class="note-box">
              <b>ملاحظة</b>
              <p><?= h($n['note_text'] ?? $n['message'] ?? '') ?></p>
              <small><?= h(substr($n['created_at'] ?? '',0,16)) ?></small>
            </div>
          <?php endforeach; ?>

          <form method="post" class="reply-form">
            <input type="hidden" name="send_note_reply" value="1">
            <textarea name="note_reply" placeholder="اكتب ردك على الملاحظات..."></textarea>
            <button class="btn-save" type="submit" style="margin-top:10px">إرسال الرد</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
function switchTab(name){
  document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('panel-'+name).classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
}

let rowIndex = 0;

function addRow(){
  const tbody = document.getElementById('evBody');
  if(!tbody) return;
  const tr = document.createElement('tr');
tr.innerHTML = `
  <td>${rowIndex + 1}</td>

  <td>
    <select name="items[${rowIndex}][type]">
      <option value="">اختر</option>
      <option>استفسار</option>
      <option>شكوى</option>
      <option>اقتراح</option>
      <option>طلب</option>
    </select>
  </td>

  <td>
    <textarea
      name="items[${rowIndex}][text]"
      placeholder="اكتب البيان"></textarea>
  </td>

  <td>
    <select name="items[${rowIndex}][status]">
      <option value="">اختر</option>
      <option>جديدة</option>
      <option>تحت الإجراء</option>
      <option>قيد المعالجة</option>
      <option>مغلقة</option>
    </select>
  </td>

  <td>
    <textarea
      name="items[${rowIndex}][protection_notes]"
      placeholder="التحسين والملاحظات"></textarea>
  </td>

  <td>
    <button type="button"
            class="btn-del"
            onclick="this.closest('tr').remove()">
      ×
    </button>
  </td>
`;

  tbody.appendChild(tr);
  rowIndex++;
}

addRow();
addRow();
</script>

</body>
</html>