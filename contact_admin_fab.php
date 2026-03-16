<?php
// ปุ่มลอยมุมขวาล่าง — ลูกค้า: คุยกับแอดมิน | แอดมิน: ไปหน้ารายการสั่งซื้อเพื่อแชทลูกค้า (มีแบดจ์แจ้งเตือนเหมือนแชทในเว็บ)
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 10;
$chat_icon_path = __DIR__ . '/assets/chat_contact_icon.png';
$use_img_icon = file_exists($chat_icon_path);

if ($is_admin) {
    $fab_url = 'app.php?page=admin_orders';
    $fab_title = 'รายการสั่งซื้อ – แชทลูกค้า';
    $fab_label = 'แชทลูกค้า';
} elseif (isset($contact_admin) && is_array($contact_admin) && !empty($contact_admin['url'])) {
    $contact_admin['name'] = isset($contact_admin['name']) ? $contact_admin['name'] : 'แอดมิน';
    $fab_url = $contact_admin['url'];
    $fab_title = 'คุยกับแอดมิน ' . $contact_admin['name'];
    $fab_label = 'คุยกับแอดมิน';
} else {
    return;
}
$show_chat_badge = isset($_SESSION['user_id']);
?>
<a href="<?= htmlspecialchars($fab_url) ?>" <?= $is_admin ? 'target="_top"' : 'target="_blank" rel="noopener"' ?> class="contact-admin-fab" id="contactAdminFab" title="<?= htmlspecialchars($fab_title) ?>" onclick="try{var t=Date.now();localStorage.setItem('gbh_chat_suppress_badge','1');localStorage.setItem('gbh_chat_suppress_at',String(t));document.cookie='gbh_chat_read_since='+Math.floor(t/1000)+'; path=/; max-age=2592000; SameSite=Lax';}catch(e){}">
    <span class="contact-admin-fab-circle<?= $use_img_icon ? ' has-img' : '' ?>">
        <?php if ($use_img_icon): ?>
            <img src="assets/chat_contact_icon.png" alt="" class="contact-admin-fab-icon" width="44" height="44">
        <?php else: ?>
            <svg class="contact-admin-fab-icon" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true">
                <ellipse fill="#fff" cx="9" cy="10" rx="7" ry="8"/>
                <ellipse fill="#fff" cx="16" cy="15" rx="5" ry="5"/>
            </svg>
        <?php endif; ?>
        <?php if ($show_chat_badge): ?>
        <span class="contact-admin-fab-badge" id="contactAdminFabBadge" aria-hidden="true" style="display:none;">0</span>
        <?php endif; ?>
    </span>
    <span class="contact-admin-fab-label"><?= htmlspecialchars($fab_label) ?></span>
</a>
<style>
.contact-admin-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9998;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px 10px 10px;
    background: #fff;
    color: #1e3a5f;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    border-radius: 50px;
    box-shadow: 0 4px 20px rgba(37, 99, 235, 0.35);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.contact-admin-fab:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 24px rgba(37, 99, 235, 0.45);
    color: #1e3a5f;
}
.contact-admin-fab-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.contact-admin-fab-circle.has-img {
    background: none;
    padding: 0;
}
.contact-admin-fab-icon {
    display: block;
    object-fit: contain;
}
.contact-admin-fab-circle.has-img .contact-admin-fab-icon {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.contact-admin-fab-circle { position: relative; }
.contact-admin-fab-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 11px;
    background: #e53935;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    line-height: 22px;
    text-align: center;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(229,57,53,0.6);
}
@media (max-width: 480px) {
    .contact-admin-fab { padding: 8px 12px 8px 8px; bottom: 20px; right: 20px; }
    .contact-admin-fab-circle { width: 40px; height: 40px; }
    .contact-admin-fab-icon { width: 24px; height: 24px; }
    .contact-admin-fab-label { font-size: 0.85rem; }
}
</style>
<?php if ($show_chat_badge): ?>
<script>
(function() {
    var badge = document.getElementById('contactAdminFabBadge');
    if (!badge) return;
    function update() {
        var url = 'chat_unread_count.php';
        try {
            var suppressAt = parseInt(localStorage.getItem('gbh_chat_suppress_at') || '0', 10);
            if (suppressAt > 0) url += '?client_read_since=' + Math.floor(suppressAt / 1000);
        } catch (e) {}
        fetch(url).then(function(r) { return r.json(); }).then(function(d) {
            var n = parseInt(d.count, 10) || 0;
            if (n > 0) {
                try {
                    if (localStorage.getItem('gbh_chat_suppress_badge') === '1') {
                        var latestAt = d.latest_unread_at ? parseInt(d.latest_unread_at, 10) : 0;
                        var suppressAt = parseInt(localStorage.getItem('gbh_chat_suppress_at') || '0', 10);
                        if (latestAt > 0 && suppressAt > 0 && (latestAt * 1000) > suppressAt) {
                            localStorage.removeItem('gbh_chat_suppress_badge');
                            localStorage.removeItem('gbh_chat_suppress_at');
                        } else {
                            n = 0;
                        }
                    }
                } catch (e) {}
            }
            if (n <= 0) {
                badge.style.display = 'none';
                badge.textContent = '0';
            } else {
                badge.style.display = 'block';
                badge.textContent = n > 99 ? '99+' : String(n);
            }
        }).catch(function() {});
    }
    update();
    setInterval(update, 2000);
})();
</script>
<?php endif; ?>
