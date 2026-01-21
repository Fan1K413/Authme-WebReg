<?php
session_start();
require __DIR__ . '/../config.php';

/* ===== 数据库 ===== */
$dsn="mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
$pdo=new PDO($dsn,$db['user'],$db['pass'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);

/* ===== 登录校验 ===== */
if(empty($_SESSION['admin_logged'])){
    header("Location:login.php");exit;
}

/* ===== 登出 ===== */
if(isset($_GET['logout'])){
    session_destroy();
    header("Location:login.php");exit;
}

/* ===== 提示信息 ===== */
$msg=$_SESSION['msg']??'';
unset($_SESSION['msg']);

/* ===== 备注 ===== */
$name_file=__DIR__.'/name.php';
$CAPTAIN_REMARKS=file_exists($name_file)?include $name_file:[];

/* ===== AuthMe ===== */
function authme_sha256(string $p):string{
    $salt=bin2hex(random_bytes(8));
    return '$SHA$'.$salt.'$'.hash('sha256',hash('sha256',$p).$salt);
}

/* ===== POST ===== */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $uid=trim($_POST['uid']??'');
    $action=$_POST['action']??'';
    $msg='';

    if($uid!==''){
        switch($action){
            case 'add':
                if(!in_array($uid,$CAPTAIN_UIDS)){
                    $CAPTAIN_UIDS[]=$uid;
                    $msg="UID {$uid} 已添加到舰长列表";
                }else $msg="UID {$uid} 已存在";
                break;

            case 'delete_account':
                $pdo->prepare("DELETE FROM `{$db['name']}` WHERE id=?")->execute([$uid]);
                $msg="UID {$uid} 的账户已删除";
                break;

            case 'remove_captain':
                $pdo->prepare("DELETE FROM `{$db['name']}` WHERE id=?")->execute([$uid]);
                if(($k=array_search($uid,$CAPTAIN_UIDS))!==false) unset($CAPTAIN_UIDS[$k]);
                unset($CAPTAIN_REMARKS[$uid]);
                $msg="UID {$uid} 已移除并注销账户";
                break;

            case 'edit':
                $CAPTAIN_REMARKS[$uid]=trim($_POST['remark']??'');

                $stmt=$pdo->prepare("SELECT * FROM `{$db['name']}` WHERE id=?");
                $stmt->execute([$uid]);
                $user=$stmt->fetch();

                if($user){
                    $fields=[];$params=[];
                    if($_POST['username']!==''){
                        $fields[]='username=?';$params[]=strtolower($_POST['username']);
                        $fields[]='realname=?';$params[]=$_POST['username'];
                    }
                    if($_POST['password']!==''){
                        $fields[]='password=?';$params[]=authme_sha256($_POST['password']);
                    }
                    if($_POST['email']!==''){
                        $fields[]='email=?';$params[]=$_POST['email'];
                    }
                    if($fields){
                        $params[]=$uid;
                        $pdo->prepare("UPDATE `{$db['name']}` SET ".implode(',',$fields)." WHERE id=?")->execute($params);
                        $msg="UID {$uid} 已更新";
                    }else $msg="仅更新备注";
                }
                break;
        }

        file_put_contents(__DIR__.'/../config.php',"<?php\n\$db=".var_export($db,true).";\n\n\$config=".var_export($config,true).";\n\n\$CAPTAIN_UIDS=".var_export($CAPTAIN_UIDS,true).";\n\n\$ADMIN_PASSWORD=".var_export($ADMIN_PASSWORD,true).";\n");
        file_put_contents($name_file,"<?php\nreturn ".var_export($CAPTAIN_REMARKS,true).";\n");
    }

    $_SESSION['msg']=$msg;
    header("Location: ".$_SERVER['PHP_SELF']);exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title><?=$config['site_name']?> 后台</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{font-family:Arial;background:#f5f5f5;padding:20px;}
h2{text-align:center}
.msg{text-align:center;color:#2ecc71;margin-bottom:10px}
.top-btns{position:fixed;top:16px;right:16px;}
.top-btns a{display:inline-block;margin-left:8px;padding:6px 12px;background:#888;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;}
.top-btns a:hover{background:#555;}

/* 表格 */
table{width:100%;max-width:1000px;margin:20px auto;background:#fff;border-radius:8px;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:center}
th{background:#00aeec;color:#fff}

/* 按钮 */
button{border:none;border-radius:4px;padding:6px 10px;cursor:pointer}
.action-btn{background:#00aeec;color:#fff}
.btn-save{background:#2ecc71;color:#fff;width:100%;margin-top:12px}
.btn-danger{background:#e74c3c;color:#fff;width:100%;margin-top:10px}
.btn-danger:disabled{background:#e0e0e0;color:#999;cursor:not-allowed;}


/* 弹窗 */
.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:1000;

    /* 初始状态（隐藏） */
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:opacity .25s ease;
}

.modal.show{
    opacity:1;
    visibility:visible;
    pointer-events:auto;
}

.modal-content{
    background:#fff;
    width:420px;
    max-width:92%;
    border-radius:10px;
    padding:20px;

    /* 动画起点 */
    transform:scale(.92);
    opacity:0;
    transition:all .25s ease;
}

.modal.show .modal-content{
    transform:scale(1);
    opacity:1;
}

}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes scaleIn{from{transform:scale(.9)}to{transform:scale(1)}}

.form-row{display:flex;align-items:center;margin-bottom:10px}
.form-row label{width:80px;color:#555;text-align:right;margin-right:10px}
.form-row input{flex:1;padding:6px;border:1px solid #ccc;border-radius:4px}

.close{float:right;cursor:pointer;color:#999}
</style>

<script>
const biliCache = {};  // uid => username

function openModal(uid){
    let m=document.getElementById('modal_'+uid);
    m.style.display='flex';
    setTimeout(()=>m.classList.add('show'),10);
}
function closeModal(uid){
    let m=document.getElementById('modal_'+uid);
    m.classList.remove('show');
    setTimeout(()=>m.style.display='none',200);
}


function loadBiliName(uid){
    const tableEl = document.getElementById('bili_name_' + uid);
    const modalEl = document.getElementById('modal_bili_name_' + uid);

    // 只要缓存里有，就直接赋值
    if (biliCache[uid]) {
        if (tableEl) tableEl.innerText = biliCache[uid];
        if (modalEl) modalEl.innerText = biliCache[uid];
        return;
    }

    // 表格元素显示加载中
    if (tableEl) tableEl.innerText = '加载中…';
    if (modalEl) modalEl.innerText = '加载中…';

    // 请求 B站用户名
    fetch('get_bili_name.php?uid=' + encodeURIComponent(uid))
        .then(r => r.json())
        .then(d => {
            const name = d.username || '获取失败';
            biliCache[uid] = name;

            if (tableEl) tableEl.innerText = name;
            if (modalEl) modalEl.innerText = name;
        })
        .catch(() => {
            if (tableEl) tableEl.innerText = '获取失败';
            if (modalEl) modalEl.innerText = '获取失败';
        });
}

// 页面加载完成后，初始化所有表格里的 B站用户名
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id^="bili_name_"]').forEach(el => {
        const uid = el.id.replace('bili_name_', '');
        loadBiliName(uid);
    });
});


function openModal(uid){
    document.getElementById('modal_' + uid).classList.add('show');
}


function closeModal(uid){
    document.getElementById('modal_'+uid).classList.remove('show');
}

function confirmAction(uid,type){
    if(confirm("确认执行该危险操作？")){
        document.getElementById(type+'_'+uid).submit();
    }
}

function confirmUIDAction(uid, type){
    const actionName = type === 'disabled' ? '注销账户' : '移除舰长';
    const nameEl = document.getElementById('bili_name_' + uid);
    const biliName = nameEl ? nameEl.innerText : '未知';

    const input = prompt(
        `⚠ 高危操作：${actionName}\n` +
        `当前操作用户UID：${uid}\n` +
        `B站用户名：${biliName}\n` +
        `请输入 UID 以确认操作：`
    );

    if(input === null) return; // 用户取消

    if(input !== uid){
        alert('UID 不匹配，操作已取消');
        return;
    }

    document.getElementById(type + '_' + uid).submit();
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id^="bili_name_"]').forEach(el => {
        const uid = el.id.replace('bili_name_', '');
        loadBiliName(uid);
    });
});

</script>
</head>

<body>

<div class="top-btns">
<a href="?logout=1">登出</a>
<a href="..">返回主界面</a>
</div>

<h2><?=$config['site_name']?> 后台管理</h2>

<?php if(!empty($msg)): ?>
<div class="msg"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<form method="post" style="max-width:400px;margin:20px auto;display:flex;gap:10px;justify-content:center;">
    <input type="text" name="uid" placeholder="舰长 UID" required
        style="flex:1;padding:8px;border-radius:6px;border:1px solid #ccc;box-sizing:border-box;font-size:14px;">
    <button type="submit" name="action" value="add"
        style="padding:8px 16px;border:none;border-radius:6px;background:#00aeec;color:#fff;font-size:14px;cursor:pointer;transition:0.2s;">
        添加舰长
    </button>
</form>

<table>
<tr><th>UID</th><th>用户名(B站)</th><th>用户名(MC)</th><th>备注</th><th>在线状态</th><th>操作</th></tr>

<?php foreach($CAPTAIN_UIDS as $uid):
$stmt=$pdo->prepare("SELECT * FROM `{$db['name']}` WHERE id=?");
$stmt->execute([$uid]);
$user=$stmt->fetch();
?>
<tr>
<td><?=$uid?></td>
<td>
    <span id="bili_name_<?=$uid?>">
        加载中…
    </span>
</td>
<td><?=$user['realname']??'未注册'?></td>
<td><?=htmlspecialchars($CAPTAIN_REMARKS[$uid]??'')?></td>
<td>
<?php
if($user){ // 用户已注册
    if($user['isLogged']==1){
        echo '<span style="color:#2ecc71;font-weight:bold;">在线</span>';
    } elseif(!empty($user['lastlogin'])){
        // 毫秒时间戳转秒
        $ts = intval($user['lastlogin'] / 1000);

        // 获取今天的年月日
        $today = date('Y-m-d');

        // 获取 lastlogin 的年月日
        $loginDay = date('Y-m-d', $ts);

        if($loginDay === $today){
            // 今天 → 显示时:分
            echo date('今天 H:i:s', $ts);
        } else {
            // 其他日期 → 显示完整时间
            echo date('Y-m-d H:i', $ts);
        }
    } else {
        echo '-';
    }
} else {
    echo '-';
}
?>
</td>


<td>
<button class="action-btn" onclick="openModal('<?=$uid?>')">管理</button>


<div class="modal" id="modal_<?=$uid?>">
<div class="modal-content">
<span class="close" onclick="closeModal('<?=$uid?>')">&times;</span>
<h3 style="margin:-12px;">UID <?=$uid?></h3>
<p>B站用户名：<span id="modal_bili_name_<?=$uid?>">加载中…</span></p>


<form method="post">
<input type="hidden" name="uid" value="<?=$uid?>">
<input type="hidden" name="action" value="edit">

<div class="form-row"><label>备注</label><input name="remark" value="<?=htmlspecialchars($CAPTAIN_REMARKS[$uid]??'')?>"></div>
<?php if($user): ?>
<div class="form-row"><label>用户名</label><input name="username" value="<?=htmlspecialchars($user['realname'])?>"></div>
<div class="form-row"><label>密码</label><input type="password" name="password"></div>
<div class="form-row"><label>邮箱</label><input name="email" value="<?=htmlspecialchars($user['email']??'')?>"></div>
<?php endif; ?>

<button class="btn-save">保存修改</button>
</form>

<form id="disabled_<?=$uid?>" method="post" style="margin:0;">
    <input type="hidden" name="uid" value="<?=$uid?>">
    <input type="hidden" name="action" value="delete_account">

    <button type="button"
            class="btn-danger"
            <?php if(!$user): ?>
                disabled
                title="该 UID 尚未注册"
            <?php else: ?>
                onclick="confirmUIDAction('<?=$uid?>','disabled')"
            <?php endif; ?>>
        注销账户
    </button>
</form>



<form id="remove_<?=$uid?>" method="post" style="margin:0;">
    <input type="hidden" name="uid" value="<?=$uid?>">
    <input type="hidden" name="action" value="remove_captain">

    <button type="button"
            class="btn-danger"
            onclick="confirmUIDAction('<?=$uid?>','remove')">
        移除舰长
    </button>
</form>


</div>
</div>
</td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
