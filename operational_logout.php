<?php
session_start();

// مسح كل بيانات الجلسة الخاصة بالملف التشغيلي
unset(
    $_SESSION['op_user_id'],
    $_SESSION['op_full_name'],
    $_SESSION['op_user_name'],
    $_SESSION['op_department'],
    $_SESSION['op_role']
);

session_destroy();

header("Location: operational_login.php");
exit;
