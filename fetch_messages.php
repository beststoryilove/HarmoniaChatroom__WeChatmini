<?php
// fetch_messages.php
// 获取消息列表的接口
require_once __DIR__ . '/functions.php';
require_login();

// 拦截封禁
$ban = active_ban_for_user(current_user_id());
if ($ban) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'banned']);
    exit;
}

// 读取完会话后立即释放锁，避免阻塞其它并发请求
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$pdo = get_pdo();

// 关键：在数据库端把 created_at 转为固定的北京时间（+08:00），统一前端展示
$stmt = $pdo->prepare("
    SELECT
        m.id,
        u.username,
        u.is_admin,
        m.content,
        DATE_FORMAT(CONVERT_TZ(m.created_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS time_cst
    FROM messages m
    JOIN users u ON u.id = m.user_id
    WHERE m.id > ?
    ORDER BY m.id ASC
    LIMIT " . (int)FETCH_LIMIT . "
");
$stmt->execute([$last_id]);
$rows = $stmt->fetchAll();

$messages = [];
foreach ($rows as $r) {
    $messages[] = [
        'id' => (int)$r['id'],
        'username' => $r['username'],
        'is_admin' => ((int)$r['is_admin'] === 1),
        'content' => $r['content'],
        // 统一返回北京时间字符串；若转换失败则兜底用原字段
        'time' => $r['time_cst'] ?: date('Y-m-d H:i:s'),
    ];
}

header('Content-Type: application/json');
echo json_encode(['messages' => $messages]);