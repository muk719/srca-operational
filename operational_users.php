<?php
session_start();
require_once 'db.php';

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// حماية: الأدمن فقط
if (empty($_SESSION['op_user_id'])) {
    header("Location: operational_login.php");
    exit;
}
if (($_SESSION['op_role'] ?? '') !== 'admin') {
    header("Location: dept_router.php");
    exit;
}
$departments = [
    ['name'=>'إدارة الشؤون الطبية','icon'=>'🏥','color'=>'#dc2626','bg'=>'#fef2f2'],
    ['name'=>'إدارة القطاعات','icon'=>'📍','color'=>'#16a34a','bg'=>'#f0fdf4'],
    ['name'=>'إدارة الطوارئ','icon'=>'🚨','color'=>'#ea580c','bg'=>'#fff7ed'],
    ['name'=>'إدارة التطوع','icon'=>'🤝','color'=>'#059669','bg'=>'#ecfdf5'],
    ['name'=>'التموين الطبي والمستودعات','icon'=>'📦','color'=>'#2563eb','bg'=>'#eff6ff'],
    ['name'=>'تشغيل وصيانة الأسطول','icon'=>'🚑','color'=>'#0284c7','bg'=>'#f0f9ff'],
    ['name'=>'تشغيل وصيانة المرافق','icon'=>'🏢','color'=>'#7e22ce','bg'=>'#fdf4ff'],
    ['name'=>'الاتصال المؤسسي','icon'=>'📢','color'=>'#c2410c','bg'=>'#fff7ed'],
    ['name'=>'الوضع التشغيلي للخدمات التقنية','icon'=>'💻','color'=>'#475569','bg'=>'#f8fafc'],
    ['name'=>'إدارة الالتزام','icon'=>'✅','color'=>'#15803d','bg'=>'#f0fdf4'],
    ['name'=>'الإدارة القانونية','icon'=>'⚖️','color'=>'#1e40af','bg'=>'#eff6ff'],
    ['name'=>'صوت الموظف','icon'=>'🗣️','color'=>'#be123c','bg'=>'#fff1f2'],
];

$deptMap = array_column($departments, null, 'name');
$msg = '';
$msgType = 'ok';

try {
    pdo()->exec("
        CREATE TABLE IF NOT EXISTS operational_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            username VARCHAR(60) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            department VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch(Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if ($full_name === '' || $username === '' || $password === '' || $department === '') {
        $msg = '⚠️ أكمل جميع الحقول';
        $msgType = 'err';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            pdo()->prepare("
                INSERT INTO operational_users
                (full_name, username, password, department, is_active)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $full_name,
                $username,
                $hash,
                $department,
                $is_active
            ]);

            header("Location: operational_users.php?done=add");
            exit;

        } catch(Throwable $e) {
            $msg = '❌ اسم الدخول موجود مسبقاً أو حدث خطأ';
            $msgType = 'err';
        }
    }
}

if (isset($_GET['delete'])) {
    pdo()->prepare("DELETE FROM operational_users WHERE id=?")
         ->execute([(int)$_GET['delete']]);

    header("Location: operational_users.php?done=delete");
    exit;
}

if (isset($_GET['toggle'])) {
    pdo()->prepare("
        UPDATE operational_users
        SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
        WHERE id = ?
    ")->execute([(int)$_GET['toggle']]);

    header("Location: operational_users.php?done=toggle");
    exit;
}

if (isset($_GET['done'])) {
    if ($_GET['done'] === 'add') {
        $msg = '✅ تم إضافة المستخدم بنجاح';
    } elseif ($_GET['done'] === 'delete') {
        $msg = '✅ تم حذف المستخدم';
    } elseif ($_GET['done'] === 'toggle') {
        $msg = '✅ تم تغيير حالة المستخدم';
    }
}

$users = [];
try {
    $users = pdo()->query("
        SELECT *
        FROM operational_users
        ORDER BY department ASC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

$total = count($users);
$active = count(array_filter($users, fn($u)=>(int)$u['is_active'] === 1));
$inactive = $total - $active;
$covered = count(array_unique(array_column($users, 'department')));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة مستخدمي الأقسام</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Segoe UI',Tahoma,Arial,sans-serif;
    background:#eef3f8;
    color:#111827;
    direction:rtl;
}
.header{
    background:linear-gradient(135deg,#0f172a,#1e3a5f,#16a34a);
    padding:28px;
    color:white;
}
.header-inner{
    max-width:1300px;
    margin:auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    flex-wrap:wrap;
}
.title{
    font-size:26px;
    font-weight:900;
}
.subtitle{
    margin-top:6px;
    color:rgba(255,255,255,.75);
    font-size:13px;
}
.back{
    color:white;
    text-decoration:none;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.3);
    padding:9px 18px;
    border-radius:999px;
    font-weight:800;
    font-size:13px;
}
.stats{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:12px;
}
.stat{
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.22);
    border-radius:12px;
    min-width:80px;
    padding:10px;
    text-align:center;
}
.stat b{
    display:block;
    font-size:23px;
}
.stat span{
    font-size:10px;
    color:rgba(255,255,255,.75);
    font-weight:800;
}
.wrap{
    max-width:1300px;
    margin:auto;
    padding:24px;
}
.msg{
    padding:12px 16px;
    border-radius:12px;
    margin-bottom:16px;
    font-weight:900;
}
.msg.ok{
    background:#ecfdf5;
    border:1px solid #86efac;
    color:#166534;
}
.msg.err{
    background:#fef2f2;
    border:1px solid #fca5a5;
    color:#dc2626;
}
.card{
    background:white;
    border:1px solid #e5e7eb;
    border-radius:18px;
    overflow:hidden;
    margin-bottom:22px;
}
.card-head{
    background:#0f172a;
    color:white;
    padding:15px 20px;
    font-weight:900;
}
.card-body{
    padding:18px;
}
.form-grid{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:12px;
    align-items:end;
}
.field label{
    display:block;
    font-size:12px;
    font-weight:900;
    color:#4b5563;
    margin-bottom:6px;
}
.field input,
.field select{
    width:100%;
    height:44px;
    border:1px solid #e5e7eb;
    border-radius:11px;
    padding:0 12px;
    font-family:inherit;
    outline:none;
}
.field input:focus,
.field select:focus{
    border-color:#16a34a;
    box-shadow:0 0 0 3px rgba(22,163,74,.12);
}
.check{
    height:44px;
    border:1px solid #e5e7eb;
    background:#f8fafc;
    border-radius:11px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    font-size:12px;
    font-weight:800;
}
.btn-add{
    height:44px;
    border:none;
    border-radius:11px;
    background:#16a34a;
    color:white;
    font-family:inherit;
    font-weight:900;
    cursor:pointer;
}
.btn-add:hover{
    background:#15803d;
}
.view-toggle{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-bottom:14px;
}
.vbtn{
    height:34px;
    padding:0 16px;
    border-radius:9px;
    border:1px solid #e5e7eb;
    background:white;
    font-family:inherit;
    font-size:12px;
    font-weight:900;
    cursor:pointer;
}
.vbtn.active{
    background:#0f172a;
    color:white;
}
.dept-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(285px,1fr));
    gap:16px;
}
.dept-card{
    background:white;
    border:1px solid #e5e7eb;
    border-radius:18px;
    overflow:hidden;
}
.dept-head{
    padding:15px;
    display:flex;
    align-items:center;
    gap:10px;
}
.dept-icon{
    width:46px;
    height:46px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
}
.dept-name{
    font-size:15px;
    font-weight:900;
}
.dept-count{
    font-size:11px;
    margin-top:3px;
    opacity:.7;
    font-weight:800;
}
.dept-body{
    padding:14px;
}
.empty{
    background:#fffbeb;
    border:1px dashed #facc15;
    border-radius:10px;
    padding:10px;
    color:#9ca3af;
    text-align:center;
    font-size:12px;
}
.user-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    background:#f8fafc;
    border:1px solid #eef2f7;
    border-radius:10px;
    padding:9px;
    margin-bottom:8px;
}
.user-name{
    font-weight:900;
    font-size:13px;
}
.user-login{
    color:#64748b;
    font-size:11px;
    margin-top:2px;
}
.badge{
    border-radius:999px;
    padding:3px 9px;
    font-size:10px;
    font-weight:900;
}
.badge.on{
    background:#dcfce7;
    color:#166534;
}
.badge.off{
    background:#fee2e2;
    color:#991b1b;
}
.actions{
    display:flex;
    gap:5px;
}
.btn-xs{
    height:26px;
    padding:0 8px;
    border-radius:7px;
    border:1px solid #e5e7eb;
    background:white;
    text-decoration:none;
    color:#111827;
    font-size:10px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
}
.btn-xs.danger{
    color:#dc2626;
    border-color:#fecaca;
}
.btn-xs.toggle{
    color:#15803d;
    border-color:#bbf7d0;
}
.table-card{
    display:none;
    background:white;
    border:1px solid #e5e7eb;
    border-radius:18px;
    overflow:hidden;
}
.table-title{
    background:#0f172a;
    color:white;
    padding:15px 20px;
    font-weight:900;
}
.tbl-wrap{
    overflow-x:auto;
}
table{
    width:100%;
    border-collapse:collapse;
}
th{
    background:#1e3a5f;
    color:white;
    padding:11px;
    font-size:12px;
}
td{
    border-bottom:1px solid #e5e7eb;
    padding:10px;
    text-align:center;
    font-size:12px;
}
td.name{
    text-align:right;
    font-weight:900;
}
.dept-pill{
    display:inline-flex;
    gap:5px;
    align-items:center;
    padding:4px 9px;
    border-radius:7px;
    font-weight:900;
    font-size:11px;
}
@media(max-width:1050px){
    .form-grid{
        grid-template-columns:1fr 1fr;
    }
}
@media(max-width:650px){
    .form-grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<header class="header">
    <div class="header-inner">
        <div>
            <div class="title">👥 إدارة مستخدمي الأقسام</div>
            <div class="subtitle">إضافة وإدارة مستخدمي الملف التشغيلي حسب القسم</div>
        </div>

        <div>
            <a class="back" href="admin_operational_reports.php">← رجوع للملف التشغيلي</a>

            <div class="stats">
                <div class="stat"><b><?= $total ?></b><span>إجمالي</span></div>
                <div class="stat"><b><?= $active ?></b><span>نشط</span></div>
                <div class="stat"><b><?= $inactive ?></b><span>موقوف</span></div>
                <div class="stat"><b><?= $covered ?>/12</b><span>أقسام</span></div>
            </div>
        </div>
    </div>
</header>

<main class="wrap">

    <?php if($msg): ?>
        <div class="msg <?= $msgType === 'ok' ? 'ok' : 'err' ?>">
            <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head">➕ إضافة مستخدم جديد</div>
        <div class="card-body">
            <form method="post">
                <div class="form-grid">
                    <div class="field">
                        <label>الاسم الكامل</label>
                        <input name="full_name" placeholder="الاسم الكامل" required>
                    </div>

                    <div class="field">
                        <label>اسم الدخول</label>
                        <input name="username" placeholder="مثال: AMAL09" required>
                    </div>

                    <div class="field">
                        <label>كلمة المرور</label>
                        <input type="password" name="password" placeholder="كلمة المرور" required>
                    </div>

                    <div class="field">
                        <label>القسم</label>
                        <select name="department" required>
                            <option value="">— اختر القسم —</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= h($d['name']) ?>">
                                    <?= $d['icon'] ?> <?= h($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <label class="check">
                        <input type="checkbox" name="is_active" checked>
                        نشط
                    </label>

                    <button class="btn-add" type="submit">+ إضافة</button>
                </div>
            </form>
        </div>
    </div>

    <div class="view-toggle">
        <button class="vbtn active" id="btnCards" onclick="switchView('cards')">🃏 عرض بطاقات</button>
        <button class="vbtn" id="btnTable" onclick="switchView('table')">📋 عرض جدول</button>
    </div>

    <section id="cardsView">
        <div class="dept-grid">
            <?php foreach($departments as $dept): ?>
                <?php
                $deptUsers = array_values(array_filter($users, function($u) use ($dept){
                    return ($u['department'] ?? '') === $dept['name'];
                }));
                ?>

                <div class="dept-card">
                    <div class="dept-head" style="background:<?= h($dept['bg']) ?>;border-bottom:3px solid <?= h($dept['color']) ?>">
                        <div class="dept-icon" style="background:<?= h($dept['color']) ?>22">
                            <?= $dept['icon'] ?>
                        </div>
                        <div>
                            <div class="dept-name" style="color:<?= h($dept['color']) ?>">
                                <?= h($dept['name']) ?>
                            </div>
                            <div class="dept-count"><?= count($deptUsers) ?> مستخدم</div>
                        </div>
                    </div>

                    <div class="dept-body">
                        <?php if(empty($deptUsers)): ?>
                            <div class="empty">⚠️ لا يوجد مستخدم لهذا القسم</div>
                        <?php else: ?>
                            <?php foreach($deptUsers as $u): ?>
                                <div class="user-row">
                                    <div>
                                        <div class="user-name"><?= h($u['full_name']) ?></div>
                                        <div class="user-login">@<?= h($u['username']) ?></div>
                                    </div>

                                    <span class="badge <?= (int)$u['is_active'] === 1 ? 'on' : 'off' ?>">
                                        <?= (int)$u['is_active'] === 1 ? 'نشط' : 'موقوف' ?>
                                    </span>

                                    <div class="actions">
                                        <a class="btn-xs toggle"
                                           href="?toggle=<?= (int)$u['id'] ?>"
                                           onclick="return confirm('تغيير حالة المستخدم؟')">
                                            <?= (int)$u['is_active'] === 1 ? 'إيقاف' : 'تفعيل' ?>
                                        </a>

                                        <a class="btn-xs danger"
                                           href="?delete=<?= (int)$u['id'] ?>"
                                           onclick="return confirm('حذف المستخدم؟')">
                                            حذف
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="table-card" id="tableView">
        <div class="table-title">جميع المستخدمين — <?= $total ?></div>

        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم الكامل</th>
                        <th>اسم الدخول</th>
                        <th>القسم</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th>تاريخ الإضافة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="8" style="padding:25px;color:#64748b">
                            لا يوجد مستخدمون
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($users as $i=>$u): ?>
                        <?php $d = $deptMap[$u['department']] ?? null; ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td class="name"><?= h($u['full_name']) ?></td>
                            <td style="color:#1d4ed8;font-weight:900">@<?= h($u['username']) ?></td>
                            <td>
                                <?php if($d): ?>
                                    <span class="dept-pill" style="background:<?= h($d['bg']) ?>;color:<?= h($d['color']) ?>">
                                        <?= $d['icon'] ?> <?= h($u['department']) ?>
                                    </span>
                                <?php else: ?>
                                    <?= h($u['department']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= (int)$u['is_active'] === 1 ? 'on' : 'off' ?>">
                                    <?= (int)$u['is_active'] === 1 ? 'نشط' : 'موقوف' ?>
                                </span>
                            </td>
                            <td><?= !empty($u['last_login']) ? h(substr($u['last_login'],0,16)) : '—' ?></td>
                            <td><?= h(substr($u['created_at'],0,10)) ?></td>
                            <td>
                                <div class="actions" style="justify-content:center">
                                    <a class="btn-xs toggle"
                                       href="?toggle=<?= (int)$u['id'] ?>"
                                       onclick="return confirm('تغيير حالة المستخدم؟')">
                                        <?= (int)$u['is_active'] === 1 ? 'إيقاف' : 'تفعيل' ?>
                                    </a>

                                    <a class="btn-xs danger"
                                       href="?delete=<?= (int)$u['id'] ?>"
                                       onclick="return confirm('حذف المستخدم؟')">
                                        حذف
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<script>
function switchView(type){
    const cards = document.getElementById('cardsView');
    const table = document.getElementById('tableView');
    const btnCards = document.getElementById('btnCards');
    const btnTable = document.getElementById('btnTable');

    if(type === 'table'){
        cards.style.display = 'none';
        table.style.display = 'block';
        btnCards.classList.remove('active');
        btnTable.classList.add('active');
    }else{
        cards.style.display = 'block';
        table.style.display = 'none';
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
    }
}
</script>

</body>
</html>