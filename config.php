<?php
// config.php
// 开发调试开关：问题定位时设为 true，正常运行后改为 false
define('DEBUG', true);

// TODO: 配置您的数据库与管理员密码
define('DB_HOST', 'xxx.infinityfree.com');   // 修改为 InfinityFree 提供的主机
define('DB_NAME', 'xxx_xxxxxxxx_xxxx_');     // 修改为您的数据库名
define('DB_USER', 'xxx_xxxxxxxx');        // 修改为您的数据库用户
define('DB_PASS', 'xxxxxx');   // 修改为您的数据库密码
define('DB_CHARSET', 'utf8mb4');

// 管理员密码（admin.php 使用）——请改成强密码
define('ADMIN_PASSWORD', 'Aa123456');

// 运行参数
define('ONLINE_WINDOW_SECONDS', 60);
define('FETCH_LIMIT', 100);

// 时区
date_default_timezone_set('Asia/Shanghai');

// 调试输出
if (defined('DEBUG') && DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}