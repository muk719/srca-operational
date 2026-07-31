<?php
// لوحة الأدمن الفعلية هي operational_file.php — هذا الملف يوجّه إليها
session_start();

if (empty($_SESSION['op_user_id'])) {
    header("Location: operational_login.php");
    exit;
}

if (($_SESSION['op_role'] ?? '') !== 'admin') {
    header("Location: dept_router.php");
    exit;
}

header("Location: operational_file.php");
exit;
