<?php
// health.php
// 用于检测环境健康状况的脚本
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "DEBUG: " . (DEBUG ? 'on' : 'off') . PHP_EOL;
echo "Session save path: " . ini_get('session.save_path') . PHP_EOL;

echo "PDO class: " . (class_exists('PDO') ? 'OK' : 'MISSING') . PHP_EOL;
if (class_exists('PDO')) {
    $drivers = PDO::getAvailableDrivers();
    echo "PDO drivers: " . implode(',', $drivers) . PHP_EOL;
    echo "pdo_mysql: " . (in_array('mysql', $drivers, true) ? 'OK' : 'MISSING') . PHP_EOL;
}

echo "Trying DB connect..." . PHP_EOL;
try {
    require_once __DIR__ . '/db.php';
    $pdo = get_pdo();
    $row = $pdo->query('SELECT NOW() t')->fetch();
    echo "DB OK, server time: " . $row['t'] . PHP_EOL;
} catch (Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . PHP_EOL;
}