<?php
// init_db.php
// 温馨提示：本文件用于初始化数据库，仅运行一次即可，运行后请删除或保护本文件以防止滥用。
require_once __DIR__ . '/functions.php';

try {
    $pdo = get_pdo();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by INT NULL,
            INDEX (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure admin user exists; password not used (admin通过config密码验证)
    $admin = fetch_user_by_username('admin');
    if (!$admin) {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, 1)');
        $stmt->execute(['admin', password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
    } else {
        if ((int)$admin['is_admin'] !== 1) {
            $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$admin['id']]);
        }
    }

    echo "数据库初始化完成。请删除或保护本文件。";
} catch (Throwable $e) {
    http_response_code(500);
    echo "初始化失败: " . h($e->getMessage());
}