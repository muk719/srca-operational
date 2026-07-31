<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['ex_user_id']) || ($_SESSION['ex_role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? 'max';
    $tableName = ($type === 'min') ? 'min_standards' : 'max_standards';

    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'لم يتم رفع الملف';
    } else {

        $filePath = $_FILES['excel_file']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $inserted = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                $department_id = trim($row[0] ?? '');
                $code          = trim($row[1] ?? '');
                $category      = trim($row[2] ?? '');
                $criterion     = trim($row[3] ?? '');
                $indicator     = trim($row[4] ?? '');
                $sub_criterion = trim($row[5] ?? '');
                $measurement   = trim($row[6] ?? '');
                $expected      = trim($row[7] ?? '');
                $min_standard  = trim($row[8] ?? '');
                $weight        = trim($row[9] ?? '');
                $center_name   = trim($row[10] ?? '');
                $plate_number  = trim($row[11] ?? '');
                $notes         = trim($row[12] ?? '');

                if ($code === '' && $criterion === '') {
                    continue;
                }

                $stmt = getDB()->prepare("
                    INSERT INTO $tableName
                    (
                        department_id,
                        code,
                        category,
                        criterion,
                        indicator,
                        sub_criterion,
                        measurement_method,
                        expected_result,
                        min_operation_standard,
                        weight,
                        center_name,
                        plate_number,
                        notes,
                        created_by,
                        created_at
                    )
                    VALUES
                    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ");

                $stmt->execute([
                    $department_id ?: null,
                    $code,
                    $category,
                    $criterion,
                    $indicator,
                    $sub_criterion,
                    $measurement,
                    $expected,
                    $min_standard,
                    $weight ?: 0,
                    $center_name,
                    $plate_number,
                    $notes,
                    $_SESSION['ex_user_id']
                ]);

                $inserted++;
            }

            $msg = "تم استيراد $inserted سجل بنجاح";

        } catch (Throwable $e) {
            $msg = 'خطأ أثناء الاستيراد: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>استيراد Excel</title>
<style>
body{font-family:Tahoma;background:#f3f6fb;padding:40px}
.box{background:#fff;border:1px solid #ddd;border-radius:16px;padding:25px;max-width:600px;margin:auto}
input,select,button{width:100%;padding:12px;margin-top:10px;border-radius:10px;border:1px solid #ddd;font-family:Tahoma}
button{background:#0f766e;color:#fff;font-weight:bold;cursor:pointer}
.msg{background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:15px}
</style>
</head>
<body>

<div class="box">
    <h2>استيراد ملف Excel للمعايير</h2>

    <?php if($msg): ?>
        <div class="msg"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>نوع المعايير</label>
        <select name="type">
            <option value="max">الحد الأعلى</option>
            <option value="min">الحد الأدنى</option>
        </select>

        <label>ملف Excel</label>
        <input type="file" name="excel_file" accept=".xlsx,.xls" required>

        <button type="submit">استيراد</button>
    </form>
</div>

</body>
</html>