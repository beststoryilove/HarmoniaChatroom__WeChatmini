<?php
// fetch_online.php
// 获取当前在线用户列表的接口
require_once __DIR__ . '/functions.php';
require_login();

$uid = (int)current_user_id();

// 若被封禁，禁止继续
$ban = active_ban_for_user($uid);
if ($ban) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'banned']);
    exit;
}

// 只读会话，释放会话锁（避免阻塞其它并发请求）
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = get_pdo();

// 强制刷新自己的在线心跳，避免刚进入房间或心跳被阻塞时漏判
try {
    $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$uid]);
} catch (Throwable $e) {
    // 忽略单次失败
}

$sec = (int)ONLINE_WINDOW_SECONDS;

// 用 DB 的 NOW() - INTERVAL 做在线时间窗判断，避免 PHP/MySQL 时区不一致
$sql = "
SELECT u.id, u.username, u.is_admin, u.last_seen
FROM users u
WHERE u.last_seen IS NOT NULL
  AND u.last_seen >= NOW() - INTERVAL {$sec} SECOND
  AND NOT EXISTS (
    SELECT 1 FROM bans b
    WHERE b.user_id = u.id
      AND b.expires_at > NOW()
  )
ORDER BY u.is_admin DESC, u.username ASC
";
$stmt = $pdo->query($sql);

$users = [];
$foundMe = false;
while ($r = $stmt->fetch()) {
    $isMe = ((int)$r['id'] === $uid);
    if ($isMe) $foundMe = true;
    $users[] = [
        'username' => $r['username'],
        'is_admin' => ((int)$r['is_admin'] === 1),
        'me' => $isMe,
    ];
}

// 兜底：若没查到自己，强行把自己补进去
if (!$foundMe) {
    $stmt2 = $pdo->prepare('SELECT id, username, is_admin FROM users WHERE id = ?');
    $stmt2->execute([$uid]);
    if ($meRow = $stmt2->fetch()) {
        $users[] = [
            'username' => $meRow['username'],
            'is_admin' => ((int)$meRow['is_admin'] === 1),
            'me' => true,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['users' => $users]);