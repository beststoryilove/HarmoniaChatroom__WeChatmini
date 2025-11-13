<?php
// get_announcement.php
require_once __DIR__ . '/functions.php';
require_login();

// 读取完会话后释放锁
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_pdo();
    $stmt = $pdo->query("
        SELECT k, v,
               DATE_FORMAT(CONVERT_TZ(updated_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS updated_cst
        FROM settings
        WHERE k IN ('announcement_text','announcement_url')
    ");
    $text = '';
    $url  = '';
    $v1 = '1970-01-01 00:00:00';
    $v2 = '1970-01-01 00:00:00';
    while ($row = $stmt->fetch()) {
        if ($row['k'] === 'announcement_text') { $text = (string)$row['v']; $v1 = $row['updated_cst'] ?: $v1; }
        if ($row['k'] === 'announcement_url')  { $url  = (string)$row['v']; $v2 = $row['updated_cst'] ?: $v2; }
    }
    // 版本号：取两个更新时间的较新者
    $verTs = max(strtotime($v1), strtotime($v2));
    $ver = $verTs > 0 ? date('YmdHis', $verTs) : '0';

    // 仅允许 http/https
    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
        $url = '';
    }

    echo json_encode(['text' => $text, 'url' => $url, 'version' => $ver]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['text' => '', 'url' => '', 'version' => '0']);
}