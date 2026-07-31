<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['op_user_id'])) {
    header("Location: operational_login.php");
    exit;
}

$userId     = $_SESSION['op_user_id'] ?? 0;
$userName   = $_SESSION['op_full_name'] ?? '';
$department = $_SESSION['op_department'] ?? '';
$userRole   = $_SESSION['op_role'] ?? '';

if (isset($REQUIRED_DEPT) && $REQUIRED_DEPT !== '') {

    if ($department !== $REQUIRED_DEPT) {
        header("Location: dept_router.php");
        exit;
    }
}

$msg = '';

$unreadNotes = [];

try {

    $stmt = pdo()->prepare("
        SELECT *
        FROM operational_notes
        WHERE department = ?
        AND (is_read = 0 OR is_read IS NULL)
        ORDER BY created_at DESC
    ");

    $stmt->execute([$department]);
    $unreadNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e) {
    $unreadNotes = [];
}

if (isset($_GET['mark_read'])) {

    try {

        pdo()->prepare("
            UPDATE operational_notes
            SET is_read = 1
            WHERE department = ?
        ")->execute([$department]);

    } catch(Throwable $e){}

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

function hoursLeft($dateTime){

    $remain = strtotime($dateTime) + 86400 - time();

    if ($remain <= 0) {
        return null;
    }

    $hours = floor($remain / 3600);
    $mins  = floor(($remain % 3600) / 60);

    return $hours . 'س ' . $mins . 'د';
}

$todayEntries = [];

try {

    $stmt = pdo()->prepare("
        SELECT *
        FROM operational_entries
        WHERE department = ?
        AND DATE(created_at) = CURDATE()
        ORDER BY created_at DESC
    ");

    $stmt->execute([$department]);

    $todayEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Throwable $e) {
    $todayEntries = [];
}