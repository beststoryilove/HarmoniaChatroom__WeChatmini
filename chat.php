<?php
// chat.php
// 聊天室主页面（目前该页面已废弃，主要是因为infinityfree.com拦截了chat.php，但改个名成room.php又能用了）
require_once __DIR__ . '/functions.php';
require_login();
require_not_banned_or_redirect();

$user = fetch_user_by_id(current_user_id());
if (!$user) {
    clear_session();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>聊天室</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="chat">
<header class="chat-header">
  <div class="title">聊天室</div>
  <div class="me">
    <span class="user"><?php echo h($user['username']); ?><?php echo $user['is_admin'] ? '（管理员）' : ''; ?></span>
    <a class="logout" href="logout.php">退出</a>
  </div>
</header>

<main id="messages" class="messages"></main>

<footer class="composer">
  <div class="hint"><?php if ($user['is_admin']): ?>
    管理员命令：/clearallmsg 或 /ban 用户名 封禁原因 天数
  <?php else: ?>
    文明发言，请勿违反规定
  <?php endif; ?></div>
  <div class="input-row">
    <input id="msgInput" type="text" placeholder="<?php echo $user['is_admin'] ? '支持管理员命令' : '输入消息'; ?>" maxlength="1000" autocomplete="off">
    <button id="sendBtn">发送</button>
  </div>
</footer>

<script>
const currentUser = <?php echo json_encode($user['username']); ?>;
const isAdmin = <?php echo $user['is_admin'] ? 'true' : 'false'; ?>;
let lastId = 0;
let fetching = false;

function esc(s) {
  const div = document.createElement('div');
  div.textContent = s;
  return div.innerHTML;
}

function appendMessage(m) {
  const wrap = document.createElement('div');
  wrap.className = 'msg ' + (m.username === currentUser ? 'mine' : 'their') + (m.is_admin ? ' admin' : '');
  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  const name = (m.username === currentUser) ? '' : ('<div class="name">'+esc(m.username)+(m.is_admin ? '（管理员）' : '')+'</div>');
  bubble.innerHTML = name + '<div class="text">'+esc(m.content)+'</div><div class="time">'+esc(m.time)+'</div>';
  wrap.appendChild(bubble);
  document.getElementById('messages').appendChild(wrap);
}

function scrollToBottom() {
  const box = document.getElementById('messages');
  box.scrollTop = box.scrollHeight;
}

async function fetchMessages() {
  if (fetching) return;
  fetching = true;
  try {
    const res = await fetch('fetch_messages.php?last_id=' + encodeURIComponent(lastId), {cache:'no-store'});
    if (res.status === 403) {
      // Banned
      window.location.href = 'banned.php';
      return;
    }
    const data = await res.json();
    if (Array.isArray(data.messages)) {
      data.messages.forEach(m => {
        appendMessage(m);
        lastId = Math.max(lastId, m.id);
      });
      if (data.messages.length > 0) scrollToBottom();
    }
  } catch (e) {
    // ignore transient errors
  } finally {
    fetching = false;
  }
}

async function sendMessage() {
  const input = document.getElementById('msgInput');
  const content = input.value.trim();
  if (!content) return;
  input.value = '';
  try {
    const res = await fetch('send_message.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'content=' + encodeURIComponent(content)
    });
    if (res.status === 403) {
      window.location.href = 'banned.php';
      return;
    }
    const data = await res.json();
    if (data.error) {
      alert(data.error);
    } else if (data.info) {
      // command feedback for admin
      const info = {id:lastId, username:'系统', is_admin:false, content:data.info, time:new Date().toLocaleString()};
      appendMessage(info);
      scrollToBottom();
    }
  } catch (e) {
    // ignore
  }
}

async function heartbeat() {
  try {
    const res = await fetch('update_presence.php', {method: 'POST'});
    if (res.status === 403) {
      window.location.href = 'banned.php';
      return;
    }
  } catch (e) {}
}

document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') sendMessage();
});

fetchMessages();
scrollToBottom();
setInterval(fetchMessages, 2000);
setInterval(heartbeat, 20000);
</script>
</body>
</html>