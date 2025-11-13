<?php
// 离开页面时的“离线 ping”
require_once __DIR__ . '/functions.php';

$uid = current_user_id();
$token = current_session_token();
if ($uid && $token) {
    try {
        clear_active_session_if_matches((int)$uid, $token);
    } catch (Throwable $e) {
        // ignore
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo 'ok';