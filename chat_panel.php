<?php
// แผงแชทในเว็บ + ปุ่มลอยเปิดแผง — แสดงไอคอนทุกหน้าเมื่อล็อกอินเท่านั้น (ยกเว้นคนที่ไม่ได้ login จะไม่เห็นไอคอน)
if (!isset($_SESSION['user_id'])) return;

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] == 10;
$can_chat = $is_admin || (isset($contact_admin) && is_array($contact_admin) && !empty($contact_admin['id']));

$my_id = (int) $_SESSION['user_id'];
$default_partner_id = 0;
$default_partner_name = '';
if ($is_admin) {
    $default_partner_name = 'เลือกลูกค้า';
} else {
    $default_partner_id = (int) ($contact_admin['id'] ?? 0);
    $default_partner_name = isset($contact_admin['name']) ? $contact_admin['name'] : 'แอดมิน';
}
$chat_icon_path = __DIR__ . '/assets/chat_contact_icon.png';
$use_img_icon = file_exists($chat_icon_path);
$chat_inbox_path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/chat_inbox.php';
?>
<button type="button" class="contact-admin-fab" id="chatFabToggle" title="แชทในเว็บ" aria-label="เปิดแชท">
    <span class="contact-admin-fab-circle<?= $use_img_icon ? ' has-img' : '' ?>">
        <?php if ($use_img_icon): ?>
            <img src="assets/chat_contact_icon.png" alt="" class="contact-admin-fab-icon" width="44" height="44">
        <?php else: ?>
            <svg class="contact-admin-fab-icon" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true">
                <ellipse fill="#fff" cx="9" cy="10" rx="7" ry="8"/>
                <ellipse fill="#fff" cx="16" cy="15" rx="5" ry="5"/>
            </svg>
        <?php endif; ?>
        <span class="chat-fab-badge" id="chatFabBadge" aria-hidden="true" style="display:none;">0</span>
    </span>
    <span class="contact-admin-fab-label"><?= $is_admin ? 'แชทลูกค้า' : 'แชท' ?></span>
</button>
<!-- แจ้งเตือนแบบ Facebook: เด้งว่ามีกี่ข้อความ -->
<div id="chatNotifyPop" class="chat-notify-pop" style="display:none;" role="status">
    <span class="chat-notify-icon">💬</span>
    <span class="chat-notify-text" id="chatNotifyText">คุณมี 0 ข้อความใหม่</span>
</div>

<div id="chatPanel" class="chat-panel" aria-hidden="true" data-no-admin="<?= $can_chat ? '0' : '1' ?>" data-is-admin="<?= $is_admin ? '1' : '0' ?>">
    <!-- มุมมองรายการแชท (แบบ Messenger) -->
    <div id="chatInboxView" class="chat-view">
        <div class="chat-panel-header">
            <h3 class="chat-panel-title">แชท</h3>
            <button type="button" class="chat-panel-close" id="chatPanelClose" aria-label="ปิด">×</button>
        </div>
        <div id="chatInboxAlert" class="chat-inbox-alert" style="display:none;" role="status"></div>
        <div id="chatInboxContent" class="chat-inbox-content">
            <div class="chat-inbox-list" id="chatInboxList"></div>
        </div>
        <div id="chatNoAdminView" class="chat-no-admin-view" style="display:none;">
            <p class="chat-no-admin-text">ยังไม่มีแชท</p>
        </div>
    </div>
    <!-- มุมมองบทสนทนา -->
    <div id="chatThreadView" class="chat-view" style="display:none;">
        <div class="chat-panel-header chat-thread-header">
            <button type="button" class="chat-back-btn" id="chatBackBtn" aria-label="กลับ">←</button>
            <div class="chat-thread-partner">
                <span class="chat-thread-avatar" id="chatThreadAvatar"></span>
                <span class="chat-thread-name" id="chatThreadName"></span>
            </div>
            <?php if ($is_admin): ?>
                <button type="button" class="chat-end-task-btn" id="chatEndTaskBtn" title="จบงาน (ลูกค้าจะไม่เห็นข้อความจนกว่าจะซื้อใหม่)">จบงาน</button>
            <?php endif; ?>
            <?php if (!$is_admin && !empty($contact_admin['url'])): ?>
                <a href="<?= htmlspecialchars($contact_admin['url']) ?>" target="_blank" rel="noopener" class="chat-panel-line-link">เปิด Line</a>
            <?php endif; ?>
        </div>
        <div class="chat-panel-messages" id="chatMessages"></div>
        <form class="chat-panel-form" id="chatForm">
            <input type="hidden" id="chatToUserId" value="<?= $default_partner_id ?>">
            <input type="text" id="chatInput" class="chat-input" placeholder="พิมพ์ข้อความ..." maxlength="2000" autocomplete="off">
            <button type="submit" class="chat-send-btn">ส่ง</button>
        </form>
    </div>
</div>

<!-- โมดัลยืนยันจบงาน (แทน confirm ของเบราว์เซอร์) -->
<div id="chatEndTaskModal" class="chat-modal" aria-hidden="true">
    <div class="chat-modal-backdrop"></div>
    <div class="chat-modal-box">
        <h4 class="chat-modal-title">จบงาน</h4>
        <p class="chat-modal-text">จบงานกับลูกค้านี้หรือไม่? ประวัติแชทจะถูกล้าง และลูกค้าจะไม่เห็นข้อความจนกว่าจะซื้อใหม่</p>
        <div class="chat-modal-actions">
            <button type="button" class="chat-modal-btn chat-modal-cancel" id="chatEndTaskCancel">ยกเลิก</button>
            <button type="button" class="chat-modal-btn chat-modal-confirm" id="chatEndTaskConfirm">ยืนยันจบงาน</button>
        </div>
    </div>
</div>

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
    border: none;
    cursor: pointer;
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
.contact-admin-fab-circle.has-img { background: none; padding: 0; }
.contact-admin-fab-icon { display: block; object-fit: contain; }
.contact-admin-fab-circle.has-img .contact-admin-fab-icon { width: 100%; height: 100%; object-fit: cover; }
.contact-admin-fab-circle { position: relative; }
/* แจ้งเตือนแสดงเฉพาะในรายการแชท (ในแผง) ไม่แสดงบนไอคอน */
.chat-fab-badge {
    display: none !important;
}
.chat-fab-badge--legacy {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 24px;
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
#chatNotifyPop.chat-notify-pop {
    display: none !important;
}
.chat-notify-pop {
    position: fixed;
    bottom: 90px;
    right: 24px;
    z-index: 9997;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    background: #1877f2;
    color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    font-size: 0.95rem;
    font-weight: 500;
    animation: chat-notify-in 0.3s ease;
    cursor: pointer;
}
.chat-notify-pop:hover { background: #166fe5; }
.chat-notify-pop.chat-notify-sent { background: #0d9488; }
.chat-notify-pop.chat-notify-sent:hover { background: #0f766e; }
.chat-notify-icon { font-size: 1.2rem; }
@keyframes chat-notify-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 480px) {
    .contact-admin-fab { padding: 8px 12px 8px 8px; bottom: 20px; right: 20px; }
    .contact-admin-fab-circle { width: 40px; height: 40px; }
    .contact-admin-fab-icon { width: 24px; height: 24px; }
}

.chat-panel {
    position: fixed;
    bottom: 80px;
    right: 24px;
    width: 360px;
    max-width: calc(100vw - 48px);
    height: 70vh;
    max-height: 70vh;
    background: var(--card-bg, #1e293b);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(255,255,255,0.1);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: opacity 0.2s, visibility 0.2s, transform 0.2s;
}
.chat-panel.is-open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.chat-view { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.chat-panel-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}
.chat-thread-header { padding: 10px 12px; }
.chat-back-btn {
    width: 36px; height: 36px; border: none; background: rgba(255,255,255,0.1); color: #fff;
    font-size: 1.1rem; cursor: pointer; border-radius: 50%; margin-right: 4px;
}
.chat-back-btn:hover { background: rgba(255,255,255,0.2); }
.chat-thread-partner { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.chat-thread-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: #334155;
    display: inline-flex; align-items: center; justify-content: center; color: #94a3b8;
    font-weight: 700; font-size: 1rem; flex-shrink: 0; overflow: hidden;
}
.chat-thread-avatar img { width: 100%; height: 100%; object-fit: cover; }
.chat-thread-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-read-label {
    align-self: flex-start;
    font-size: 0.7rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 4px;
    margin-bottom: 4px;
    padding-left: 2px;
}
.chat-panel-title { margin: 0; font-size: 1.1rem; font-weight: 700; flex: 1; }
.chat-end-task-btn {
    padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.5);
    background: rgba(239, 68, 68, 0.15); color: #f87171; font-size: 0.8rem; font-weight: 600; cursor: pointer;
}
.chat-end-task-btn:hover { background: rgba(239, 68, 68, 0.25); }

.chat-modal {
    position: fixed; left: 0; top: 0; right: 0; bottom: 0;
    z-index: 10000; display: flex; align-items: center; justify-content: center;
    opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s;
}
.chat-modal.is-open { opacity: 1; visibility: visible; }
.chat-modal-backdrop {
    position: absolute; left: 0; top: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6); cursor: pointer;
}
.chat-modal-box {
    position: relative; width: 90%; max-width: 360px;
    padding: 1.25rem 1.5rem; border-radius: 16px;
    background: var(--card-bg, #1e293b); border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 12px 40px rgba(0,0,0,0.5);
}
.chat-modal-title { margin: 0 0 0.75rem; font-size: 1.1rem; color: var(--text-primary); }
.chat-modal-text { margin: 0 0 1.25rem; font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; }
.chat-modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.chat-modal-btn { padding: 8px 16px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border: none; }
.chat-modal-cancel { background: rgba(255,255,255,0.1); color: var(--text-primary); }
.chat-modal-cancel:hover { background: rgba(255,255,255,0.15); }
.chat-modal-confirm { background: #dc2626; color: #fff; }
.chat-modal-confirm:hover { background: #b91c1c; }

.chat-panel-line-link { font-size: 0.8rem; color: #60a5fa; text-decoration: none; white-space: nowrap; }
.chat-panel-line-link:hover { text-decoration: underline; }
.chat-panel-close {
    width: 32px; height: 32px; border: none; background: rgba(255,255,255,0.1);
    color: #fff; font-size: 1.25rem; line-height: 1; cursor: pointer; border-radius: 8px;
}
.chat-panel-close:hover { background: rgba(255,255,255,0.2); }
.chat-inbox-alert {
    padding: 8px 12px; margin: 0 12px 8px; background: #1877f2; color: #fff; border-radius: 8px;
    font-size: 0.85rem; font-weight: 500; flex-shrink: 0;
}
.chat-inbox-content { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.chat-inbox-list {
    flex: 1; overflow-y: auto; min-height: 0;
}
.chat-no-admin-view {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 24px; text-align: center;
}
.chat-no-admin-text { color: var(--text-muted, #94a3b8); font-size: 0.95rem; line-height: 1.5; }
.chat-conv-item {
    display: flex; align-items: center; gap: 12px; padding: 12px 14px;
    cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.06);
    transition: background 0.15s;
}
.chat-conv-item:hover { background: rgba(255,255,255,0.06); }
.chat-conv-avatar {
    width: 52px; height: 52px; border-radius: 50%; background: #334155;
    display: flex; align-items: center; justify-content: center; color: #94a3b8;
    font-weight: 700; font-size: 1.1rem; flex-shrink: 0; overflow: hidden;
}
.chat-conv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.chat-conv-body { flex: 1; min-width: 0; }
.chat-conv-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 2px; }
.chat-conv-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.chat-conv-unread {
    display: inline-flex; align-items: center; justify-content: center;
    margin-left: 8px;
    padding: 2px 10px;
    background: #1877f2;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 12px;
    white-space: nowrap;
}
.chat-conv-snippet {
    font-size: 0.85rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.chat-conv-snippet.from-me { color: #94a3b8; }
.chat-conv-time { font-size: 0.75rem; color: var(--text-muted); }
.chat-panel-messages {
    flex: 1;
    min-height: 200px;
    max-height: 320px;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.chat-msg {
    max-width: 85%;
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    word-break: break-word;
}
.chat-msg.from-me { align-self: flex-end; background: #2563eb; color: #fff; }
.chat-msg.from-them { align-self: flex-start; background: rgba(255,255,255,0.12); color: var(--text-primary); }
.chat-msg-time { font-size: 0.7rem; opacity: 0.8; margin-top: 2px; }
.chat-panel-form {
    display: flex;
    gap: 8px;
    padding: 12px 14px;
    border-top: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}
.chat-input {
    flex: 1;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(0,0,0,0.2);
    color: var(--text-primary);
    font-size: 0.95rem;
}
.chat-send-btn {
    padding: 10px 18px;
    border-radius: 10px;
    border: none;
    background: #2563eb;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
.chat-send-btn:hover { background: #1d4ed8; }
@media (max-width: 480px) {
    .chat-panel { right: 12px; bottom: 72px; width: calc(100vw - 24px); max-height: 60vh; }
}
</style>

<script>
(function() {
    var panel = document.getElementById('chatPanel');
    var toggle = document.getElementById('chatFabToggle');
    var closeBtn = document.getElementById('chatPanelClose');
    var inboxView = document.getElementById('chatInboxView');
    var threadView = document.getElementById('chatThreadView');
    var inboxList = document.getElementById('chatInboxList');
    var backBtn = document.getElementById('chatBackBtn');
    var messagesEl = document.getElementById('chatMessages');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var toUserIdEl = document.getElementById('chatToUserId');
    var threadNameEl = document.getElementById('chatThreadName');
    var threadAvatarEl = document.getElementById('chatThreadAvatar');
    var inboxContent = document.getElementById('chatInboxContent');
    var noAdminView = document.getElementById('chatNoAdminView');
    var myId = <?= (int)$my_id ?>;
    var defaultPartnerId = <?= (int)$default_partner_id ?>;
    var pollTimer = null;
    var inboxPollTimer = null;
    var inboxData = [];
    var hasNoAdmin = panel && panel.getAttribute('data-no-admin') === '1';
    var badgeEl = document.getElementById('chatFabBadge');
    var notifyPop = document.getElementById('chatNotifyPop');
    var notifyText = document.getElementById('chatNotifyText');
    var unreadPollTimer = null;
    var lastUnreadCount = -1;
    var notifyPopTimer = null;
    var CHAT_LAST_OPEN_KEY = 'gbh_chat_last_open';
    var LAST_VIEWED_PARTNER_KEY = 'gbh_last_viewed_partner';
    var LAST_VIEWED_PARTNER_AT_KEY = 'gbh_last_viewed_at';
    var lastViewedPartnerId = 0;
    var lastPanelCloseAt = 0;
    var lastViewedPartnerName = '';
    var hasViewedThreadThisSession = false;
    var lastCloseMarkedRead = false;

    function setChatReadCookie(ms) {
        try {
            var sec = Math.floor((ms || Date.now()) / 1000);
            document.cookie = 'gbh_chat_read_since=' + sec + '; path=/; max-age=2592000; SameSite=Lax';
        } catch (e) {}
    }
    function markChatAsSeen(latestMessageUnix) {
        if (badgeEl) { badgeEl.style.display = 'none'; badgeEl.textContent = '0'; }
        if (notifyPop) notifyPop.style.display = 'none';
        if (notifyPopTimer) { clearTimeout(notifyPopTimer); notifyPopTimer = null; }
        lastUnreadCount = 0;
        var sec = latestMessageUnix && latestMessageUnix > 0 ? Math.floor(latestMessageUnix) : Math.floor(Date.now() / 1000);
        localStorage.setItem(CHAT_LAST_OPEN_KEY, String(sec));
        setChatReadCookie(sec * 1000);
        var url = 'chat_mark_read.php';
        if (latestMessageUnix && latestMessageUnix > 0) url += '?latest=' + Math.floor(latestMessageUnix);
        return fetch(url).then(function(r) { return r.json(); }).catch(function() {});
    }
    function doUnreadFetch(forceZeroAfterSync) {
        if (!badgeEl || !panel) return;
        if (panel.classList.contains('is-open')) return;
        var url = 'chat_unread_count.php';
        try {
            var suppressAt = parseInt(localStorage.getItem('gbh_chat_suppress_at') || '0', 10);
            if (suppressAt > 0) url += '?client_read_since=' + Math.floor(suppressAt / 1000);
        } catch (e) {}
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (panel && panel.classList.contains('is-open')) return;
            var n = parseInt(data.count, 10) || 0;
            if (forceZeroAfterSync && n > 0) n = 0;
            if (n > 0) {
                try {
                    if (localStorage.getItem('gbh_chat_suppress_badge') === '1') {
                        var latestAt = data.latest_unread_at ? parseInt(data.latest_unread_at, 10) : 0;
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
            // ไม่ล้าง flag ตอนได้ count=0 (กัน refresh แล้ว request แรกได้ 0 แล้ว request ถัดไปได้ 3) — ล้างเฉพาะตอนเปิดแผงหรือเมื่อมีข้อความใหม่จริง
            var senderName = (data.sender_name && data.sender_name.trim()) ? data.sender_name.trim() : '';
            // หลังเข้าไปดูแชทแล้วปิด — ไม่ให้แบดจ์โผล่มาเลยจนกว่าจะเปิดแผงอีกครั้ง (แล้วมีข้อความใหม่จริง)
            if (lastCloseMarkedRead && n > 0) n = 0;
            // หลังปิดแผงไม่แสดงแจ้งเตือน 3 วินาที (ให้ mark_read บนเซิร์ฟเวอร์อัปเดตก่อน)
            if (lastPanelCloseAt > 0 && (Date.now() - lastPanelCloseAt) < 3000) n = 0;
            lastUnreadCount = n;
            if (n <= 0) {
                if (badgeEl) { badgeEl.style.display = 'none'; badgeEl.textContent = '0'; }
                if (notifyPop) notifyPop.style.display = 'none';
            } else {
                if (badgeEl) {
                    badgeEl.style.display = 'block';
                    badgeEl.textContent = n > 99 ? '99+' : String(n);
                }
                if (notifyPop) notifyPop.style.display = 'none';
            }
        }).catch(function() {});
    }
    function updateUnreadBadge() {
        if (!badgeEl || !panel) return;
        if (panel.classList.contains('is-open')) {
            if (badgeEl) badgeEl.style.display = 'none';
            if (notifyPop) notifyPop.style.display = 'none';
            return;
        }
        // หลังปิดแผงเรา mark as read แล้วใน closePanel(); ไม่ต้องเรียก markChatAsSeen() ซ้ำตอนโพล
        // เพื่อให้เมื่อแอดมินส่งข้อความมาหาลูกค้า แบดจ์/ป๊อปจะเด้งได้ (ไม่ถูกบังด้วย read_at อัปเดตทุก 2 วินาที)
        doUnreadFetch(false);
    }
    function startUnreadPoll() {
        if (unreadPollTimer) return;
        unreadPollTimer = setInterval(updateUnreadBadge, 2000);
    }
    function stopUnreadPoll() {
        if (unreadPollTimer) { clearInterval(unreadPollTimer); unreadPollTimer = null; }
    }

    if (!panel || !toggle) return;

    function startInboxPoll() {
        stopInboxPoll();
        var ms = (noAdminView && noAdminView.style.display !== 'none') ? 5000 : 12000;
        inboxPollTimer = setInterval(function() { loadInbox(); }, ms);
    }
    function stopInboxPoll() {
        if (inboxPollTimer) { clearInterval(inboxPollTimer); inboxPollTimer = null; }
    }

    function timeAgo(str) {
        if (!str) return '';
        var d = new Date(str.replace(/-/g, '/'));
        var now = new Date();
        var sec = (now - d) / 1000;
        if (sec < 60) return 'เมื่อกี้';
        if (sec < 3600) return Math.floor(sec / 60) + ' นาที';
        if (sec < 86400) return Math.floor(sec / 3600) + ' ชม.';
        if (sec < 604800) return Math.floor(sec / 86400) + ' วัน';
        if (sec < 2592000) return Math.floor(sec / 604800) + ' สัปดาห์';
        if (sec < 31536000) return Math.floor(sec / 2592000) + ' เดือน';
        return Math.floor(sec / 31536000) + ' ปี';
    }

    function openPanel() {
        hasViewedThreadThisSession = false;
        lastCloseMarkedRead = false;
        try {
            localStorage.setItem('gbh_chat_suppress_badge', '1');
            localStorage.setItem('gbh_chat_suppress_at', String(Date.now()));
        } catch (e) {}
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        stopUnreadPoll();
        showInbox();
        if (badgeEl) { badgeEl.style.display = 'none'; }
        if (notifyPop) notifyPop.style.display = 'none';
        markChatAsSeen();
        if (hasNoAdmin) {
            if (inboxContent) inboxContent.style.display = 'none';
            if (noAdminView) noAdminView.style.display = 'flex';
            loadInbox();
        } else {
            if (inboxContent) inboxContent.style.display = 'flex';
            if (noAdminView) noAdminView.style.display = 'none';
            loadInbox();
        }
        startInboxPoll();
    }
    function closePanel() {
        var currentPartnerId = toUserIdEl ? parseInt(toUserIdEl.value, 10) : 0;
        var currentPartnerName = '';
        if (threadView && threadView.style.display !== 'none' && threadNameEl && threadNameEl.textContent) {
            currentPartnerName = threadNameEl.textContent.replace(/^ลูกค้า:\s*/i, '').trim();
            if (currentPartnerId) lastViewedPartnerId = currentPartnerId;
            lastViewedPartnerName = currentPartnerName;
        }
        // ถ้าปิดจากรายการแชท (กดกลับมาแล้วปิด) อย่าล้าง lastViewedPartnerName เพื่อให้ยังซ่อนแบดจ์ได้
        lastPanelCloseAt = Date.now();
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        stopPoll();
        stopInboxPoll();
        var wasInThreadView = threadView && threadView.style.display !== 'none';
        var shouldMarkRead = hasViewedThreadThisSession || wasInThreadView;
        function afterClose() {
            setTimeout(function() { startUnreadPoll(); }, 2000);
            setTimeout(function() { updateUnreadBadge(); }, 3500);
        }
        // ทุกครั้งที่ปิดแผง — บันทึกเวลาปิดไว้ เพื่อไม่ให้แจ้งเตือนโผล่จากข้อความเก่า แจ้งเตือนจะขึ้นเฉพาะเมื่อมีข้อความใหม่จริง (หลังเวลาปิดนี้)
        try {
            localStorage.setItem('gbh_chat_suppress_badge', '1');
            localStorage.setItem('gbh_chat_suppress_at', String(lastPanelCloseAt));
            setChatReadCookie(lastPanelCloseAt);
        } catch (e) {}
        if (shouldMarkRead) {
            lastCloseMarkedRead = true;
            markChatAsSeen().then(function() { afterClose(); });
        } else {
            lastCloseMarkedRead = false;
            afterClose();
        }
        hasViewedThreadThisSession = false;
    }
    function showInbox() {
        if (threadView) threadView.style.display = 'none';
        if (inboxView) inboxView.style.display = 'flex';
    }
    function showThread(partnerId, partnerName, partnerAvatar) {
        lastViewedPartnerId = 0;
        if (inboxAlertEl) { inboxAlertEl.style.display = 'none'; if (inboxAlertTimer) clearTimeout(inboxAlertTimer); inboxAlertTimer = null; }
        stopInboxPoll();
        if (inboxView) inboxView.style.display = 'none';
        if (threadView) threadView.style.display = 'flex';
        if (toUserIdEl) toUserIdEl.value = partnerId;
        var isAdmin = panel && panel.getAttribute('data-is-admin') === '1';
        if (threadNameEl) threadNameEl.textContent = (isAdmin && partnerName ? 'ลูกค้า: ' : '') + (partnerName || 'แชท');
        if (threadAvatarEl) {
            threadAvatarEl.innerHTML = '';
            if (partnerAvatar) {
                var img = document.createElement('img');
                img.src = partnerAvatar + '?t=' + (Date.now());
                img.alt = '';
                threadAvatarEl.appendChild(img);
            } else {
                threadAvatarEl.textContent = (partnerName || '?').charAt(0).toUpperCase();
            }
        }
        loadMessages(partnerId);
        startPoll();
    }
    function startPoll() {
        stopPoll();
        pollTimer = setInterval(function() {
            var toId = toUserIdEl ? parseInt(toUserIdEl.value, 10) : 0;
            if (toId) loadMessages(toId);
        }, 4000);
    }
    function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    toggle.addEventListener('click', function() {
        if (panel.classList.contains('is-open')) closePanel();
        else openPanel();
    });
    if (closeBtn) closeBtn.addEventListener('click', closePanel);
    if (backBtn) backBtn.addEventListener('click', function() {
        var fromPartnerId = toUserIdEl ? parseInt(toUserIdEl.value, 10) : 0;
        if (inboxAlertEl) { inboxAlertEl.style.display = 'none'; if (inboxAlertTimer) clearTimeout(inboxAlertTimer); inboxAlertTimer = null; }
        if (fromPartnerId) {
            lastViewedPartnerId = fromPartnerId;
            try {
                localStorage.setItem(LAST_VIEWED_PARTNER_KEY, String(fromPartnerId));
                localStorage.setItem(LAST_VIEWED_PARTNER_AT_KEY, String(Date.now()));
            } catch (e) {}
        }
        showInbox();
        stopPoll();
        startInboxPoll();
        markChatAsSeen().then(function() {
            if (hasNoAdmin) {
                if (inboxContent) inboxContent.style.display = 'none';
                if (noAdminView) noAdminView.style.display = 'flex';
                loadInbox();
            } else {
                if (inboxContent) inboxContent.style.display = 'flex';
                if (noAdminView) noAdminView.style.display = 'none';
                loadInbox();
            }
        });
    });

    var endTaskBtn = document.getElementById('chatEndTaskBtn');
    var endTaskModal = document.getElementById('chatEndTaskModal');
    var endTaskCancel = document.getElementById('chatEndTaskCancel');
    var endTaskConfirm = document.getElementById('chatEndTaskConfirm');
    var pendingEndTaskCustomerId = 0;

    function openEndTaskModal(customerId) {
        pendingEndTaskCustomerId = customerId;
        if (endTaskModal) {
            endTaskModal.classList.add('is-open');
            endTaskModal.setAttribute('aria-hidden', 'false');
        }
    }
    function closeEndTaskModal() {
        pendingEndTaskCustomerId = 0;
        if (endTaskModal) {
            endTaskModal.classList.remove('is-open');
            endTaskModal.setAttribute('aria-hidden', 'true');
        }
    }
    function doEndTask() {
        var toId = pendingEndTaskCustomerId;
        closeEndTaskModal();
        if (!toId) return;
        var fd = new FormData();
        fd.append('customer_id', toId);
        fetch('chat_end_task.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    stopPoll();
                    showInbox();
                    if (inboxContent) inboxContent.style.display = 'flex';
                    if (noAdminView) noAdminView.style.display = 'none';
                    loadInbox();
                }
            });
    }

    if (endTaskBtn) endTaskBtn.addEventListener('click', function() {
        var toId = toUserIdEl ? parseInt(toUserIdEl.value, 10) : 0;
        if (!toId) return;
        openEndTaskModal(toId);
    });
    if (endTaskCancel) endTaskCancel.addEventListener('click', closeEndTaskModal);
    if (endTaskConfirm) endTaskConfirm.addEventListener('click', doEndTask);
    if (endTaskModal) {
        var backdrop = endTaskModal.querySelector('.chat-modal-backdrop');
        if (backdrop) backdrop.addEventListener('click', closeEndTaskModal);
    }

    var inboxAlertEl = document.getElementById('chatInboxAlert');
    var inboxAlertTimer = null;
    var CHAT_INBOX_URL = <?= json_encode($chat_inbox_path) ?>;
    function getChatInboxUrl(useRelative) {
        if (useRelative) return new URL('chat_inbox.php', window.location.href).href;
        if (CHAT_INBOX_URL.indexOf('http') === 0) return CHAT_INBOX_URL;
        return window.location.origin + (CHAT_INBOX_URL.indexOf('/') === 0 ? '' : '/') + CHAT_INBOX_URL;
    }
    function loadInbox(retry, useRelative) {
        if (typeof useRelative === 'undefined') useRelative = false;
        var url = getChatInboxUrl(useRelative);
        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function(text) {
                var data;
                try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON'); }
                return data;
            })
            .then(function(data) {
                inboxData = data.conversations || [];
                renderInbox(inboxData);
                // ไม่แสดงแถบแจ้งเตือนสำหรับแชทที่เพิ่งกดเข้าไปอ่านแล้ว (lastViewedPartnerId / localStorage)
                function isJustViewed(pid) {
                    if (pid === lastViewedPartnerId) return true;
                    try {
                        var storedId = parseInt(localStorage.getItem(LAST_VIEWED_PARTNER_KEY) || '0', 10);
                        var storedAt = parseInt(localStorage.getItem(LAST_VIEWED_PARTNER_AT_KEY) || '0', 10);
                        return pid === storedId && storedAt > 0 && (Date.now() - storedAt) < 300000;
                    } catch (e) { return false; }
                }
                var firstUnread = inboxData.length && inboxData.find(function(c) {
                    var n = parseInt(c.unread_count, 10) || 0;
                    if (n <= 0) return false;
                    if (isJustViewed(Number(c.partner_id))) return false;
                    return true;
                });
                if (inboxAlertEl && firstUnread) {
                    var alertN = parseInt(firstUnread.unread_count, 10) || 0;
                    inboxAlertEl.textContent = (firstUnread.username || 'มีคน') + ' ส่ง ' + (alertN > 1 ? alertN + ' ข้อความใหม่' : '1 ข้อความใหม่');
                    inboxAlertEl.style.display = 'block';
                    if (inboxAlertTimer) clearTimeout(inboxAlertTimer);
                    inboxAlertTimer = setTimeout(function() {
                        if (inboxAlertEl) inboxAlertEl.style.display = 'none';
                        inboxAlertTimer = null;
                    }, 5000);
                } else if (inboxAlertEl) {
                    inboxAlertEl.style.display = 'none';
                }
                if (inboxData.length > 0 && noAdminView && noAdminView.style.display !== 'none') {
                    noAdminView.style.display = 'none';
                    if (inboxContent) inboxContent.style.display = 'flex';
                    hasNoAdmin = false;
                    startInboxPoll();
                }
            })
            .catch(function(err) {
                if (!retry) {
                    setTimeout(function() { loadInbox(true, false); }, 800);
                    return;
                }
                if (!useRelative) {
                    loadInbox(false, true);
                    return;
                }
                if (inboxList) inboxList.innerHTML = '<div class="chat-conv-item"><div class="chat-conv-body">โหลดไม่สำเร็จ</div><div class="chat-conv-right"><button type="button" class="chat-send-btn" style="padding:4px 10px;font-size:0.8rem;" onclick="loadInbox(false)">ลองใหม่</button></div></div>';
            });
    }
    function renderInbox(list) {
        inboxList.innerHTML = '';
        if (!list || list.length === 0) {
            inboxList.innerHTML = '<div class="chat-conv-item"><div class="chat-conv-body">ยังไม่มีแชท</div></div>';
            return;
        }
        list.forEach(function(c) {
            var item = document.createElement('div');
            item.className = 'chat-conv-item';
            item.dataset.partnerId = c.partner_id;
            item.dataset.partnerName = c.username || '';
            item.dataset.partnerAvatar = c.avatar || '';
            var avatar = document.createElement('div');
            avatar.className = 'chat-conv-avatar';
            if (c.avatar) {
                var img = document.createElement('img');
                img.src = c.avatar + '?t=' + (Date.now());
                img.alt = '';
                avatar.appendChild(img);
            } else {
                avatar.textContent = (c.username || '?').charAt(0).toUpperCase();
            }
            var body = document.createElement('div');
            body.className = 'chat-conv-body';
            var name = document.createElement('div');
            name.className = 'chat-conv-name';
            name.textContent = c.username || 'แชท';
            var unread = parseInt(c.unread_count, 10) || 0;
            var pid = Number(c.partner_id);
            if (pid === lastViewedPartnerId) unread = 0;
            if (unread > 0) {
                try {
                    var storedId = parseInt(localStorage.getItem(LAST_VIEWED_PARTNER_KEY) || '0', 10);
                    var storedAt = parseInt(localStorage.getItem(LAST_VIEWED_PARTNER_AT_KEY) || '0', 10);
                    if (pid === storedId && storedAt > 0 && (Date.now() - storedAt) < 300000) unread = 0;
                } catch (e) {}
            }
            if (unread > 0) {
                var unreadSpan = document.createElement('span');
                unreadSpan.className = 'chat-conv-unread';
                unreadSpan.textContent = (unread > 99 ? '99+' : String(unread)) + ' ข้อความใหม่';
                name.appendChild(document.createTextNode(' '));
                name.appendChild(unreadSpan);
            }
            var snippet = document.createElement('div');
            snippet.className = 'chat-conv-snippet' + (c.last_from_me ? ' from-me' : '');
            snippet.textContent = c.last_message ? (c.last_message.length > 35 ? c.last_message.substring(0, 35) + '...' : c.last_message) : 'เริ่มต้นการสนทนา';
            var time = document.createElement('div');
            time.className = 'chat-conv-time';
            time.textContent = timeAgo(c.last_time);
            var rightCol = document.createElement('div');
            rightCol.className = 'chat-conv-right';
            rightCol.appendChild(time);
            body.appendChild(name);
            body.appendChild(snippet);
            item.appendChild(avatar);
            item.appendChild(body);
            item.appendChild(rightCol);
            item.addEventListener('click', function() {
                showThread(parseInt(this.dataset.partnerId, 10), this.dataset.partnerName, this.dataset.partnerAvatar || null);
            });
            inboxList.appendChild(item);
        });
    }
    function loadMessages(withUserId) {
        if (!withUserId) { messagesEl.innerHTML = ''; return; }
        fetch('chat_get.php?with=' + withUserId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var list = data.messages || [];
                messagesEl.innerHTML = '';
                var latestFromThemUnix = 0;
                list.forEach(function(m) {
                    var isMe = m.from_user_id === myId;
                    if (!isMe && m.created_at) {
                        var t = new Date(m.created_at.replace(' ', 'T')).getTime() / 1000;
                        if (t > latestFromThemUnix) latestFromThemUnix = t;
                    }
                    var div = document.createElement('div');
                    div.className = 'chat-msg ' + (isMe ? 'from-me' : 'from-them');
                    var bodyText = (m.body != null) ? String(m.body).trim() : '';
                    if (bodyText === '' || bodyText === '-') bodyText = '(ไม่มีข้อความ)';
                    div.textContent = bodyText;
                    var time = document.createElement('div');
                    time.className = 'chat-msg-time';
                    time.textContent = m.created_at ? m.created_at.replace('T',' ').substring(0, 16) : '';
                    div.appendChild(time);
                    messagesEl.appendChild(div);
                });
                if (latestFromThemUnix > 0) {
                    var readLabel = document.createElement('div');
                    readLabel.className = 'chat-read-label';
                    readLabel.setAttribute('role', 'status');
                    readLabel.textContent = 'อ่านแล้ว';
                    var children = messagesEl.children;
                    var lastFromThem = null;
                    for (var i = children.length - 1; i >= 0; i--) {
                        if (children[i].classList && children[i].classList.contains('from-them')) {
                            lastFromThem = children[i];
                            break;
                        }
                    }
                    if (lastFromThem)
                        messagesEl.insertBefore(readLabel, lastFromThem.nextSibling);
                    else
                        messagesEl.appendChild(readLabel);
                    try {
                        // ใช้เวลาข้อความล่าสุดจาก server เป็นจุดตัด "อ่านแล้ว" จะได้นับเฉพาะข้อความที่ส่งมาหลังจากนั้น (ไม่รวม 9 ข้อความเก่า)
                        var readUpToMs = latestFromThemUnix * 1000;
                        localStorage.setItem('gbh_chat_suppress_badge', '1');
                        localStorage.setItem('gbh_chat_suppress_at', String(readUpToMs));
                        setChatReadCookie(readUpToMs);
                    } catch (e) {}
                    if (badgeEl) { badgeEl.style.display = 'none'; badgeEl.textContent = '0'; }
                }
                messagesEl.scrollTop = messagesEl.scrollHeight;
                markChatAsSeen(latestFromThemUnix > 0 ? latestFromThemUnix : null);
                hasViewedThreadThisSession = true;
            })
            .catch(function() { messagesEl.innerHTML = '<p class="chat-msg from-them">โหลดไม่สำเร็จ</p>'; });
    }

    form && form.addEventListener('submit', function(e) {
        e.preventDefault();
        var toId = toUserIdEl ? parseInt(toUserIdEl.value, 10) : defaultPartnerId;
        var body = (input && input.value) ? input.value.trim() : '';
        if (!toId || !body) return;
        var fd = new FormData();
        fd.append('to_user_id', toId);
        fd.append('body', body);
        fetch('chat_send.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    input.value = '';
                    loadMessages(toId);
                    markChatAsSeen();
                    if (notifyPop && notifyText) {
                        notifyText.textContent = 'ส่งแล้ว';
                        notifyPop.classList.add('chat-notify-sent');
                        notifyPop.style.display = 'flex';
                        if (notifyPopTimer) clearTimeout(notifyPopTimer);
                        notifyPopTimer = setTimeout(function() {
                            if (notifyPop) { notifyPop.style.display = 'none'; notifyPop.classList.remove('chat-notify-sent'); }
                            notifyPopTimer = null;
                        }, 2000);
                    }
                }
            });
    });

    if (notifyPop && toggle) {
        notifyPop.addEventListener('click', function() {
            if (!panel.classList.contains('is-open')) openPanel();
        });
    }
    try {
        if (localStorage.getItem('gbh_chat_suppress_badge') === '1' && badgeEl) {
            badgeEl.style.display = 'none';
            badgeEl.textContent = '0';
        }
    } catch (e) {}
    updateUnreadBadge();
    startUnreadPoll();
    // อัปเดตซ้ำเร็วหลังโหลด เพื่อให้ลูกค้าเห็นแจ้งเตือนเมื่อแอดมินส่งข้อความมาได้ทัน
    setTimeout(updateUnreadBadge, 500);
})();
</script>
