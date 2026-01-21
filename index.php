<?php
session_start();
require __DIR__ . '/config.php';

/* ===== 数据库连接 ===== */
try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit('数据库连接失败');
}

/* ===== AuthMe SHA256 密码生成 ===== */
function authme_sha256(string $password): string {
    $salt = bin2hex(random_bytes(8));
    $hash1 = hash('sha256', $password);
    $hash2 = hash('sha256', $hash1 . $salt);
    return '$SHA$' . $salt . '$' . $hash2;
}

/* ===== 处理注册 ===== */
$msg = $_SESSION['msg'] ?? '';
$ok  = $_SESSION['ok'] ?? false;

// 使用完立即清空
unset($_SESSION['msg'], $_SESSION['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $uid       = trim($_POST['uid'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    // 保存用户输入数据，包括确认密码
    $_SESSION['post_data'] = $_POST; // 存储用户输入数据
    $_SESSION['password'] = $password; // 存储密码
    $_SESSION['confirm_password'] = $password2; // 存储确认密码

    $msg = '';
    $ok  = false;

    // 基础验证
    if ($username === '' || $password === '' || $password2 === '' || $uid === '') {
        $msg = '请完整填写必填信息';
    } elseif ($password !== $password2) {
        $msg = '两次密码输入不一致';
    } elseif (!ctype_digit($uid)) {
        $msg = 'UID 格式不正确';
    } elseif (!in_array($uid, $CAPTAIN_UIDS, true)) {
        $msg = '该 UID 未获得舰长授权';
    } else {
        $username_lower = strtolower($username);
        $realname = $username;

        // UID 是否已被使用
        if ($config['uid_single_use']) {
            $stmt = $pdo->prepare("SELECT 1 FROM authme WHERE id = ?");
            $stmt->execute([$uid]);
            if ($stmt->fetch()) {
                $msg = '该 UID 已被使用';
                goto redirect;
            }
        }

        // 用户名是否存在
        $stmt = $pdo->prepare("SELECT 1 FROM authme WHERE username = ?");
        $stmt->execute([$username_lower]);
        if ($stmt->fetch()) {
            $msg = '用户名已存在';
            goto redirect;
        }

        // 生成 AuthMe SHA256 密码
        $authme_pass = authme_sha256($password);

        // 写入数据库
        $stmt = $pdo->prepare("
            INSERT INTO authme (username, realname, password, email, id, regdate)
            VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP())
        ");
        $stmt->execute([
            $username_lower,
            $realname,
            $authme_pass,
            $email ?: null,
            $uid
        ]);

        $ok  = true;
        $msg = "{$username}，你已注册成功！请进入服务器登录";

        // 注册成功后清空输入数据
        unset($_SESSION['post_data']);
        unset($_SESSION['password']);
        unset($_SESSION['confirm_password']); // 清除确认密码
    }

    redirect:
    // PRG：存 session 并跳转
    $_SESSION['msg'] = $msg;
    $_SESSION['ok']  = $ok;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?=$config['site_name']?> - 注册</title>
    <link rel="icon" href="./icon.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="top-btns">
        <a href="/admin">管理后台</a>
    </div>

    <div class="wrap">
        <div class="card">
            <h1><?=$config['site_name']?></h1>
            <div class="desc">舰长专属注册通道</div>

            <?php if ($msg): ?>
                <div class="msg <?=$ok ? 'ok' : 'err'?>">
                    <?=htmlspecialchars($msg)?>
                </div>
            <?php endif; ?>

            <?php if (!$ok): ?>
                <form method="post">
                    <div class="group">
                        <input type="text" name="username" placeholder="游戏用户名" value="<?= htmlspecialchars($_SESSION['post_data']['username'] ?? '') ?>" required>
                    </div>
                    <div class="group">
                        <input type="password" name="password" placeholder="密码" value="<?= htmlspecialchars($_SESSION['password'] ?? '') ?>" required>
                    </div>
                    <div class="group">
                        <input type="password" name="password2" placeholder="确认密码" value="<?= htmlspecialchars($_SESSION['confirm_password'] ?? '') ?>" required>
                    </div>
                    <div class="group">
                        <input type="text" name="uid" placeholder="B站 UID" value="<?= htmlspecialchars($_SESSION['post_data']['uid'] ?? '') ?>" required>
                    </div>
                    <div class="group">
                        <input type="email" name="email" placeholder="邮箱（可选，可用于恢复密码）" value="<?= htmlspecialchars($_SESSION['post_data']['email'] ?? '') ?>">
                    </div>
                    <div class="group"><button>注册</button></div>
                </form>
            <?php endif; ?>

            <div class="footer">
                请上舰之后再来注册
            </div>
        </div>
    </div>
</body>
</html>
