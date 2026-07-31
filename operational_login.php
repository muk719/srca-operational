<?php
session_start();
require_once 'db.php';

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$error = '';

$deptPages = [
    'إدارة الشؤون الطبية'             => 'dept_medical.php',
    'إدارة القطاعات'                  => 'dept_sectors.php',
    'إدارة الطوارئ'                   => 'dept_emergency.php',
    'إدارة التطوع'                    => 'dept_volunteer.php',
    'التموين الطبي والمستودعات'       => 'dept_supply.php',
    'تشغيل وصيانة الأسطول'            => 'dept_fleet.php',
    'تشغيل وصيانة المرافق'            => 'dept_facilities.php',
    'الاتصال المؤسسي'                 => 'dept_comm.php',
    'الوضع التشغيلي للخدمات التقنية'  => 'dept_it.php',
    'إدارة الالتزام'                  => 'dept_compliance.php',
    'الإدارة القانونية'               => 'dept_legal.php',
    'صوت الموظف'                      => 'dept_employee.php',
];

function checkPassword($input, $stored){
    if (!$stored) return false;

    if (password_get_info($stored)['algo']) {
        return password_verify($input, $stored);
    }

    return $input === $stored;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'أدخل اسم المستخدم وكلمة المرور';
    } else {
        try {

            $stmt = pdo()->prepare("
                SELECT *
                FROM system_users
                WHERE username = ?
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && checkPassword($password, $admin['password'] ?? '')) {
                $_SESSION['op_user_id']    = $admin['id'];
                $_SESSION['op_full_name']  = $admin['full_name'] ?? $admin['username'];
                $_SESSION['op_user_name']  = $admin['full_name'] ?? $admin['username'];
                $_SESSION['op_department'] = $admin['department'] ?? 'الكل';
                $_SESSION['op_role']       = 'admin';

                header("Location: admin_operational_reports.php");
                exit;
            }

            $stmt = pdo()->prepare("
                SELECT *
                FROM operational_users
                WHERE username = ?
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && checkPassword($password, $user['password'] ?? '')) {
                $_SESSION['op_user_id']    = $user['id'];
                $_SESSION['op_full_name']  = $user['full_name'];
                $_SESSION['op_user_name']  = $user['full_name'];
                $_SESSION['op_department'] = $user['department'];
                $_SESSION['op_role']       = 'department';

                pdo()->prepare("
                    UPDATE operational_users
                    SET last_login = NOW()
                    WHERE id = ?
                ")->execute([$user['id']]);

                $target = $deptPages[$user['department']] ?? 'operational_user_portal.php';

                header("Location: " . $target);
                exit;
            }

            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';

        } catch(Throwable $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول — الملف التشغيلي</title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI',Tahoma,Arial,sans-serif;
    min-height:100vh;
    background:#f1f5f9;
    direction:rtl;
    display:flex;
    overflow:hidden;
}

.login-page{
    width:100%;
    min-height:100vh;
    display:grid;
    grid-template-columns:420px 1fr;
}

/* الفورم */
.login-box{
    background:#fff;
    padding:45px 38px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    box-shadow:-10px 0 35px rgba(0,0,0,.12);
    z-index:2;
}

.logo-box{
    width:76px;
    height:76px;
    border-radius:22px;
    background:linear-gradient(135deg,#16a34a,#0f7a38);
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 22px;
    font-size:34px;
    box-shadow:0 12px 30px rgba(22,163,74,.28);
}

.title{
    text-align:center;
    font-size:27px;
    font-weight:900;
    color:#0f172a;
    margin-bottom:7px;
}

.subtitle{
    text-align:center;
    font-size:13px;
    color:#64748b;
    margin-bottom:28px;
}

.alert{
    background:#fef2f2;
    border:1px solid #fca5a5;
    color:#dc2626;
    padding:12px 14px;
    border-radius:12px;
    font-size:13px;
    font-weight:800;
    margin-bottom:18px;
    text-align:center;
}

.form-group{
    margin-bottom:17px;
}

.form-group label{
    display:block;
    font-size:13px;
    font-weight:900;
    color:#334155;
    margin-bottom:7px;
}

.input-wrap{
    position:relative;
}

.input-wrap span{
    position:absolute;
    right:14px;
    top:50%;
    transform:translateY(-50%);
    font-size:18px;
}

.input-wrap input{
    width:100%;
    height:52px;
    border:1.5px solid #e2e8f0;
    border-radius:14px;
    padding:0 46px 0 14px;
    font-family:inherit;
    font-size:14px;
    outline:none;
    background:#f8fafc;
    color:#0f172a;
    transition:.2s;
}

.input-wrap input:focus{
    background:#fff;
    border-color:#16a34a;
    box-shadow:0 0 0 4px rgba(22,163,74,.12);
}

.btn-login{
    width:100%;
    height:54px;
    border:none;
    border-radius:15px;
    background:linear-gradient(135deg,#16a34a,#15803d);
    color:#fff;
    font-size:16px;
    font-weight:900;
    font-family:inherit;
    cursor:pointer;
    margin-top:6px;
    box-shadow:0 10px 24px rgba(22,163,74,.28);
    transition:.2s;
}

.btn-login:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 30px rgba(22,163,74,.38);
}

.depts{
    margin-top:28px;
    border-top:1px solid #e5e7eb;
    padding-top:18px;
}

.depts-title{
    text-align:center;
    font-size:11px;
    font-weight:900;
    color:#94a3b8;
    margin-bottom:12px;
}

.depts-list{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:7px;
}

.depts-list span{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#64748b;
    padding:5px 9px;
    border-radius:8px;
    font-size:11px;
    font-weight:700;
}

/* الخلفية */
.visual{
    position:relative;
    background:
        radial-gradient(circle at 25% 25%, rgba(22,163,74,.25), transparent 28%),
        radial-gradient(circle at 80% 70%, rgba(59,130,246,.18), transparent 30%),
        linear-gradient(135deg,#0f172a,#0b2d3a,#064e3b);
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:50px;
}

.visual::before{
    content:'';
    position:absolute;
    inset:0;
    background-image:
        linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
    background-size:42px 42px;
}

.visual-card{
    position:relative;
    z-index:1;
    max-width:520px;
    text-align:center;
    color:#fff;
}

.visual-logo{
    width:120px;
    height:120px;
    border-radius:50%;
    margin:0 auto 24px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.18);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:54px;
    box-shadow:0 0 50px rgba(22,163,74,.25);
}

.visual-title{
    font-size:34px;
    font-weight:900;
    line-height:1.45;
    margin-bottom:14px;
}

.visual-sub{
    color:rgba(255,255,255,.72);
    line-height:1.9;
    font-size:15px;
}

.float{
    position:absolute;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.1);
    color:rgba(255,255,255,.55);
    border-radius:12px;
    padding:8px 14px;
    font-size:12px;
    animation:up 18s linear infinite;
    white-space:nowrap;
}

@keyframes up{
    0%{transform:translateY(105vh);opacity:0}
    12%{opacity:1}
    90%{opacity:1}
    100%{transform:translateY(-15vh);opacity:0}
}

@media(max-width:850px){
    body{
        overflow:auto;
    }

    .login-page{
        grid-template-columns:1fr;
    }

    .visual{
        display:none;
    }

    .login-box{
        min-height:100vh;
        box-shadow:none;
    }
}
</style>
</head>

<body>

<div class="login-page">

    <div class="login-box">

        <div class="logo-box">🔐</div>

        <div class="title">تسجيل الدخول</div>
        <div class="subtitle">أدخل بياناتك للوصول إلى الملف التشغيلي</div>

        <?php if($error): ?>
            <div class="alert">⚠️ <?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">

            <div class="form-group">
                <label>اسم المستخدم</label>
                <div class="input-wrap">
                    <span>👤</span>
                    <input type="text"
                           name="username"
                           value="<?= h($_POST['username'] ?? '') ?>"
                           placeholder="مثال: AMAL09"
                           required
                           autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>كلمة المرور</label>
                <div class="input-wrap">
                    <span>🔒</span>
                    <input type="password"
                           name="password"
                           placeholder="أدخل كلمة المرور"
                           required>
                </div>
            </div>

            <button class="btn-login" type="submit">دخول ←</button>

        </form>

        <div class="depts">
            <div class="depts-title">الأقسام المتاحة</div>
            <div class="depts-list">
                <span>🏥 الشؤون الطبية</span>
                <span>📍 القطاعات</span>
                <span>🚨 الطوارئ</span>
                <span>🤝 التطوع</span>
                <span>📦 التموين</span>
                <span>🚑 الأسطول</span>
                <span>🏢 المرافق</span>
                <span>📢 الاتصال</span>
                <span>💻 التقنية</span>
                <span>✅ الالتزام</span>
                <span>⚖️ القانونية</span>
                <span>🗣️ صوت الموظف</span>
            </div>
        </div>

    </div>

    <div class="visual">

        <div class="float" style="left:12%;animation-delay:0s">🏥 إدارة الشؤون الطبية</div>
        <div class="float" style="left:68%;animation-delay:2s">📍 إدارة القطاعات</div>
        <div class="float" style="left:38%;animation-delay:4s">🚨 إدارة الطوارئ</div>
        <div class="float" style="left:75%;animation-delay:6s">📦 التموين الطبي</div>
        <div class="float" style="left:18%;animation-delay:8s">🚑 تشغيل الأسطول</div>
        <div class="float" style="left:58%;animation-delay:10s">💻 الخدمات التقنية</div>
        <div class="float" style="left:30%;animation-delay:12s">⚖️ الإدارة القانونية</div>

        <div class="visual-card">
            <div class="visual-logo">🌙</div>
            <div class="visual-title">
                الملف التشغيلي<br>
                هيئة الهلال الأحمر السعودي
            </div>
            <div class="visual-sub">
                منصة موحدة لإدخال ومتابعة بيانات الأقسام اليومية، مع توجيه كل مستخدم إلى صفحته حسب القسم.
            </div>
        </div>

    </div>

</div>

</body>
</html>