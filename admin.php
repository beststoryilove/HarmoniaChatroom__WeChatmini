<?php
require_once __DIR__ . '/functions.php';

// 管理员登录页面

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    if ($password === '') {
        $err = '请输入管理员密码';
    } elseif ($password !== ADMIN_PASSWORD) {
        $err = '管理员密码错误';
    } else {
        $admin = fetch_user_by_username('admin');
        if (!$admin) {
            http_response_code(500);
            echo 'admin 用户不存在，请先运行 init_db.php';
            exit;
        }
        // 单设备限制
        if (user_has_live_active_session((int)$admin['id'])) {
            $err = '已驳回您的登录请求！原因：你当前所尝试登录的用户在线，可能是以下情况导致：1.您所使用的账密被泄露；2.您不是该账号号主，正在尝试登录他人账号（这是不符合规则的！）';
        } else {
            set_session_user($admin);
            try {
                $token = bin2hex(random_bytes(16));
                set_active_session((int)$admin['id'], $token);
            } catch (Throwable $e) {}
            header('Location: room.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>管理员登录</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: time(); ?>">
</head>
<body class="auth">
<div class="auth-card">
  <h1>管理员登录</h1>
  <div class="tip">您正在登录 admin（管理员）账号，请输入密码以验证您的身份。</div>
  <div class="tip small">管理员密码在 config.php 中常量 ADMIN_PASSWORD 位置，请自行修改。</div>
  <?php if ($err): ?><div class="error"><?php echo h($err); ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>管理员密码</label>
    <input type="password" name="password" required>
    <button type="submit">进入聊天室</button>
  </form>
  <div class="links">
    <a href="login.php">返回普通登录</a>
  </div>
</div>
</body>
</html>