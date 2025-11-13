<?php
// send_message.php
require_once __DIR__ . '/functions.php';
require_login();

// 被封禁则禁止发送
$ban = active_ban_for_user(current_user_id());
if ($ban) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'banned']);
    exit;
}

// 同时支持 POST 或 GET 作为兜底（应对部分免费主机对 POST 的拦截）
$content = $_POST['content'] ?? ($_GET['content'] ?? '');
$content = trim((string)$content);

header('Content-Type: application/json');

if ($content === '') {
    echo json_encode(['error' => '内容不能为空']);
    exit;
}

$user = fetch_user_by_id(current_user_id());
$is_admin = $user && (int)$user['is_admin'] === 1;

// 后续只读会话信息了，释放会话锁，避免阻塞其它请求
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$pdo = get_pdo();

// 管理员命令处理
if ($is_admin && strlen($content) > 0 && $content[0] === '/') {
    try {
        // /clearallmsg
        if (preg_match('#^/clearallmsg\s*$#i', $content)) {
            $pdo->exec('DELETE FROM messages');
            echo json_encode(['info' => '已清空所有聊天记录']);
            exit;
        }

        // /ban 用户名 原因 天数
        if (stripos($content, '/ban ') === 0) {
            $parts = preg_split('/\s+/', $content);
            if (count($parts) < 4) {
                echo json_encode(['error' => '用法：/ban 用户名 封禁原因 封禁时间(天)']);
                exit;
            }
            $username = $parts[1];
            $days_str = $parts[count($parts) - 1];
            $reason = implode(' ', array_slice($parts, 2, -1));

            $days = (int)$days_str;
            if ($days <= 0) {
                echo json_encode(['error' => '封禁时间(天)必须为正整数']);
                exit;
            }
            $target = fetch_user_by_username($username);
            if (!$target) {
                echo json_encode(['error' => '用户不存在']);
                exit;
            }
            if ((int)$target['id'] === (int)$user['id']) {
                echo json_encode(['error' => '不能封禁自己']);
                exit;
            }
            if (!is_user_online((int)$target['id'])) {
                echo json_encode(['error' => '该用户当前不在线，无法封禁']);
                exit;
            }

            // 移除未过期的旧封禁
            $pdo->prepare('DELETE FROM bans WHERE user_id = ? AND expires_at > NOW()')->execute([(int)$target['id']]);

            $expires = (new DateTime())->modify('+' . $days . ' day')->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO bans (user_id, reason, expires_at, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([(int)$target['id'], $reason, $expires, (int)$user['id']]);

            echo json_encode(['info' => '已封禁用户：' . $username . '，' . $days . '天，原因：' . $reason]);
            exit;
        }

        echo json_encode(['error' => '未知命令']);
        exit;
    } catch (Throwable $e) {
        $msg = defined('DEBUG') && DEBUG ? ('命令执行失败: ' . $e->getMessage()) : '命令执行失败';
        http_response_code(500);
        echo json_encode(['error' => $msg]);
        exit;
    }
}

// 普通消息入库
try {
    $stmt = $pdo->prepare('INSERT INTO messages (user_id, content) VALUES (?, ?)');
    $stmt->execute([(int)$user['id'], $content]);

    // 返回新消息的 id 与时间，供前端去重/转正
    $id = (int)$pdo->lastInsertId();
    $time = date('Y-m-d H:i:s');

    // 说话也刷新在线时间
    try {
        $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    } catch (Throwable $e) {}

    echo json_encode(['ok' => true, 'id' => $id, 'time' => $time]);
} catch (Throwable $e) {
    $msg = defined('DEBUG') && DEBUG ? ('数据库错误: ' . $e->getMessage()) : '服务器繁忙，请稍后再试';
    http_response_code(500);
    echo json_encode(['error' => $msg]);
}