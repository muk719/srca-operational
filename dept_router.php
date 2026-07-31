<?php
session_start();

if (empty($_SESSION['op_user_id'])) {
    header("Location: operational_login.php");
    exit;
}

$role       = $_SESSION['op_role'] ?? '';
$department = $_SESSION['op_department'] ?? '';

// الأدمن يذهب للوحة التقارير
if ($role === 'admin') {
    header("Location: admin_operational_reports.php");
    exit;
}

// توجيه كل قسم لصفحته
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

if (isset($deptPages[$department])) {
    header("Location: " . $deptPages[$department]);
    exit;
}

// قسم غير معروف → خروج
header("Location: operational_logout.php");
exit;
