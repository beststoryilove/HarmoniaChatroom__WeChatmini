<?php
// functions.php
// 通用函数库
require_once __DIR__ . '/db.php';
session_start();

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_username(): ?string {
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

function current_session_token(): ?string {
    return isset($_SESSION['session_token']) ? (string)$_SESSION['session_token'] : null;
}

function is_admin_session(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function require_login(): void {
    if (!current_user_id()) {
        header('Location: login.php');
        exit;
    }
}

function fetch_user_by_id(int $uid): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, is_admin, last_seen, active_session, active_session_updated FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_user_by_username(string $username): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_admin, last_seen, active_session, active_session_updated FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function set_session_user(array $user): void {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = ((int)$user['is_admin'] === 1);
}

function clear_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'] ?? false, $params['httponly'] ?? false);
    }
    session_destroy();
}

// 在线判断（DB 内部用 NOW 对比，避免时区问题）
function is_user_online(int $user_id): bool {
    $pdo = get_pdo();
    $sec = (int)ONLINE_WINDOW_SECONDS;
    $sql = "SELECT (last_seen IS NOT NULL AND last_seen >= NOW() - INTERVAL {$sec} SECOND) AS online FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ? ((int)$row['online'] === 1) : false;
}

function active_ban_for_user(int $user_id): ?array {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, user_id, reason, expires_at, created_at FROM bans WHERE user_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$user_id]);
    $ban = $stmt->fetch();
    if (!$ban) return null;
    $seconds = strtotime($ban['expires_at']) - time();
    $days = (int)ceil($seconds / 86400);
    $ban['days_left'] = max($days, 0);
    return $ban;
}

function require_not_banned_or_redirect(): void {
    $uid = current_user_id();
    if (!$uid) return;
    $ban = active_ban_for_user($uid);
    if ($ban) {
        header('Location: banned.php');
        exit;
    }
}

// 加固后的“单设备活跃会话判断”
// 只要满足以下任一，就视为已有活跃会话：
// 1) active_session 不为空 且 active_session_updated 在窗口内
// 2) last_seen 在窗口内（兜底：即便 token 丢失，也不放行）
function user_has_live_active_session(int $user_id): bool {
    $pdo = get_pdo();
    $sec = (int)ONLINE_WINDOW_SECONDS;
    $sql = "
        SELECT (
            (active_session IS NOT NULL AND active_session_updated IS NOT NULL
                AND active_session_updated >= NOW() - INTERVAL {$sec} SECOND)
            OR
            (last_seen IS NOT NULL AND last_seen >= NOW() - INTERVAL {$sec} SECOND)
        ) AS alive
        FROM users WHERE id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ? ((int)$row['alive'] === 1) : false;
}

// 登录成功后登记活跃会话 token（并刷新 last_seen）
function set_active_session(int $user_id, string $token): void {
    $_SESSION['session_token'] = $token;
    $pdo = get_pdo();
    $pdo->prepare('UPDATE users SET active_session = ?, active_session_updated = NOW(), last_seen = NOW() WHERE id = ?')
        ->execute([$token, $user_id]);
}

// 登出/离开时，仅当 token 匹配才清除（避免误清他端）
function clear_active_session_if_matches(int $user_id, ?string $token): void {
    if (!$token) return;
    $pdo = get_pdo();
    $pdo->prepare('UPDATE users SET active_session = NULL, active_session_updated = NULL, last_seen = NULL WHERE id = ? AND active_session = ?')
        ->execute([$user_id, $token]);
}