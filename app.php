<?php
/**
 * หน้า wrapper สำหรับผู้ใช้ที่ล็อกอิน – เพลงเล่นต่อเมื่อเปลี่ยนหน้า (โหลดเนื้อหาใน iframe)
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['page']) : 'index';
$allowed = ['index', 'profile', 'categories', 'product', 'checkout', 'admin_panel', 'admin_products', 'admin_user_edit', 'admin_categories', 'topup', 'topup_history', 'admin_topups', 'admin_orders'];
if (!in_array($page, $allowed)) $page = 'index';

$iframe_src = $page . '.php?inapp=1';
if ($page === 'product' && !empty($_GET['slug'])) {
    $iframe_src .= '&slug=' . urlencode($_GET['slug']);
}
if ($page === 'admin_user_edit' && !empty($_GET['id'])) {
    $iframe_src .= '&id=' . (int) $_GET['id'];
}
?>
<!DOCTYPE html>
<html lang="th" style="background-color:#0f0f12">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <script>
    (function(){
        var t = localStorage.getItem('godblackhole-theme');
        var isLight = t === 'light';
        document.documentElement.setAttribute('data-theme', isLight ? 'light' : '');
        document.documentElement.style.backgroundColor = isLight ? '#f5f5f8' : '#0f0f12';
    })();
    </script>
    <title>God_BlackHole</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; -webkit-text-size-adjust: 100%; }
        body {
            height: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
        }
        #gbhContent {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            border: none;
        }
    </style>
</head>
<body>
    <script>if (window !== window.top) { window.top.location.href = window.location.href; }</script>
    <iframe name="gbhContent" id="gbhContent" src="<?= htmlspecialchars($iframe_src) ?>"></iframe>
    <?php /* include 'music_player.php'; */ ?>
    <?php include 'chat_panel.php'; ?>
    <script>
    (function() {
        var iframe = document.getElementById('gbhContent');
        if (!iframe) return;
        var allowed = ['index','profile','categories','product','checkout','admin_panel','admin_products','admin_user_edit','admin_categories', 'topup', 'topup_history', 'admin_topups', 'admin_orders'];
        function updateUrl() {
            try {
                var loc = iframe.contentWindow.location;
                if (loc.origin !== window.location.origin) return;
                var base = loc.pathname.split('/').pop() || '';
                var page = base.replace(/\.php$/, '');
                if (allowed.indexOf(page) === -1) return;
                var params = new URLSearchParams(loc.search);
                var q = '?page=' + encodeURIComponent(page);
                if (page === 'product' && params.get('slug')) q += '&slug=' + encodeURIComponent(params.get('slug'));
                if (page === 'admin_user_edit' && params.get('id')) q += '&id=' + encodeURIComponent(params.get('id'));
                var url = window.location.pathname + q;
                if (window.location.search !== q) window.history.replaceState(null, '', url);
            } catch (e) {}
        }
        iframe.addEventListener('load', updateUrl);
        window.addEventListener('message', function(e) {
            if (e.data && e.data.type === 'gbh_open_chat') {
                var btn = document.getElementById('chatFabToggle');
                if (btn) btn.click();
            }
        });
    })();
    </script>
</body>
</html>
