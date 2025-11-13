<?php
require_once __DIR__ . '/functions.php';

// login.php登录页面

$err = '';
$conflict = isset($_GET['conflict']);
if ($conflict) {
    $err = '您已在其他设备登录，本会话已失效，请重新登录。'; // 这个提示目前没有用到，以后考虑移除，因为已经有了驳回请求功能（如下代码）
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $err = '请输入用户名和密码';
    } elseif (strtolower($username) === 'admin') {
        header('Location: admin.php?from=login');
        exit;
    } else {
        $user = fetch_user_by_username($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $err = '用户名或密码错误';
        } else {
            // 单设备限制（加固后判定）
            if (user_has_live_active_session((int)$user['id'])) {
                $err = '已驳回您的登录请求！原因：你当前所尝试登录的用户在线，可能是以下情况导致：1.您所使用的账密被泄露；2.您不是该账号号主，正在尝试登录他人账号（这是不符合规则的！）';
            } else {
                set_session_user($user);
                try {
                    $pdo = get_pdo();
                    $token = bin2hex(random_bytes(16));
                    set_active_session((int)$user['id'], $token); // 同时刷新 last_seen
                } catch (Throwable $e) {}
                header('Location: room.php');
                exit;
            }
        }
    }
}
$registered = isset($_GET['registered']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>登录</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: time(); ?>">
</head>
<body class="auth">
<div class="auth-card">
  <h1>登录</h1>
  <?php if ($registered): ?><div class="ok">注册成功，请登录</div><?php endif; ?>
  <?php if ($err): ?><div class="error"><?php echo h($err); ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <label>用户名</label>
    <input type="text" name="username" inputmode="text" autocomplete="username" required>
    <label>密码</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <button type="submit">登录</button>
  </form>
  <div class="links">
    <a href="register.php">没有账号？去注册</a>
  </div>
</div>
</body>
</html>