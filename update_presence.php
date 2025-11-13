<?php
require_once __DIR__ . '/functions.php';
require_login();

// 若被封禁，立即拦截
$ban = active_ban_for_user(current_user_id());
if ($ban) {
    http_response_code(403);
    echo 'banned';
    exit;
}

// 关键：释放会话锁，避免与其它接口互锁
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = get_pdo();
$pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([current_user_id()]);
echo 'ok';