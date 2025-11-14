<?php
// long_poll.php
// 仅负责“等待新消息或消息版本变化”，返回后端统一北京时间
require_once __DIR__ . '/functions.php';
require_login();

// 若被封禁，直接拦截
$uid = (int)current_user_id();
$ban = active_ban_for_user($uid);
if ($ban) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'banned']);
    exit;
}

// 读取完会话释放锁，避免阻塞其它请求
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');

$lastId  = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$lastVer = isset($_GET['ver']) ? (string)$_GET['ver'] : '';

$timeoutSec = 12;           // 每次最长等待 12s（免费主机通常 <=20s）
$intervalUs = 300000;       // 轮训间隔 300ms
$deadline   = microtime(true) + $timeoutSec;

try {
    $pdo = get_pdo();

    $getVersion = function() use ($pdo) {
        $row = $pdo->query("SELECT v FROM settings WHERE k='messages_version'")->fetch();
        return $row && $row['v'] !== null ? (string)$row['v'] : '0';
    };

    $fetchNew = function(int $sinceId) use ($pdo) {
        $stmt = $pdo->prepare("
            SELECT m.id, u.username, u.is_admin, m.content,
                   DATE_FORMAT(CONVERT_TZ(m.created_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS time_cst
            FROM messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.id > ?
            ORDER BY m.id ASC
            LIMIT " . (int)FETCH_LIMIT
        );
        $stmt->execute([$sinceId]);
        return $stmt->fetchAll();
    };

    $currentVer = $getVersion();

    while (true) {
        // 版本变化（管理员清空等）→ 通知前端重置
        if ($lastVer !== '' && $currentVer !== $lastVer) {
            echo json_encode(['reset' => true, 'messages' => [], 'messages_version' => $currentVer]);
            return;
        }

        // 有新消息 → 立即返回
        $rows = $fetchNew($lastId);
        if ($rows && count($rows) > 0) {
            $msgs = [];
            foreach ($rows as $r) {
                $msgs[] = [
                    'id'       => (int)$r['id'],
                    'username' => $r['username'],
                    'is_admin' => ((int)$r['is_admin'] === 1),
                    'content'  => $r['content'],
                    'time'     => $r['time_cst'] ?: date('Y-m-d H:i:s'),
                ];
            }
            echo json_encode(['reset' => false, 'messages' => $msgs, 'messages_version' => $currentVer]);
            return;
        }

        // 超时 → 返回空（前端会立刻再发起下一次）
        if (microtime(true) >= $deadline) {
            echo json_encode(['reset' => false, 'messages' => [], 'messages_version' => $currentVer]);
            return;
        }

        usleep($intervalUs);
        // 版本可能变化，下一轮再比较
        $currentVer = $getVersion();
    }
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'db_busy']);
}
