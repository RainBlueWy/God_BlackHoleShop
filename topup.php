<?php
session_start();
require_once 'config.php';

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$is_inapp = isset($_GET['inapp']) ? true : false;
// เบอร์พร้อมเพย์สำหรับรับโอน (แบบแนบสลิป) — ใช้สร้าง QR ผ่าน promptpay.io
$promptpay_phone = '0889546478';
$promptpay_name = 'ปฐมพร แพน้อย'; // ชื่อแสดงด้านล่างเบอร์โทร
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>เติมเงิน - God_BlackHole</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.8">
    <link rel="stylesheet" href="points.css?v=3">
    <?php include 'protection_header.php'; ?>
    <style>
        .topup-container {
            max-width: min(600px, 100%);
            margin: clamp(1rem, 4vw, 2rem) auto clamp(1.5rem, 5vw, 3rem);
            padding: clamp(1rem, 4vw, 2rem);
            padding-left: max(clamp(1rem, 4vw, 2rem), env(safe-area-inset-left));
            padding-right: max(clamp(1rem, 4vw, 2rem), env(safe-area-inset-right));
            background: rgba(25, 25, 25, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: clamp(12px, 3vw, 20px);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        [data-theme="light"] .topup-container {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(0, 0, 0, 0.1);
        }

        .topup-header {
            text-align: center;
            margin-bottom: clamp(1.25rem, 4vw, 2rem);
        }
        .topup-header h2 {
            font-size: clamp(1.35rem, 4vw, 2rem);
            margin-bottom: 0.5rem;
        }
        .topup-header p {
            font-size: clamp(0.9rem, 2vw, 1rem);
            color: var(--text-muted);
        }

        .amount-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: clamp(0.5rem, 2vw, 1rem);
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
        }
        .amount-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text);
            padding: clamp(0.75rem, 2.5vw, 1rem);
            min-height: 48px;
            border-radius: clamp(10px, 2vw, 12px);
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .amount-btn:hover, .amount-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        [data-theme="light"] .amount-btn {
            background: rgba(0,0,0,0.05);
            border-color: rgba(0,0,0,0.1);
        }
        [data-theme="light"] .amount-btn:hover, [data-theme="light"] .amount-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .custom-amount {
            width: 100%;
            max-width: 100%;
            padding: clamp(0.75rem, 2vw, 1rem);
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: clamp(10px, 2vw, 12px);
            color: var(--text);
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            margin-bottom: clamp(1.25rem, 3vw, 2rem);
            font-family: inherit;
            box-sizing: border-box;
        }
        [data-theme="light"] .custom-amount {
            background: rgba(255,255,255,0.8);
            border-color: rgba(0,0,0,0.2);
        }

        .topup-container .btn {
            width: 100%;
            min-height: 48px;
            font-size: clamp(1rem, 2.5vw, 1.1rem);
        }

        .qr-section {
            display: none;
            text-align: center;
            margin-top: clamp(1rem, 4vw, 2rem);
            padding-top: clamp(1rem, 4vw, 2rem);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        [data-theme="light"] .qr-section { border-color: rgba(0,0,0,0.1); }

        .qr-section h3 {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            margin-bottom: 0.5rem;
        }
        .qr-section p {
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        .qr-image {
            background: white;
            padding: clamp(0.5rem, 2vw, 1rem);
            border-radius: clamp(10px, 2vw, 12px);
            display: inline-block;
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
        }
        .qr-image img {
            width: 100%;
            max-width: min(250px, 85vw);
            height: auto;
            aspect-ratio: 1;
            display: block;
        }

        .form-group {
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: clamp(0.9rem, 2vw, 1rem);
        }
        .file-input {
            width: 100%;
            max-width: 100%;
            padding: clamp(0.6rem, 2vw, 0.8rem);
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--text);
            font-size: clamp(0.9rem, 2vw, 1rem);
            box-sizing: border-box;
        }
        [data-theme="light"] .file-input {
            background: rgba(255,255,255,0.8);
            border-color: rgba(0,0,0,0.2);
        }

        .alert-error,
        .alert-success {
            padding: clamp(0.75rem, 2vw, 1rem);
            border-radius: clamp(10px, 2vw, 12px);
            margin-bottom: 1rem;
            font-size: clamp(0.9rem, 2vw, 1rem);
            display: none;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="noise"></div>

    <!-- Navigation -->
    <?php if (!$is_inapp): ?>
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">⚡</div>
                <span>God_BlackHole</span>
            </a>
            <ul class="nav-links">
                <li class="nav-drawer-close-li">
                    <button type="button" class="nav-drawer-close" id="navDrawerClose" aria-label="ปิดเมนู">× ปิดเมนู</button>
                </li>
                <li><a href="index.php">หน้าหลัก</a></li>
                <li><a href="categories.php">หมวดหมู่</a></li>
                <li><a href="topup_history.php">ประวัติเติมเงิน</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 10): ?>
                    <li><a href="admin_panel.php" style="color: #d946ef; font-weight: bold;">จัดการระบบ (Admin)</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-buttons">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับธีม">
                    <span class="sun">☀</span>
                    <span class="moon">☽</span>
                </button>
                <?php if (!empty($contact_admin) && !empty($contact_admin['url'])): ?>
                <a href="<?= htmlspecialchars($contact_admin['url']) ?>" target="_blank" rel="noopener" class="btn" style="background:#eab308;color:#000;font-weight:600;">ติดต่อ <?= htmlspecialchars($contact_admin['name']) ?></a>
                <?php endif; ?>
                <div class="points-display">
                    <span class="points-amount">💰 <?php echo $user_points; ?> พอยท์</span>
                    <a href="topup.php" class="btn-topup">+ เติมเงิน</a>
                </div>
                <a href="profile.php" class="btn btn-primary">
                    <span>👤</span> <span class="btn-text">โปรไฟล์</span>
                </a>
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เปิดเมนู">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </nav>
    <div style="height: 40px;"></div>
    <?php else: ?>
    <!-- Top Padding for in-app iframe -->
    <div style="height: 20px;"></div>
    <?php endif; ?>

    <div class="page-frame">
    <div class="container">
        <div class="topup-container">
            <div class="topup-header">
                <h2>เติมเงิน (PromptPay)</h2>
                <p>เลือกหรือระบุจำนวนเงินที่ต้องการเติม</p>
            </div>

            <div id="errorBox" class="alert-error"></div>
            <div id="successBox" class="alert-success"></div>

            <div id="step1">
                <div class="amount-options">
                    <button class="amount-btn" data-amount="50">50฿</button>
                    <button class="amount-btn" data-amount="90">90฿</button>
                    <button class="amount-btn" data-amount="150">150฿</button>
                    <button class="amount-btn" data-amount="300">300฿</button>
                    <button class="amount-btn" data-amount="500">500฿</button>
                    <button class="amount-btn" data-amount="1000">1000฿</button>
                </div>
                
                <input type="number" id="customAmount" class="custom-amount" placeholder="ระบุจำนวนเงินเอง (ขั้นต่ำ 10 บาท)" min="10">
                
                <button id="btnGenerateQr" class="btn btn-primary" style="width: 100%; height: 50px; font-size: 1.1rem;">
                    แสดง QR Code โอนเงิน
                </button>
            </div>

            <!-- Step 2 แบบแนบสลิป (เดิม) -->
            <div id="step2" class="qr-section">
                <h3>สแกน QR Code ด้วยแอปธนาคาร</h3>
                <p>จำนวนเงิน: <span id="displayAmount" class="gradient-text" style="font-size: 1.5rem; font-weight: bold;">0.00</span> บาท</p>
                <p style="margin-bottom: 0.35rem;">พร้อมเพย์ (เบอร์โทรศัพท์): <strong><?php echo preg_match('/^\d{10}$/', $promptpay_phone) ? substr($promptpay_phone, 0, 3) . '-' . substr($promptpay_phone, 3, 3) . '-' . substr($promptpay_phone, 6, 4) : htmlspecialchars($promptpay_phone); ?></strong></p>
                <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.95rem;">ชื่อ <?= htmlspecialchars($promptpay_name) ?></p>
                <div class="qr-image">
                    <img id="qrImg" src="" alt="PromptPay QR Code">
                </div>
                
                <form id="topupForm">
                    <input type="hidden" id="finalAmount" name="amount" value="0">
                    <div class="form-group">
                        <label for="slip">แนบสลิปโอนเงิน <span style="color:red;">*</span></label>
                        <input type="file" id="slip" name="slip" class="file-input" accept="image/jpeg,image/png,image/jpg" required>
                    </div>
                    
                    <button type="submit" id="btnSubmitForm" class="btn btn-primary" style="width: 100%; height: 50px; font-size: 1.1rem;">
                        แจ้งโอนเงิน
                    </button>
                    <button type="button" id="btnCancel" class="btn btn-secondary" style="width: 100%; height: 50px; font-size: 1.1rem; margin-top: 1rem;">
                        ยกเลิก/เปลี่ยนจำนวนเงิน
                    </button>
                </form>
            </div>
        </div>
    </div>

    </div><!-- .page-frame -->

    <!-- Theme Script -->
    <script>
    (function() {
        var themeKey = 'godblackhole-theme';
        function applyTheme(theme) {
            if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
            else document.documentElement.removeAttribute('data-theme');
        }
        var stored = localStorage.getItem(themeKey);
        if (stored) applyTheme(stored);
        else applyTheme(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    })();
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const amountBtns = document.querySelectorAll('.amount-btn');
        const customAmount = document.getElementById('customAmount');
        const btnGenerateQr = document.getElementById('btnGenerateQr');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const displayAmount = document.getElementById('displayAmount');
        const finalAmount = document.getElementById('finalAmount');
        const qrImg = document.getElementById('qrImg');
        const btnCancel = document.getElementById('btnCancel');
        const topupForm = document.getElementById('topupForm');
        const errorBox = document.getElementById('errorBox');
        const successBox = document.getElementById('successBox');
        const btnSubmitForm = document.getElementById('btnSubmitForm');
        const ppNumber = '<?php echo addslashes(preg_replace('/\D/', '', $promptpay_phone)); ?>';

        let selectedAmount = 0;

        amountBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                amountBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedAmount = parseFloat(btn.dataset.amount);
                customAmount.value = ''; // clear custom amount
            });
        });

        customAmount.addEventListener('input', () => {
            amountBtns.forEach(b => b.classList.remove('active'));
            selectedAmount = parseFloat(customAmount.value) || 0;
        });

        btnGenerateQr.addEventListener('click', () => {
            if (selectedAmount < 10) {
                showError("กรุณาระบุจำนวนเงินอย่างน้อย 10 บาท");
                return;
            }
            errorBox.style.display = 'none';
            finalAmount.value = selectedAmount;
            displayAmount.textContent = selectedAmount.toFixed(2);
            var amountForQr = (selectedAmount % 1 === 0) ? selectedAmount : selectedAmount.toFixed(2);
            qrImg.src = 'topup_qr.php?phone=' + encodeURIComponent(ppNumber) + '&amount=' + encodeURIComponent(amountForQr);
            step1.style.display = 'none';
            step2.style.display = 'block';
        });

        btnCancel.addEventListener('click', () => {
            step2.style.display = 'none';
            step1.style.display = 'block';
            topupForm.reset();
            errorBox.style.display = 'none';
            successBox.style.display = 'none';
        });

        topupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorBox.style.display = 'none';
            successBox.style.display = 'none';
            
            const fileInput = document.getElementById('slip');
            if (!fileInput.files.length) {
                showError("กรุณาแนบสลิปโอนเงิน");
                return;
            }

            btnSubmitForm.disabled = true;
            btnSubmitForm.textContent = 'กำลังส่งข้อมูล...';

            const formData = new FormData(topupForm);

            try {
                const res = await fetch('topup_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    step2.style.display = 'none';
                    step1.style.display = 'block';
                    var msg = data.message;
                    if (data.current_points != null) {
                        msg += ' — ยอดพอยท์ปัจจุบัน: ' + data.current_points + ' พอยท์';
                    }
                    successBox.innerHTML = msg + '<br><br><span style="font-size:0.9rem;">กำลังพาไปหน้ารออนุมัติ...</span><br><a href="<?php echo $is_inapp ? "topup_history.php?inapp=1" : "app.php?page=topup_history"; ?>" class="btn btn-primary" style="display:inline-block;margin-top:8px;">ไปหน้ารออนุมัติเลย</a>';
                    successBox.style.display = 'block';

                    setTimeout(function() {
                        window.location.href = '<?php echo $is_inapp ? "topup_history.php?inapp=1" : "app.php?page=topup_history"; ?>';
                    }, 2500);
                } else {
                    showError(data.message || 'เกิดข้อผิดพลาดในการแจ้งโอนเงิน');
                    btnSubmitForm.disabled = false;
                    btnSubmitForm.textContent = 'แจ้งโอนเงิน';
                }
            } catch (err) {
                console.error(err);
                showError('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
                btnSubmitForm.disabled = false;
                btnSubmitForm.textContent = 'แจ้งโอนเงิน';
            }
        });

        function showError(msg) {
            errorBox.textContent = msg;
            errorBox.style.display = 'block';
        }
    });
    </script>
</body>
</html>
