<?php
declare(strict_types=1);

function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $port = null;

    if (getenv('MYSQLHOST') || getenv('MYSQL_HOST')) {
        // Railway أو أي استضافة تستخدم متغيرات البيئة
        $host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST');
        $db   = getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'railway');
        $user = getenv('MYSQLUSER') ?: (getenv('MYSQL_USER') ?: 'root');
        $pass = getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: '');
        $port = getenv('MYSQLPORT') ?: (getenv('MYSQL_PORT') ?: '3306');
    } elseif (in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1'], true)) {
        // XAMPP على الجهاز
        $host = 'localhost';
        $db   = 'operational_management';
        $user = 'root';
        $pass = '';
    } else {
        // استضافة InfinityFree
        $host = 'sql311.infinityfree.com';
        $db   = 'if0_42324606_epiz_12345_operational';
        $user = 'if0_42324606';
        $pass = '';                            // ← ضع هنا كلمة مرور الاستضافة
    }

    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host" . ($port ? ";port=$port" : "") . ";dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}