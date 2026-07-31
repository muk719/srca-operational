<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

function response($ok, $msg, $extra = []){
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $msg
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['op_user_id'])) {
    response(false, 'انتهت الجلسة، سجل الدخول من جديد');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, 'طريقة الطلب غير صحيحة');
}

$userId     = (int)$_SESSION['op_user_id'];
$department = $_SESSION['op_department'] ?? '';
$section    = trim($_POST['section'] ?? '');
$data       = $_POST['data'] ?? [];

if ($department === '') {
    response(false, 'لم يتم تحديد القسم');
}

if (!is_array($data)) {
    response(false, 'البيانات غير صحيحة');
}

try {

    /*
    |--------------------------------------------------------------------------
    | صوت الموظف
    |--------------------------------------------------------------------------
    */
    if ($department === 'صوت الموظف') {

        $monthYear = trim($data['month_year'] ?? date('Y-m'));
        $items = $data['items'] ?? [];

        pdo()->beginTransaction();

        $stmt = pdo()->prepare("
            INSERT INTO employee_voice_reports
            (user_id, department, month_year, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $department, $monthYear]);

        $reportId = pdo()->lastInsertId();
        $saved = 0;

        foreach ($items as $r) {
            $type   = trim($r['type'] ?? '');
            $text   = trim($r['text'] ?? '');
            $status = trim($r['status'] ?? '');
            $notes  = trim($r['notes'] ?? $r['protection_notes'] ?? '');

            if ($type !== '' || $text !== '' || $status !== '' || $notes !== '') {
                pdo()->prepare("
                    INSERT INTO employee_voice_items
                    (report_id, item_type, statement_text, status, protection_notes, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $reportId,
                    $type,
                    $text,
                    $status,
                    $notes
                ]);

                $saved++;
            }
        }

        pdo()->commit();

        response(true, "تم حفظ صوت الموظف وإرسال {$saved} صف للأدمن");
    }

    /*
    |--------------------------------------------------------------------------
    | التموين الطبي والمستودعات
    |--------------------------------------------------------------------------
    */
    if ($department === 'التموين الطبي والمستودعات') {

        pdo()->prepare("
            INSERT INTO supply_daily_reports
            (user_id, department, data_json, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([
            $userId,
            $department,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);

        response(true, 'تم حفظ بيانات التموين الطبي والمستودعات');
    }

    /*
    |--------------------------------------------------------------------------
    | تشغيل وصيانة الأسطول
    |--------------------------------------------------------------------------
    */
    if ($department === 'تشغيل وصيانة الأسطول') {

        pdo()->prepare("
            INSERT INTO fleet_daily_reports
            (user_id, department, data_json, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([
            $userId,
            $department,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);

        response(true, 'تم حفظ بيانات تشغيل وصيانة الأسطول');
    }

 
    if ($department === 'الإدارة القانونية') {

        pdo()->prepare("
            INSERT INTO legal_department_reports
            (user_id, department, data_json, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([
            $userId,
            $department,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);

        response(true, 'تم حفظ بيانات الإدارة القانونية');
    }

  
    if ($department === 'الاتصال المؤسسي') {

        pdo()->prepare("
            INSERT INTO corporate_communication_reports
            (user_id, department, data_json, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([
            $userId,
            $department,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);

        response(true, 'تم حفظ بيانات الاتصال المؤسسي');
    }


    pdo()->prepare("
        INSERT INTO operational_entries
        (report_year, report_month, department, section_name, title, value_1, notes, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        date('Y'),
        date('m'),
        $department,
        $section ?: $department,
        'بيانات القسم',
        json_encode($data, JSON_UNESCAPED_UNICODE),
        '',
        $userId
    ]);

    response(true, 'تم حفظ البيانات في الجدول العام مؤقتاً');

} catch(Throwable $e) {

    if (pdo()->inTransaction()) {
        pdo()->rollBack();
    }

    response(false, 'خطأ أثناء الحفظ: ' . $e->getMessage());
}