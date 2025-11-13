<?php
// db.php
// 数据库连接与 PDO 实例获取
require_once __DIR__ . '/config.php';

function get_pdo() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!class_exists('PDO')) {
        throw new RuntimeException('服务器未启用 PDO 扩展。请在主机面板启用 PDO 或改用 mysqli。');
    }
    $drivers = PDO::getAvailableDrivers();
    if (!in_array('mysql', $drivers, true)) {
        throw new RuntimeException('PDO MySQL 驱动（pdo_mysql）未启用。');
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (Throwable $e) {
        // 不泄漏敏感信息，仅返回错误原因
        throw new RuntimeException('数据库连接失败：' . $e->getMessage());
    }

    return $pdo;
}