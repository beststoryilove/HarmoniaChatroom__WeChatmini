<?php
require_once __DIR__ . '/functions.php';

// 清理当前用户的 active_session 字段（如果匹配的话）

try {
    $uid = current_user_id();
    $token = current_session_token();
    if ($uid && $token) {
        clear_active_session_if_matches((int)$uid, $token);
    }
} catch (Throwable $e) {
    // ignore
}

clear_session();
header('Location: login.php');