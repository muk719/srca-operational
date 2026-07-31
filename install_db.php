<?php
/**
 * تثبيت قاعدة البيانات — يُشغَّل مرة واحدة فقط ثم يُحذف
 * الاستخدام: افتح  install_db.php?key=srca2026
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

if (($_GET['key'] ?? '') !== 'srca2026') {
    http_response_code(403);
    exit('❌ مفتاح غير صحيح. استخدم: install_db.php?key=srca2026');
}

$sqlFile = __DIR__ . '/operational_management_hosting.sql';
if (!file_exists($sqlFile)) {
    exit('❌ ملف SQL غير موجود: operational_management_hosting.sql');
}

$sql = file_get_contents($sqlFile);

// تقسيم الأوامر وتنفيذها
$statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
$ok = 0; $skip = 0; $errors = [];

foreach ($statements as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
    try {
        pdo()->exec($stmt);
        $ok++;
    } catch (Throwable $e) {
        // تجاهل أخطاء "موجود مسبقاً"
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            $skip++;
        } else {
            $errors[] = mb_substr($stmt, 0, 60) . '... → ' . $e->getMessage();
        }
    }
}

echo '<div dir="rtl" style="font-family:Tahoma;padding:30px;line-height:2">';
echo "<h2>✅ اكتمل التثبيت</h2>";
echo "<p>أوامر نُفذت: {$ok} — تم تخطيها (موجودة مسبقاً): {$skip}</p>";
if ($errors) {
    echo '<h3 style="color:#dc2626">أخطاء:</h3><ul>';
    foreach ($errors as $er) echo '<li>' . htmlspecialchars($er) . '</li>';
    echo '</ul>';
}
echo '<p style="color:#dc2626;font-weight:bold">⚠️ مهم: احذف هذا الملف (install_db.php) الآن من السيرفر.</p>';
echo '<p><a href="operational_login.php">→ الذهاب لتسجيل الدخول</a></p>';
echo '</div>';
