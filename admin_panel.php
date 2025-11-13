<?php
// admin_panel.php
// 管理后台页面
require_once __DIR__ . '/functions.php';
require_login();

$me = fetch_user_by_id(current_user_id());
if (!$me || (int)$me['is_admin'] !== 1) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$info = '';
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    try {
        $pdo = get_pdo();
        if ($act === 'save_announcement') {
            $text = trim($_POST['announcement'] ?? '');
            $url  = trim($_POST['announcement_url'] ?? '');
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $err = '公告链接必须以 http:// 或 https:// 开头';
            } else {
                // 保存文本与URL（分别更新时间，任一变更都会触发客户端重新弹窗）
                $pdo->prepare("INSERT INTO settings (k, v) VALUES ('announcement_text', ?) ON DUPLICATE KEY UPDATE v=VALUES(v), updated_at=NOW()")->execute([$text]);
                $pdo->prepare("INSERT INTO settings (k, v) VALUES ('announcement_url',  ?) ON DUPLICATE KEY UPDATE v=VALUES(v), updated_at=NOW()")->execute([$url]);
                $info = '公告已保存';
            }
        } elseif ($act === 'add_user') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            if ($username === '' || $password === '') {
                $err = '请填写用户名与密码';
            } elseif ((function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username)) > 50) {
                $err = '用户名过长';
            } elseif (strtolower($username) === 'admin') {
                $err = '用户名 admin 已被保留';
            } else {
                $exists = fetch_user_by_username($username);
                if ($exists) {
                    $err = '该用户名已存在';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, 0)')->execute([$username, $hash]);
                    $info = '用户已添加';
                }
            }
        } elseif ($act === 'delete_user') {
            $uid = (int)($_POST['uid'] ?? 0);
            if ($uid === (int)$me['id']) {
                $err = '不能删除当前登录管理员';
            } else {
                $u = fetch_user_by_id($uid);
                if (!$u) {
                    $err = '用户不存在';
                } elseif ((int)$u['is_admin'] === 1) {
                    $err = '不能删除管理员账号';
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                    $info = '用户已删除';
                }
            }
        }
    } catch (Throwable $e) {
        $err = defined('DEBUG') && DEBUG ? ('操作失败：' . $e->getMessage()) : '操作失败';
    }
}

// 读取公告（转北京时间）
$announcement = '';
$ann_url      = '';
$ann_updated  = '';
try {
    $pdo = get_pdo();
    $rowT = $pdo->query("SELECT v AS text, DATE_FORMAT(CONVERT_TZ(updated_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS updated FROM settings WHERE k='announcement_text'")->fetch();
    $rowU = $pdo->query("SELECT v AS url,  DATE_FORMAT(CONVERT_TZ(updated_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS updated FROM settings WHERE k='announcement_url'")->fetch();
    if ($rowT) { $announcement = (string)$rowT['text']; }
    if ($rowU) { $ann_url      = (string)$rowU['url'];  }
    $t1 = $rowT && $rowT['updated'] ? strtotime($rowT['updated']) : 0;
    $t2 = $rowU && $rowU['updated'] ? strtotime($rowU['updated']) : 0;
    $ann_updated = date('Y-%m-%d %H:%i:%s', max($t1, $t2) ?: time());
} catch (Throwable $e) {}

// 用户列表（时间已转北京时间的版本，略去不变部分）
$users = [];
try {
    $sql = "
        SELECT
            u.id,
            u.username,
            u.is_admin,
            DATE_FORMAT(CONVERT_TZ(u.created_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') AS created_at_cst,
            CASE 
              WHEN u.last_seen IS NULL THEN NULL
              ELSE DATE_FORMAT(CONVERT_TZ(u.last_seen, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s')
            END AS last_seen_cst,
            (SELECT COUNT(*) FROM bans b WHERE b.user_id = u.id AND b.expires_at > NOW()) AS banned_count,
            (SELECT b.reason FROM bans b WHERE b.user_id = u.id AND b.expires_at > NOW() ORDER BY b.id DESC LIMIT 1) AS ban_reason,
            (SELECT DATE_FORMAT(CONVERT_TZ(b.expires_at, @@session.time_zone, '+08:00'), '%Y-%m-%d %H:%i:%s') 
               FROM bans b WHERE b.user_id = u.id AND b.expires_at > NOW() ORDER BY b.id DESC LIMIT 1) AS ban_expires_cst
        FROM users u
        ORDER BY u.is_admin DESC, u.username ASC
    ";
    foreach ($pdo->query($sql) as $r) { $users[] = $r; }
} catch (Throwable $e) {}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>管理后台</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/style.css') ?: time(); ?>">
<style>
  body { background: #f5f7fa; }
  .admin-container { max-width: 980px; margin: 20px auto; padding: 0 12px; }
  .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .admin-header .actions a { margin-left: 8px; text-decoration: none; color: #1AAD19; }
  .panel { background: #fff; border: 1px solid #eee; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,.06); margin-bottom: 16px; }
  .panel .hd { padding: 12px 14px; border-bottom: 1px solid #f0f0f0; font-weight: 600; color: #333; }
  .panel .bd { padding: 12px 14px; }
  .msg-ok { background: #f6ffed; border: 1px solid #b7eb8f; color: #237804; padding: 8px 10px; border-radius: 8px; margin-bottom: 10px; }
  .msg-err { background: #fff2f0; border: 1px solid #ffa39e; color: #a8071a; padding: 8px 10px; border-radius: 8px; margin-bottom: 10px; }
  textarea.ann { width: 100%; min-height: 120px; resize: vertical; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; }
  .btn { border: 0; background: #1AAD19; color: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; }
  .btn.light { background: #e6f4ea; color: #1AAD19; }
  .grid { width: 100%; border-collapse: collapse; }
  .grid th, .grid td { padding: 8px 6px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: top; }
  .tag { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid #ddd; color: #555; }
  .tag.admin { color: #1AAD19; border-color: #1AAD19; }
  .tag.banned { color: #cf1322; border-color: #ff7875; background: #fff1f0; }
  .muted { color: #888; font-size: 12px; }
  .form-row { display: flex; gap: 10px; flex-wrap: wrap; }
  .form-row input { padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; }
</style>
</head>
<body>
<div class="admin-container">
  <div class="admin-header">
    <div><strong>管理后台</strong></div>
    <div class="actions">
      <a href="room.php">返回聊天室</a>
      <a href="logout.php">退出登录</a>
    </div>
  </div>

  <?php if ($info): ?><div class="msg-ok"><?php echo h($info); ?></div><?php endif; ?>
  <?php if ($err):  ?><div class="msg-err"><?php echo h($err);  ?></div><?php endif; ?>

  <div class="panel">
    <div class="hd">公告管理</div>
    <div class="bd">
      <form method="post">
        <input type="hidden" name="action" value="save_announcement">
        <label for="ann">公告内容（支持纯文本）</label>
        <textarea id="ann" name="announcement" class="ann" placeholder="输入公告内容..."><?php echo h($announcement); ?></textarea>
        <div style="margin:8px 0;"></div>
        <label for="ann_url">公告链接（可选，需以 http:// 或 https:// 开头）</label>
        <input id="ann_url" type="url" name="announcement_url" placeholder="https://example.com" value="<?php echo h($ann_url); ?>" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;">
        <div class="muted" style="margin:8px 0;">最近更新（北京时间）：<?php echo h($ann_updated ?: '无'); ?></div>
        <button class="btn" type="submit">保存公告</button>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="hd">添加用户</div>
    <div class="bd">
      <form method="post" class="form-row">
        <input type="hidden" name="action" value="add_user">
        <input type="text" name="username" placeholder="用户名" required>
        <input type="password" name="password" placeholder="密码" required>
        <button class="btn" type="submit">添加</button>
      </form>
      <div class="muted" style="margin-top:8px;">注意：不可添加用户名 admin，新增用户为普通用户。</div>
    </div>
  </div>

  <div class="panel">
    <div class="hd">用户列表</div>
    <div class="bd" style="overflow-x:auto;">
      <table class="grid">
        <thead>
          <tr>
            <th>ID</th>
            <th>用户名</th>
            <th>角色</th>
            <th>状态</th>
            <th>最近在线（北京时间）</th>
            <th>创建时间（北京时间）</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?php echo (int)$u['id']; ?></td>
            <td><?php echo h($u['username']); ?></td>
            <td><?php echo ((int)$u['is_admin'] === 1) ? '<span class="tag admin">管理员</span>' : '普通'; ?></td>
            <td>
              <?php if ((int)$u['banned_count'] > 0): ?>
                <span class="tag banned">封禁中</span>
                <div class="muted"><?php echo h((string)$u['ban_reason']); ?> / 至 <?php echo h((string)$u['ban_expires_cst']); ?></div>
              <?php else: ?>
                <span class="tag">正常</span>
              <?php endif; ?>
            </td>
            <td class="muted"><?php echo h((string)($u['last_seen_cst'] ?? '—') ?: '—'); ?></td>
            <td class="muted"><?php echo h((string)$u['created_at_cst']); ?></td>
            <td>
              <?php if ((int)$u['is_admin'] !== 1): ?>
              <form method="post" onsubmit="return confirm('确认删除该用户及其历史消息？此操作不可撤销');" style="display:inline;">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="uid" value="<?php echo (int)$u['id']; ?>">
                <button type="submit" class="btn danger">删除</button>
              </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>