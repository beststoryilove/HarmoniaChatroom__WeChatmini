<?php
// register.php
// 用户注册页面，目前没有启用验证码等防刷机制，请谨慎公开使用（若只是朋友间使用，建议在管理员后台创建账号然后分发给朋友）
require_once __DIR__ . '/functions.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $err = '请输入用户名和密码';
    } else {
        $ulen = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);
        if ($ulen > 50) {
            $err = '用户名过长';
        } elseif (strtolower($username) === 'admin') {
            $err = '用户名 admin 已被保留';
        } else {
            $pdo = get_pdo();
            $exists = fetch_user_by_username($username);
            if ($exists) {
                $err = '该用户名已被注册';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, $hash]);
                header('Location: login.php?registered=1');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>注册</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: time(); ?>">
</head>
<body class="auth">
<div class="auth-card">
  <h1>注册</h1>
  <?php if ($err): ?><div class="error"><?php echo h($err); ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>用户名</label>
    <input type="text" name="username" maxlength="50" inputmode="text" required>
    <label>密码</label>
    <input type="password" name="password" autocomplete="new-password" required>
    <button type="submit">注册</button>
  </form>
  <div class="links">
    <a href="login.php">已有账号？去登录</a>
  </div>
</div>
</body>
</html>