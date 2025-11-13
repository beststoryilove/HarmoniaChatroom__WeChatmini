<?php
// upgrade_settings.php
// 创建/升级 settings 表，初始化公告文本与URL
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = get_pdo();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) PRIMARY KEY,
            v TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    // 初始化键
    $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES ('announcement_text', '')")->execute();
    $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES ('announcement_url',  '')")->execute();

    echo "OK: settings ready.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}