<?php
// combined_poll.php
// 合并轮询：更新在线心跳 + 拉取新消息(北京时间) + 在线用户列表(可选)
require_once __DIR__ . '/functions.php';
require_login();

$uid   = (int)current_user_id();
$token = current_session_token();

// 封禁拦截
$ban = active_ban_for_user($uid);
if ($ban) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'banned']);
    exit;
}

// 释放会话锁
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json');

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
// no_users=1 时不查询在线用户，减轻数据库负担
$noUsers = isset($_GET['no_users']) ? (int)$_GET['no_users'] : 0;
$sec = (int)ONLINE_WINDOW_SECONDS;

try {
    $pdo = get_pdo();

    // 1) 心跳：总是刷新 last_seen；仅 token 匹配时刷新 active_session_updated
    $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$uid]);
    if ($token) {
        $pdo->prepare('UPDATE users SET active_session_updated = NOW() WHERE id = ? AND active_session = ?')
            ->execute([$uid, $token]);
    }

    // 二次校验：如 DB 中存在与当前 token 不同的活跃会话，则让本会话失效
    $stmt = $pdo->prepare("
        SELECT active_session,
               (active_session IS NOT NULL AND active_session_updated IS NOT NULL
                 AND active_session_updated >= NOW() - INTERVAL {$sec} SECOND) AS alive
        FROM users WHERE id = ?
    ");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row && (int)$row['alive'] === 1) {
        $activeToken = (string)$row['active_session'];
        if ($activeToken !== '' && ($token === null || !hash_equals($activeToken, (string)$token))) {
            http_response_code(409);
            echo json_encode(['error' => 'session_conflict']);
            return;
        }
    }

    // 2) 拉新消息（北京时间）
    $stmt = $pdo->prepare("
        SELECT m.id, u.username, u.is_admin, m.content,
               DATE_FORMAT(CONVERT_TZ(m.created_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS time_cst
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.id > ?
        ORDER BY m.id ASC
        LIMIT " . (int)FETCH_LIMIT
    );
    $stmt->execute([$last_id]);
    $rows = $stmt->fetchAll();

    $messages = [];
    foreach ($rows as $r) {
        $messages[] = [
            'id'       => (int)$r['id'],
            'username' => $r['username'],
            'is_admin' => ((int)$r['is_admin'] === 1),
            'content'  => $r['content'],
            'time'     => $r['time_cst'] ?: date('Y-m-d H:i:s'),
        ];
    }

    // 3) 在线用户列表（按需）
    $users = null;
    if (!$noUsers) {
        $sqlOnline = "
            SELECT u.id, u.username, u.is_admin
            FROM users u
            WHERE u.last_seen IS NOT NULL
              AND u.last_seen >= NOW() - INTERVAL {$sec} SECOND
              AND NOT EXISTS (SELECT 1 FROM bans b WHERE b.user_id = u.id AND b.expires_at > NOW())
            ORDER BY u.is_admin DESC, u.username ASC
        ";
        $users = [];
        $foundMe = false;
        foreach ($pdo->query($sqlOnline) as $r) {
            $isMe = ((int)$r['id'] === $uid);
            if ($isMe) $foundMe = true;
            $users[] = [
                'username' => $r['username'],
                'is_admin' => ((int)$r['is_admin'] === 1),
                'me'       => $isMe,
            ];
        }
        if (!$foundMe) {
            $stmt2 = $pdo->prepare('SELECT id, username, is_admin FROM users WHERE id = ?');
            $stmt2->execute([$uid]);
            if ($me = $stmt2->fetch()) {
                $users[] = ['username' => $me['username'], 'is_admin' => ((int)$me['is_admin'] === 1), 'me' => true];
            }
        }
    }

    echo json_encode(['messages' => $messages, 'users' => $users]);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'db_busy', 'message' => '服务繁忙，请稍后重试']);
}