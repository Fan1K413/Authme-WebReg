<?php
session_start();
require __DIR__ . '/../config.php';

/* ===== 数据库连接 ===== */
$pdo = new PDO(
    "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}",
    $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

/* ===== 登录校验 ===== */
if (empty($_SESSION['admin_logged'])) {
    header("Location: login.php");
    exit;
}

/* ===== 登出 ===== */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* ===== 提示信息 ===== */
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

/* ===== 备注 ===== */
$name_file = __DIR__ . '/name.php';
$CAPTAIN_REMARKS = file_exists($name_file) ? include $name_file : [];

/* ===== AuthMe 密码生成 ===== */
function authme_sha256(string $p): string {
    $salt = bin2hex(random_bytes(8));
    return '$SHA$' . $salt . '$' . hash('sha256', hash('sha256', $p) . $salt);
}

/* ===== 处理 POST 请求 ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = trim($_POST['uid'] ?? '');
    $action = $_POST['action'] ?? '';
    $msg = '';

    if ($uid !== '') {
        switch ($action) {
            case 'add':
                if (!in_array($uid, $CAPTAIN_UIDS)) {
                    $CAPTAIN_UIDS[] = $uid;
                    $msg = "UID {$uid} 已添加到舰长列表";
                } else {
                    $msg = "UID {$uid} 已存在";
                }
                break;

            case 'delete_account':
                $pdo->prepare("DELETE FROM `{$db['name']}` WHERE id=?")->execute([$uid]);
                $msg = "UID {$uid} 的账户已删除";
                break;

            case 'remove_captain':
                $pdo->prepare("DELETE FROM `{$db['name']}` WHERE id=?")->execute([$uid]);
                if (($k = array_search($uid, $CAPTAIN_UIDS)) !== false) {
                    unset($CAPTAIN_UIDS[$k]);
                }
                unset($CAPTAIN_REMARKS[$uid]);
                $msg = "UID {$uid} 已移除并注销账户";
                break;

            case 'edit':
                $CAPTAIN_REMARKS[$uid] = trim($_POST['remark'] ?? '');
                $stmt = $pdo->prepare("SELECT * FROM `{$db['name']}` WHERE id=?");
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
                if ($user) {
                    $fields = $params = [];
                    if (!empty($_POST['username'])) {
                        $fields[] = 'username=?';
                        $params[] = strtolower($_POST['username']);
                        $fields[] = 'realname=?';
                        $params[] = $_POST['username'];
                    }
                    if (!empty($_POST['password'])) {
                        $fields[] = 'password=?';
                        $params[] = authme_sha256($_POST['password']);
                    }
                    if (!empty($_POST['email'])) {
                        $fields[] = 'email=?';
                        $params[] = $_POST['email'];
                    }
                    if ($fields) {
                        $params[] = $uid;
                        $pdo->prepare("UPDATE `{$db['name']}` SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
                        $msg = "UID {$uid} 已更新";
                    } else {
                        $msg = "仅更新备注";
                    }
                }
                break;
        }

        // 保存配置
        file_put_contents(__DIR__ . '/../config.php', "<?php\n\$db=" . var_export($db, true) . ";\n\$config=" . var_export($config, true) . ";\n\$CAPTAIN_UIDS=" . var_export($CAPTAIN_UIDS, true) . ";\n\$ADMIN_PASSWORD=" . var_export($ADMIN_PASSWORD, true) . ";\n");
        file_put_contents($name_file, "<?php\nreturn " . var_export($CAPTAIN_REMARKS, true) . ";\n");
    }

    $_SESSION['msg'] = $msg;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ===== 获取舰长数据 ===== */
$captains = [];
if (!empty($CAPTAIN_UIDS)) {
    $in = implode(',', array_fill(0, count($CAPTAIN_UIDS), '?'));
    $stmt = $pdo->prepare("SELECT * FROM `{$db['name']}` WHERE id IN ($in)");
    $stmt->execute($CAPTAIN_UIDS);
    foreach ($stmt->fetchAll() as $row) {
        $captains[$row['id']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($config['site_name']) ?> 后台</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../style.css">
    <script src="script.js" defer></script>
</head>
<body>

<div class="top-btns">
    <a href="?logout=1">登出</a>
    <a href="..">返回主界面</a>
</div>

<h2><?= htmlspecialchars($config['site_name']) ?> 后台管理</h2>

<?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="form-inline">
    <div class="form-row" style="flex: 1;padding: 10px 20px;border: none;border-radius: 8px;font-size: 14px;">
        <input type="text" id="searchInput" placeholder="搜索 UID / 用户名 / 备注" oninput="searchTable()">
    </div>
    <div class="form-row">
        <form method="post" style="margin:0;">
            <input type="text" name="uid" placeholder="舰长 UID" required>
            <button type="submit" name="action" value="add">添加舰长</button>
        </form>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>UID</th>
            <th>用户名(B站)</th>
            <th>用户名(MC)</th>
            <th>备注</th>
            <th>在线状态</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($CAPTAIN_UIDS as $uid):
            $user = $captains[$uid] ?? null;
            $status = '-';
            if ($user) {
                if ($user['isLogged'] == 1) {
                    $status = '<span class="status-dot" style="background:#2ecc71"></span>在线';
                } elseif (!empty($user['lastlogin'])) {
                    $ts = intval($user['lastlogin'] / 1000);
                    $today = date('Y-m-d');
                    $loginDay = date('Y-m-d', $ts);
                    $status = ($loginDay === $today) ? '今天 ' . date('H:i:s', $ts) : date('Y-m-d H:i', $ts);
                    $status = '<span class="status-dot" style="background:#00aeec"></span>' . $status;
                }
            }
            ?>
            <tr>
                <td><a href="https://space.bilibili.com/<?= $uid ?>" target="_blank"><?= $uid ?></a></td>
                <td><a id="bili_name_<?= $uid ?>" href="https://space.bilibili.com/<?= $uid ?>" target="_blank">加载中…</a></td>
                <td><?= !empty($user['realname']) ? '<a href="https://zh-cn.namemc.com/profile/' . urlencode($user['realname']) . '" target="_blank">' . htmlspecialchars($user['realname']) . '</a>' : '未注册' ?></td>
                <td><?= htmlspecialchars($CAPTAIN_REMARKS[$uid] ?? '') ?></td>
                <td><?= $status ?></td>
                <td><button onclick="openModal('<?= $uid ?>')">管理</button></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php foreach ($CAPTAIN_UIDS as $uid): 
    $user = $captains[$uid] ?? null;
    include 'modal.php';
endforeach; ?>

</body>
</html>
