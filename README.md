# HarmoniaChatroom__WeChatmini
一个dev是gpt5-codex的聊天室项目，可部署在infinityfree.com所提供的免费空间里，支持管理员/登陆注册/封禁等功能
# 欢迎使用 HarmoniaChatRoom

## 说明

1. 本项目由ChatGPT5-Codex开发
2. 感谢由[Tryallai.com](https://Tryallai.com "Tryallai.com")提供的gpt-5-codex（Codex逆向）

## 快速开始

本文档将会全面的帮助您托管本项目

#### 前提

您需要拥有一个infinityfree的账号，[点我跳转至infinityfree](https://infinityfree.com "点我跳转至infinityfree")

此外，您可能还需要一个良好的网络环境，否则可能在访问过程中出现卡顿等情况

#### 正式开始

您首先需要创建一个托管实例（如下图）

[![创建实例](https://youke1.picui.cn/s1/2025/11/13/6915aeb62f11b.png "创建实例")](https://youke1.picui.cn/s1/2025/11/13/6915aeb62f11b.png "创建实例")

（后续请自行创建，此处因篇幅问题省略）

然后创建完实例以后，点进你所创建的实例

[![进入实例页面](https://youke1.picui.cn/s1/2025/11/13/6915aeb185828.png "进入实例页面")](https://youke1.picui.cn/s1/2025/11/13/6915aeb185828.png "进入实例页面")

接着点击上面的File Manager（若你开了翻译器，则可能显示”文件管理器“等同意词）进入以下页面

[![进入文件管理器](https://youke1.picui.cn/s1/2025/11/13/6915aeb080b61.png "进入文件管理器")](https://youke1.picui.cn/s1/2025/11/13/6915aeb080b61.png "进入文件管理器")

点击“htdocs”，你的个人资产需全部放在这里（如图）

[![进入htdocs](https://youke1.picui.cn/s1/2025/11/13/6915aeb1ead36.png "进入htdocs")](https://youke1.picui.cn/s1/2025/11/13/6915aeb1ead36.png "进入htdocs")

第一次里面是什么都没有的，需要你上传从仓库里的文件压缩包（点击仓库页面的下载，默认下载压缩包），点击Upload，选择Zip & Extract

[![上传压缩包](https://youke1.picui.cn/s1/2025/11/13/6915aeb22db65.png "上传压缩包")](https://youke1.picui.cn/s1/2025/11/13/6915aeb22db65.png "上传压缩包")

接着点击Upload & Extract，自动解压文件并上传

至此，恭喜您成功部署了HarmoniaChatRoom！不过这还不够，您还需要进行以下配置

#### 配置环节

您需要对以下文件的部分内容进行更改：
```javascript
config.php
```
> 您需要更改其中的DB_HOST，DB_NAME，DB_USER，DB_PASS以及默认的admin密码（ADMIN_PASSWORD）

关于一系列DB的更改,您需要先进入Control Panel（见实例图），在该界面的右侧获取到以下信息：MySQL hostname；MySQL username
例子：
1. MySQL hostname:	harmonia.infinityfree.com ——>对应DB_HOST
2. MySQL username:	809blogtest ——>对应DB_USER

剩下的DB_NAME，DB_PASS需要按照以下步骤进行
1. DB_NAME：进入后“MySQL Databases” → Create New Database，创建后会看到一个完整名称（以 你的用户名_ 开头），把它作为DB_NAME
> （2025-11-13控制面板抽风，海内海外都连不上，所以只能文字说明）

2. DB_PASS:如图所示，这个password就是DB_PASS的数值

[![DB_PASS](https://youke1.picui.cn/s1/2025/11/13/6915abfb64dd0.png "DB_PASS")](https://youke1.picui.cn/s1/2025/11/13/6915abfb64dd0.png "DB_PASS")

填完一系列DB后，为了安全起见，您需要更改默认的管理员密码为高强度密码（如下所示）

```javascript
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
define('ADMIN_PASSWORD', 'Aa123456');<——把这个Aa123456改成高强度密码
```

终于！您成功完整的更改了所有配置！现在你就可以使用域名访问您的项目了！

#### 访问项目

对了！您还需要进行以下操作！

您需要访问您的项目域名.xx.xx/init_db.php来初始化一次数据库（仅运行一次即可，运行后请删除或保护本文件以防止滥用）如：

https://809blogtest.page.gd/init_db.php

### 功能说明
1. 管理员指令
```javascript
/clearallmsg （用于清理所有聊天信息）
/ban user 封禁理由 天数（用于封禁违规用户）
```
2. 管理员后台
您可以使用以下功能：
```javascript
管理公告（每次更新公告都会强制推送给登录用户）
添加/删除用户
```

**祝您使用愉快！**
