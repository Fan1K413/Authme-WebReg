const biliCache = {};

// 加载 B 站用户名
function loadBiliName(uid) {
    if (biliCache[uid]) {
        return setText(uid, biliCache[uid]);
    }

    setText(uid, '加载中…');
    
    fetch('get_bili_name.php?uid=' + encodeURIComponent(uid))
        .then(response => response.json())
        .then(data => {
            biliCache[uid] = data.username || '获取失败';
            setText(uid, biliCache[uid]);
        })
        .catch(() => setText(uid, '获取失败'));
}

// 设置用户名或其他文本
function setText(uid, text) {
    const el = document.getElementById('bili_name_' + uid);
    const elModal = document.getElementById('modal_bili_name_' + uid);
    
    if (el) el.innerText = text;
    if (elModal) elModal.innerText = text;
}

// 搜索表格
function searchTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('table tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? '' : 'none';
    });
}

// 打开弹窗
function openModal(uid) {
    const modal = document.getElementById('modal_' + uid);
    modal.classList.add('show');
    
    // 自动调整弹窗大小（对于小屏设备）
    if (window.innerWidth <= 767) {
        modal.querySelector('.modal-content').style.width = '95%';
    }

    // 如果 B 站用户名尚未加载，则加载
    if (!biliCache[uid]) {
        loadBiliName(uid);
    }
}

// 关闭弹窗
function closeModal(uid) {
    document.getElementById('modal_' + uid).classList.remove('show');
}

// 确认高危操作
function confirmUIDAction(uid, type) {
    const actionName = type === 'disabled' ? '注销账户' : '移除舰长';
    const biliName = document.getElementById('bili_name_' + uid)?.innerText || '未知';
    const input = prompt(`⚠ 高危操作：${actionName}\nUID：${uid}\nB站用户名：${biliName}\n请输入 UID 确认操作：`);
    
    if (input === uid) {
        document.getElementById(type + '_' + uid).submit();
    } else {
        alert('UID 不匹配，操作已取消');
    }
}

// 页面加载完成后，自动加载 B 站用户名
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id^="bili_name_"]').forEach(el => {
        const uid = el.id.replace('bili_name_', '');
        loadBiliName(uid);
    });
});
