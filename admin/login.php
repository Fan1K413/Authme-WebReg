<?php
session_start();
require __DIR__ . '/../config.php';

/* ===== 已登录直接跳转 dashboard ===== */
if (!empty($_SESSION['admin_logged'])) {
    header('Location: ./');
    exit;
}

$msg = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // 校验密码
    if ($password === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged'] = true;
        header('Location: ./');
        exit;
    } else {
        $msg = '密码错误';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($config['site_name']) ?> - 后台登录</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/style.css">
</head>
<body>

<div class="top-btns">
    <a href="..">返回主界面</a>
</div>

<div class="wrap">
    <div class="card">
        <h1><?= htmlspecialchars($config['site_name']) ?></h1>
        <div class="desc">舰长后台登录</div>

        <?php if ($msg): ?>
            <div class="msg err"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="group">
                <input type="password" name="password" placeholder="管理员密码" required>
            </div>
            <div class="group">
                <button>登录</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
