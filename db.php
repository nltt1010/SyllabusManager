<?php
$DB_USER = 'root';
$DB_PASS = '';
$DB_HOST = 'localhost';
$DB_NAME = 'tempctdt';

ini_set('display_errors', 0);

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Exception $e) {
    echo "Database connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
?>