<?php
// index.php - 带跳转动画与登录态文案的首页
require_once __DIR__ . '/functions.php';

// 只用 Session 判断是否登录；封禁检测尽量温和处理（失败则忽略，避免偶发连接数限制）
$uid = current_user_id();
$username = current_username();
$isLoggedIn = $uid !== null;

$isBanned = false;
if ($isLoggedIn) {
    try {
        $ban = active_ban_for_user((int)$uid);
        $isBanned = $ban ? true : false;
    } catch (Throwable $e) {
        // 数据库繁忙或不可用时，不在首页阻断流程
        $isBanned = false;
    }
}

// 目标页
$target = !$isLoggedIn ? 'login.php' : ($isBanned ? 'banned.php' : 'room.php');

// 供前端使用的变量
$jsUser = $isLoggedIn ? ($username ?? '未知用户') : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>ChatRoom · 登录态分析</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<style>
  /* 极简加载页样式与动画（不依赖外部资源） */
  * { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0; padding: 0; display: flex; align-items: center; justify-content: center;
    background: #f5f5f5; color: #111; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
  }
  .card {
    width: min(560px, 92vw);
    background: #fff; border: 1px solid #eee; border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,.06); padding: 24px 20px;
  }
  .title { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; color: #111; }
  .title .badge { background: #1AAD19; color: #fff; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
  .row { display: flex; align-items: center; gap: 14px; margin-top: 16px; }
  .spinner {
    width: 22px; height: 22px; border-radius: 50%;
    border: 3px solid #e6f4ea; border-top-color: #1AAD19; animation: spin .9s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  .text { font-size: 15px; color: #333; }
  .dim { color: #666; }
  .ok { color: #1a7f1a; }
  .warn { color: #d46b08; }
  .muted { color: #888; font-size: 13px; margin-top: 10px; }
  .tip  { margin-top: 12px; font-size: 13px; color: #555; background: #f6ffed; border: 1px solid #b7eb8f; border-radius: 8px; padding: 8px 10px; }
  .footer { margin-top: 16px; display: flex; gap: 10px; }
  .btn {
    border: 0; background: #1AAD19; color: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 14px;
  }
  .btn.secondary { background: #e6f4ea; color: #1AAD19; }
  .btn:disabled { opacity: .6; cursor: default; }
  .small { font-size: 12px; color: #999; margin-top: 8px; }
</style>
<noscript>
  <meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($target, ENT_QUOTES); ?>">
</noscript>
</head>
<body>
  <div class="card">
    <div class="title">
      <div class="badge">ChatRoom</div>
      <div>欢迎访问</div>
    </div>

    <div class="row">
      <div class="spinner" aria-hidden="true"></div>
      <div class="text" id="line1">您正在访问 ChatRoom，正在分析您当前的登录态…</div>
    </div>

    <div class="row" id="row2" style="opacity:.0; transition: opacity .25s ease;">
      <div class="spinner" aria-hidden="true"></div>
      <div class="text" id="line2" class="dim"></div>
    </div>

    <div class="tip" id="hint" style="display:none;"></div>

    <div class="footer">
      <button class="btn" id="goNow" disabled>立即前往</button>
      <button class="btn secondary" id="stayHere" disabled>停留本页</button>
    </div>
    <div class="small">若长时间未跳转，请点击“立即前往”。</div>
  </div>

<script>
  // 由后端注入的状态
  const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
  const isBanned   = <?php echo $isBanned ? 'true' : 'false'; ?>;
  const username   = <?php echo json_encode($jsUser, JSON_UNESCAPED_UNICODE); ?>;
  const target     = <?php echo json_encode($target, JSON_UNESCAPED_SLASHES); ?>;

  const line1 = document.getElementById('line1');
  const line2 = document.getElementById('line2');
  const row2  = document.getElementById('row2');
  const hint  = document.getElementById('hint');
  const btnGo = document.getElementById('goNow');
  const btnStay = document.getElementById('stayHere');

  // 时序：先显示分析中 -> 给出结果 -> 自动跳转
  const t1 = 600;   // 首段“分析中”显示时间
  const t2 = 1200;  // 显示结果后等待跳转
  let redirectTimer = null;

  function setResultAndRedirect() {
    // 结果文案
    let msg = '';
    if (!isLoggedIn) {
      msg = '登录态分析成功！您当前未登录！正在为您重定向至登录页…';
      hint.style.display = 'block';
      hint.textContent = '提示：首次使用请在登录页选择“注册”。';
    } else if (isBanned) {
      msg = '登录态分析成功！检测到您的账号已被封禁，正在为您跳转到封禁说明页…';
      hint.style.display = 'block';
      hint.textContent = '如需申诉，请联系管理员。';
    } else {
      msg = '登录态分析成功！您当前登录账户为：' + username + '！正在为您重定向至聊天室…';
      hint.style.display = 'block';
      hint.textContent = '祝您聊天愉快，文明发言。';
    }
    line2.textContent = msg;
    row2.style.opacity = '1';

    // 启用按钮
    btnGo.disabled = false;
    btnStay.disabled = false;

    // 自动跳转
    redirectTimer = setTimeout(() => {
      window.location.href = target;
    }, t2);
  }

  // 立即前往 / 停留本页
  btnGo.addEventListener('click', () => {
    if (redirectTimer) clearTimeout(redirectTimer);
    window.location.href = target;
  });
  btnStay.addEventListener('click', () => {
    if (redirectTimer) clearTimeout(redirectTimer);
    line2.textContent += '（已取消自动跳转）';
    btnGo.disabled = false;
    btnStay.disabled = true;
  });

  // 启动动效
  setTimeout(setResultAndRedirect, t1);
</script>
</body>
</html>