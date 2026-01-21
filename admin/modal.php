<?php
/**
 * modal.php
 * 每个 UID 的弹窗管理内容
 * 变量依赖：
 *  $uid              - 用户 UID
 *  $user             - 用户信息数组
 *  $CAPTAIN_REMARKS  - 备注数组
 */
?>

<div class="modal" id="modal_<?=$uid?>">
    <div class="modal-content">
        <!-- 关闭按钮 -->
        <span class="close" onclick="closeModal('<?=$uid?>')">&times;</span>
        
        <!-- 弹窗头部 -->
        <div class="modal-header">
            <h3>UID <?=$uid?></h3>
            <p>B站用户名：<span id="modal_bili_name_<?=$uid?>">加载中…</span></p>
        </div>

        <!-- 编辑表单 -->
        <form method="post">
            <input type="hidden" name="uid" value="<?=$uid?>">
            <input type="hidden" name="action" value="edit">

            <!-- 备注输入框 -->
            <div class="form-row">
                <label for="remark_<?=$uid?>">备注</label>
                <input id="remark_<?=$uid?>" name="remark" value="<?=htmlspecialchars($CAPTAIN_REMARKS[$uid] ?? '')?>">
            </div>

            <!-- 用户信息输入框，若用户存在 -->
            <?php if ($user): ?>
                <div class="form-row">
                    <label for="username_<?=$uid?>">用户名</label>
                    <input id="username_<?=$uid?>" name="username" value="<?=htmlspecialchars($user['realname'])?>">
                </div>

                <div class="form-row">
                    <label for="password_<?=$uid?>">密码</label>
                    <input id="password_<?=$uid?>" type="password" name="password">
                </div>

                <div class="form-row">
                    <label for="email_<?=$uid?>">邮箱</label>
                    <input id="email_<?=$uid?>" name="email" value="<?=htmlspecialchars($user['email'] ?? '')?>">
                </div>
            <?php endif; ?>

            <!-- 保存修改按钮 -->
            <button class="btn-save">保存修改</button>
        </form>

        <!-- 注销账户按钮 -->
        <form id="disabled_<?=$uid?>" method="post">
            <input type="hidden" name="uid" value="<?=$uid?>">
            <input type="hidden" name="action" value="delete_account">
            <button type="button" class="btn-danger" <?= !$user ? 'disabled' : '' ?> onclick="confirmUIDAction('<?=$uid?>','disabled')">注销账户</button>
        </form>

        <!-- 移除舰长按钮 -->
        <form id="remove_<?=$uid?>" method="post">
            <input type="hidden" name="uid" value="<?=$uid?>">
            <input type="hidden" name="action" value="remove_captain">
            <button type="button" class="btn-danger" onclick="confirmUIDAction('<?=$uid?>','remove')">移除舰长</button>
        </form>
    </div>
</div>
