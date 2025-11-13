<?php
// banned.php
// 用户被封禁时显示的页面
require_once __DIR__ . '/functions.php';
$uid = current_user_id();
$ban = $uid ? active_ban_for_user($uid) : null;
$reason = $ban ? $ban['reason'] : '违反聊天规范';
$days = $ban ? (int)$ban['days_left'] : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>已被封禁</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: time(); ?>">
</head>
<body class="auth">
<div class="auth-card">
  <h1>您已被管理员封禁</h1>
  <div class="tip">封禁时间：<?php echo h((string)$days); ?> 天</div>
  <div class="tip">封禁理由：<?php echo h($reason); ?></div>
  <p class="small" style="margin:10px 0;color:#666;">请注意您的言行，遵守聊天室规则。</p>
  <div class="links">
    <a href="logout.php">退出登录</a>
  </div>
</div>
</body>
</html>