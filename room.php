<?php
// room.php
require_once __DIR__ . '/functions.php';
require_login();
// 封禁在 combined_poll.php 中统一拦截，这里不再检查

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
<link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: time(); ?>">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<style>
/* 公告弹窗样式（内联） */
.notice-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.38); z-index: 1000; }
.notice-overlay.open { display: flex; }
.notice-card { width: min(560px, 92vw); background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.18); border: 1px solid #eee; }
.notice-card .hd { padding: 12px 14px; border-bottom: 1px solid #f0f0f0; font-weight: 600; }
.notice-card .bd { padding: 12px 14px; color: #333; white-space: pre-wrap; word-break: break-word; }
.notice-card .ft { padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f0f0f0; gap: 10px; flex-wrap: wrap; }
.notice-card .btn { border: 0; background: #1AAD19; color: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
.notice-card .btn.secondary { background: #e6f4ea; color: #1AAD19; }
.notice-card .chk { display: flex; align-items: center; gap: 6px; color: #666; font-size: 13px; }
</style>
</head>
<body class="chat">
<header class="chat-header">
  <div class="title">聊天室</div>
  <div class="me">
    <span class="user"><?php echo h($user['username']); ?><?php echo $user['is_admin'] ? '（管理员）' : ''; ?></span>
    <span id="onlineBtn" class="online-toggle">在线 <span id="onlineCountHeader">0</span></span>
    <?php if ((int)$user['is_admin'] === 1): ?>
      <a class="logout" href="admin_panel.php" style="background: rgba(255,255,255,.2);">后台</a>
    <?php endif; ?>
    <a class="logout" href="logout.php">退出</a>
  </div>
</header>

<!-- 在线列表抽屉 -->
<div id="onlineOverlay" class="overlay">
  <div class="overlay-backdrop" id="overlayBackdrop"></div>
  <aside class="online-drawer" id="onlinePanel">
    <div class="drawer-header">
      <h3>在线用户 (<span id="onlineCountDrawer">0</span>)</h3>
      <button id="closeOverlay" class="close-btn" aria-label="关闭">×</button>
    </div>
    <div class="drawer-body">
      <ul id="onlineList"></ul>
    </div>
  </aside>
</div>

<!-- 公告弹窗 -->
<div id="noticeOverlay" class="notice-overlay" role="dialog" aria-modal="true" aria-labelledby="noticeTitle">
  <div class="notice-card">
    <div class="hd" id="noticeTitle">公告</div>
    <div class="bd" id="noticeBody">加载中...</div>
    <div class="ft">
      <label class="chk"><input type="checkbox" id="noticeDont"> 不再提示</label>
      <div style="display:flex; gap:8px; align-items:center; margin-left:auto;">
        <a class="btn secondary" id="noticeOpen" href="#" target="_blank" rel="noopener noreferrer" style="display:none;">打开链接</a>
        <button class="btn" id="noticeOk">我知道了</button>
      </div>
    </div>
  </div>
</div>

<main id="messages" class="messages"></main>

<footer class="composer">
  <div class="hint"><?php if ($user['is_admin']): ?>
    管理员命令：/clearallmsg 或 /ban 用户名 封禁原因 天数
  <?php else: ?>
    文明发言，请勿违反规定
  <?php endif; ?></div>
  <div class="input-row">
    <input id="msgInput" type="text" placeholder="<?php echo $user['is_admin'] ? '支持管理员命令' : '输入消息'; ?>" maxlength="1000" autocomplete="off">
    <button id="sendBtn" type="button">发送</button>
  </div>
</footer>

<script>
const currentUser = <?php echo json_encode($user['username']); ?>;
const isAdmin = <?php echo $user['is_admin'] ? 'true' : 'false'; ?>;

// 自适应轮询：突发快轮询 + 可选跳过在线列表
const POLL_FAST = 600;
const POLL_SLOW = 2800;
const POLL_BACKOFF = 12000;
const USER_POLL_EVERY = 3;
const USERS_MIN_INTERVAL = 10000; // 至少每10s查一次在线列表

let lastId = 0;
let fetching = false;
let pollTimer = null;

// 突发与在线节奏控制
let burst = 0;
let pollSeq = 0;
let firstUsers = true;
let lastUsersTs = 0;

// 去重与临时消息
const renderedIds = new Set();
const tempSent = new Map();

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function appendMessage(m) {
  if (m.id && renderedIds.has(m.id)) return;
  const wrap = document.createElement('div');
  if (m.id) { wrap.id = 'msg-' + m.id; renderedIds.add(m.id); }
  if (m._cid) { wrap.dataset.cid = m._cid; }
  wrap.className = 'msg ' + (m.username === currentUser ? 'mine' : 'their') + (m.is_admin ? ' admin' : '') + (m._temp ? ' temp' : '');
  const bubble = document.createElement('div'); bubble.className = 'bubble';
  if (m.username !== currentUser) {
    const name = document.createElement('div'); name.className = 'name';
    name.innerHTML = esc(m.username) + (m.is_admin ? '（管理员）' : '');
    bubble.appendChild(name);
  }
  const text = document.createElement('div'); text.className = 'text'; text.innerHTML = esc(m.content);
  bubble.appendChild(text);
  const time = document.createElement('div'); time.className = 'time'; time.textContent = m.time || new Date().toLocaleString();
  bubble.appendChild(time);
  wrap.appendChild(bubble);
  document.getElementById('messages').appendChild(wrap);
  if (m._cid && m._temp) tempSent.set(m._cid, wrap);
}

function scrollToBottom() {
  const box = document.getElementById('messages');
  box.scrollTop = box.scrollHeight;
}

function schedulePoll(ms) {
  if (pollTimer) clearTimeout(pollTimer);
  pollTimer = setTimeout(poll, ms);
}

async function poll() {
  if (fetching) return;
  fetching = true;
  try {
    // 决定是否查在线列表
    let wantUsers = false;
    const dueByTime = (Date.now() - lastUsersTs) >= USERS_MIN_INTERVAL;

    if (firstUsers) {
      wantUsers = true;
      firstUsers = false;
    } else if (burst > 0 && !dueByTime) {
      wantUsers = false;
      burst--;
    } else if (dueByTime) {
      wantUsers = true;
    } else {
      pollSeq++;
      wantUsers = (pollSeq % USER_POLL_EVERY === 0);
    }

    const url = 'combined_poll.php?last_id=' + encodeURIComponent(lastId) + (wantUsers ? '' : '&no_users=1');
    const res = await fetch(url, {cache:'no-store'});

    if (res.status === 403) { location.href = 'banned.php'; return; }
    if (res.status === 409) { location.href = 'login.php?conflict=1'; return; }

    const text = await res.text();
    let data; try { data = JSON.parse(text); } catch { console.warn('poll non-JSON:', text); schedulePoll(POLL_BACKOFF); return; }
    if (data.error === 'db_busy') { schedulePoll(POLL_BACKOFF); return; }

    // 消息
    let got = 0;
    if (Array.isArray(data.messages)) {
      data.messages.forEach(m => {
        let merged = false;
        if (m.username === currentUser && tempSent.size > 0) {
          for (const [cid, node] of tempSent) {
            const textEl = node.querySelector('.text');
            if (textEl && textEl.textContent === m.content) {
              node.classList.remove('temp'); node.dataset.cid = ''; node.id = 'msg-' + m.id;
              const timeEl = node.querySelector('.time'); if (timeEl && m.time) timeEl.textContent = m.time;
              tempSent.delete(cid); renderedIds.add(m.id);
              merged = true; break;
            }
          }
        }
        if (!merged) appendMessage(m);
        if (m.id) lastId = Math.max(lastId, m.id);
        got++;
      });
      if (got > 0) scrollToBottom();
    }

    // 在线
    if (Array.isArray(data.users)) {
      renderOnline(data.users);
      lastUsersTs = Date.now();
    }

    if (got > 0) {
      burst = Math.max(burst, 5);
      schedulePoll(POLL_FAST);
    } else {
      schedulePoll(POLL_SLOW);
    }
  } catch (e) {
    console.warn('poll error', e);
    schedulePoll(POLL_BACKOFF);
  } finally {
    fetching = false;
  }
}

async function sendMessage() {
  const input = document.getElementById('msgInput');
  const content = input.value.trim();
  if (!content) return;
  input.value = '';

  const cid = 'c' + Date.now() + Math.random().toString(36).slice(2);
  appendMessage({ _cid: cid, _temp: true, username: currentUser, is_admin: isAdmin, content, time: new Date().toLocaleString() });
  scrollToBottom();

  async function tryPost(){ return fetch('send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'content='+encodeURIComponent(content)}); }
  async function tryGet(){ return fetch('send_message.php?content='+encodeURIComponent(content), {cache:'no-store'}); }

  let res;
  try { res = await tryPost(); if (!res.ok) throw new Error('POST '+res.status); }
  catch(e){ try { res = await tryGet(); if (!res.ok) throw new Error('GET '+res.status); }
    catch(e2){ const node = tempSent.get(cid); if (node){ node.style.opacity='0.6'; node.style.filter='grayscale(1)'; const t=node.querySelector('.time'); if (t) t.textContent='发送失败'; tempSent.delete(cid);} alert('发送失败：'+e2.message); return; } }

  const txt = await res.text(); let data; try { data = JSON.parse(txt); } catch {
    const node = tempSent.get(cid); if (node){ node.style.opacity='0.6'; node.style.filter='grayscale(1)'; const t=node.querySelector('.time'); if (t) t.textContent='发送失败'; tempSent.delete(cid);}
    alert('发送返回非JSON：'+txt.slice(0,120)); return;
  }

  if (data.error){
    const node = tempSent.get(cid);
    if (node){ node.style.opacity='0.6'; node.style.filter='grayscale(1)'; const t=node.querySelector('.time'); if (t) t.textContent=data.error; tempSent.delete(cid); }
    alert(data.error);
  } else if (data.info){
    appendMessage({id:lastId, username:'系统', is_admin:false, content:data.info, time:new Date().toLocaleString()});
    scrollToBottom();
    const node = tempSent.get(cid); if (node){ node.remove(); tempSent.delete(cid); }
    burst = Math.max(burst, 5);
    schedulePoll(POLL_FAST);
  } else if (data.ok){
    burst = Math.max(burst, 5);
    schedulePoll(POLL_FAST);
  }
}

// 在线抽屉
const onlineBtn = document.getElementById('onlineBtn');
const overlay = document.getElementById('onlineOverlay');
const backdrop = document.getElementById('overlayBackdrop');
const closeOverlayBtn = document.getElementById('closeOverlay');
const onlineCountHeader = document.getElementById('onlineCountHeader');
const onlineCountDrawer = document.getElementById('onlineCountDrawer');
const onlineList = document.getElementById('onlineList');

function openOverlay(){ overlay.classList.add('open'); }
function closeOverlay(){ overlay.classList.remove('open'); if (!overlay.classList.contains('open')){ overlay.style.display='none'; setTimeout(()=>{overlay.style.display='';},0);} }
if (onlineBtn) onlineBtn.addEventListener('click', openOverlay);
if (backdrop) backdrop.addEventListener('click', closeOverlay);
if (closeOverlayBtn) closeOverlayBtn.addEventListener('click', closeOverlay);
window.addEventListener('load', ()=>{ closeOverlay(); });

// 公告正文链接化（仅 http/https）
function renderLinkified(el, s) {
  while (el.firstChild) el.removeChild(el.firstChild);
  const re = /(https?:\/\/[^\s<>"']+)/gi;
  let last = 0, m;
  while ((m = re.exec(s)) !== null) {
    if (m.index > last) el.appendChild(document.createTextNode(s.slice(last, m.index)));
    const url = m[1];
    const a = document.createElement('a');
    a.href = url; a.textContent = url; a.target = '_blank'; a.rel = 'noopener noreferrer';
    a.style.color = '#1AAD19';
    el.appendChild(a);
    last = re.lastIndex;
  }
  if (last < s.length) el.appendChild(document.createTextNode(s.slice(last)));
}

// 公告弹窗（支持 URL）
async function checkAnnouncement() {
  try {
    const res = await fetch('get_announcement.php', {cache:'no-store'});
    if (!res.ok) return;
    const data = await res.json();
    const text = (data && data.text) ? String(data.text).trim() : '';
    const url  = (data && data.url)  ? String(data.url).trim()  : '';
    const ver  = (data && data.version) ? String(data.version) : '0';
    if (!text) return;

    const seenKey = 'ann_seen_ver';
    const seenVer = localStorage.getItem(seenKey) || '';
    if (seenVer === ver) return;

    const ov = document.getElementById('noticeOverlay');
    const body = document.getElementById('noticeBody');
    const ok = document.getElementById('noticeOk');
    const chk = document.getElementById('noticeDont');
    const openBtn = document.getElementById('noticeOpen');

    renderLinkified(body, text);

    if (url && /^https?:\/\//i.test(url)) {
      openBtn.style.display = '';
      openBtn.href = url;
      openBtn.onclick = () => { if (chk.checked) { try { localStorage.setItem(seenKey, ver); } catch(e){} } };
    } else {
      openBtn.style.display = 'none';
      openBtn.removeAttribute('href');
    }

    ov.classList.add('open');
    ok.onclick = () => {
      if (chk.checked) { try { localStorage.setItem(seenKey, ver); } catch(e){} }
      ov.classList.remove('open');
    };
  } catch (e) {}
}

function renderOnline(users){
  const n = users.length;
  onlineCountHeader.textContent = n;
  onlineCountDrawer.textContent = n;
  onlineList.innerHTML = '';
  users.forEach(u => {
    const li = document.createElement('li');
    const left = document.createElement('span');
    left.className = 'name';
    left.textContent = u.username + (u.is_admin ? '（管理员）' : '') + (u.me ? '（我）' : '');
    li.appendChild(left);

    const right = document.createElement('span'); right.className = 'right';
    const tag = document.createElement('span'); tag.className = 'tag'; tag.textContent = '在线';
    right.appendChild(tag);

    if (isAdmin && !u.is_admin && !u.me) {
      const banBtn = document.createElement('button');
      banBtn.className = 'btn-ban';
      banBtn.textContent = '封禁...';
      banBtn.title = '发起封禁（将预填命令）';
      banBtn.addEventListener('click', () => {
        const reason = prompt('请输入封禁原因：', '违反规定'); if (reason === null) return;
        const days = prompt('封禁天数（整数）：', '1'); if (days === null) return;
        const input = document.getElementById('msgInput');
        input.value = `/ban ${u.username} ${reason} ${parseInt(days,10) || 1}`;
        input.focus(); closeOverlay();
      });
      right.appendChild(banBtn);
    }

    li.appendChild(right);
    onlineList.appendChild(li);
  });
}

// 离开页面尝试释放会话
window.addEventListener('beforeunload', () => {
  try {
    if (navigator.sendBeacon) {
      const blob = new Blob([], {type: 'application/x-www-form-urlencoded'});
      navigator.sendBeacon('offline_ping.php', blob);
    } else if (window.fetch) {
      fetch('offline_ping.php', {method: 'POST', keepalive: true});
    }
  } catch (e) {}
});

// 页面可见性改变：重新可见时触发快轮询
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    burst = Math.max(burst, 5);
    schedulePoll(50);
  }
});

// 启动
poll();
checkAnnouncement();

// 发送事件绑定
document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') sendMessage();
});
</script>
</body>
</html>