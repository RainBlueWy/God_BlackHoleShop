<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: index.php");
    exit();
}
if (!isset($_GET['inapp'])) {
    header('Location: app.php?page=admin_products');
    exit;
}

// สร้างตารางหมวดหมู่ถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS main_categories (id INT(11) AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, slug VARCHAR(50) NOT NULL UNIQUE, image VARCHAR(255) DEFAULT NULL, sort_order INT(11) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS sub_categories (id INT(11) AUTO_INCREMENT PRIMARY KEY, main_id INT(11) NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(50) NOT NULL, image VARCHAR(255) DEFAULT NULL, sort_order INT(11) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_main (main_id), UNIQUE KEY uq_main_slug (main_id, slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$col = $conn->query("SHOW COLUMNS FROM products LIKE 'sub_category_id'");
if ($col && $col->num_rows === 0) $conn->query("ALTER TABLE products ADD COLUMN sub_category_id INT(11) DEFAULT NULL AFTER category");

// Fetch all products
$sql = "SELECT * FROM products ORDER BY updated_at DESC, id DESC";
$result = $conn->query($sql);

// หมวดหมู่ย่อย (สำหรับ dropdown ในฟอร์ม)
$sub_categories = [];
$res = $conn->query("SELECT s.id, s.name, s.slug, s.main_id, m.name AS main_name FROM sub_categories s LEFT JOIN main_categories m ON m.id = s.main_id ORDER BY m.sort_order, m.id, s.sort_order, s.id");
if ($res) while ($r = $res->fetch_assoc()) $sub_categories[] = $r;

// หมวดหมู่หลัก (สำหรับ dropdown)
$main_categories = [];
$resm = $conn->query("SELECT id, name, slug FROM main_categories ORDER BY sort_order ASC, id ASC");
if ($resm) while ($r = $resm->fetch_assoc()) $main_categories[] = $r;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Products - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css?v=1.5">
    <?php include 'protection_header.php'; ?>

    <style>
        .admin-container {
            padding-top: 120px;
            padding-bottom: 50px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .product-table th, .product-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .product-table th {
            background: rgba(236, 0, 140, 0.2);
        }
        .product-img-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            overflow-y: auto;
        }
        .modal-content {
            background: #1a1a2e;
            margin: 5% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 90%;
            max-width: 600px;
            border-radius: var(--radius-lg);
            position: relative;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-secondary); }
        .form-control {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            color: white;
        }
        /* ให้ตัวหนังสือใน dropdown/option เป็นสีดำ (กันพื้นหลังขาวอ่านไม่ออก) */
        select.form-control { color: #111; background: rgba(255,255,255,0.92); }
        select.form-control option { color: #111; background: #fff; }
    </style>
</head>
<body>
    <script>
    (function(){var v=null;function c(){var x=new XMLHttpRequest();x.open('GET','version.php?r='+Date.now(),true);x.setRequestHeader('Cache-Control','no-cache');x.onload=function(){var t=(x.responseText||'').trim();if(v!==null&&t!==''&&t!==v)location.reload();if(v===null)v=t;};x.send();}c();setInterval(c,10000);})();
    </script>
    <div class="noise"></div>
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <a href="admin_panel.php" class="logo">
                <div class="logo-icon">⚡</div>
                <span>Admin Panel</span>
            </a>
            <div class="nav-buttons">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="สลับธีม">
                    <span class="sun">☀</span>
                    <span class="moon">☽</span>
                </button>
                <a href="admin_panel.php" class="btn btn-secondary">ย้อนกลับ</a>
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="เมนู">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <span class="nav-end-spacer" aria-hidden="true"></span>
            </div>
        </div>
    </nav>


    <div class="container admin-container">
        <div class="header-actions">
            <h1>จัดการสินค้า (Products)</h1>
            <button onclick="openModal('add')" class="btn btn-primary">➕ เพิ่มสินค้าใหม่</button>
        </div> 

        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: rgba(34, 197, 94, 0.2); color: #86efac; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <?php echo $_SESSION['success'];
    unset($_SESSION['success']); ?>
            </div>
        <?php
endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <?php echo $_SESSION['error'];
    unset($_SESSION['error']); ?>
            </div>
        <?php
endif; ?>

        <table class="product-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><img src="<?php echo htmlspecialchars($row['image']); ?>" class="product-img-thumb" alt="img"></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php
                            $p = $row['price'] ?? '';
                            $sp = isset($row['sale_price']) ? trim($row['sale_price']) : '';
                            if ($sp !== '') {
                                echo '<span style="text-decoration:line-through; color: var(--text-muted);">' . htmlspecialchars($p) . '</span> เหลือ ' . htmlspecialchars($sp);
                            } else {
                                echo htmlspecialchars($p);
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>แก้ไข</button>
                            <form action="admin_action.php?action=delete_product" method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบสินค้า?');">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="background:#ef4444; border:none;">ลบ</button>
                            </form>
                        </td>
                    </tr>
                    <?php
    endwhile; ?>
                <?php
else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">ไม่พบสินค้า (กรุณารัน setup_db.php หรือเพิ่มสินค้า)</td>
                    </tr>
                <?php
endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Form -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">เพิ่มสินค้า</h2>
            <form id="productForm" action="admin_action.php?action=add_product" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="prodId">
                <?php if (isset($_GET['inapp'])): ?><input type="hidden" name="inapp" value="1"><?php endif; ?>
                
                <div class="form-group">
                    <label>ชื่อสินค้า (Name)</label>
                    <input type="text" name="name" id="prodName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>URL Slug (ภาษาอังกฤษ ห้ามซ้ำ เช่น wave1)</label>
                    <input type="text" name="slug" id="prodSlug" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>ราคา (ใส่เลข จะลงท้ายด้วย ฿ อัตโนมัติ)</label>
                    <input type="text" name="price" id="prodPrice" class="form-control" required placeholder="เช่น 99 หรือ ฟรี">
                </div>

                <div class="form-group">
                    <label>ส่วนลด (ลดเหลือบาท - ว่างไว้ = ไม่มีส่วนลด)</label>
                    <input type="text" name="sale_price" id="prodSalePrice" class="form-control" placeholder="เช่น 20 (จะแสดงเป็น ราคาเดิม <del>ขีดทับ</del> เหลือ 20฿)">
                </div>

                <div class="form-group">
                    <label>รับกี่คน (จำนวนคนที่ซื้อได้ด้วยพอยท์ แล้วสินค้าหมด เช่น 4 = ครบ 4 คนแล้วหมด)</label>
                    <input type="number" name="max_slots" id="prodMaxSlots" class="form-control" min="0" value="0" placeholder="0 = ไม่จำกัด">
                </div>

                <div class="form-group">
                    <label>หมวดหมู่ (Category) — ข้อความแสดงใต้ชื่อสินค้า</label>
                    <input type="text" name="category" id="prodCategory" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>หมวดหมู่หลัก (เลือกก่อน)</label>
                    <select id="prodMainCategory" class="form-control">
                        <option value="">— เลือกหมวดหมู่หลัก —</option>
                        <?php foreach ($main_categories as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>หมวดหมู่ย่อย (แสดงในหน้าหมวดหมู่)</label>
                    <select name="sub_category_id" id="prodSubCategory" class="form-control">
                        <option value="">— ไม่ระบุ (แสดงใน "ทั้งหมด") —</option>
                        <?php
                        $cur_main = '';
                        foreach ($sub_categories as $s):
                            if ($s['main_name'] !== $cur_main) {
                                $cur_main = $s['main_name'];
                                echo '<option value="" disabled>── ' . htmlspecialchars($cur_main) . ' ──</option>';
                            }
                        ?>
                        <option value="<?= (int)$s['id'] ?>" data-main-id="<?= (int)$s['main_id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['slug']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>รูปภาพ (อัปโหลดไฟล์ หรือใส่ URL)</label>
                    <input type="file" name="image_file" class="form-control" accept="image/*" style="margin-bottom: 5px;">
                    <input type="text" name="image_url" id="prodImage" class="form-control" placeholder="หรือใส่ลิงก์รูปภาพ (URL)">
                </div>

                <div class="form-group">
                    <label>รายละเอียด (Description)</label>
                    <textarea name="description" id="prodDesc" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>ฟีเจอร์ (ขึ้นบรรทัดใหม่ 1 บรรทัดต่อ 1 ฟีเจอร์)</label>
                    <textarea name="features" id="prodFeatures" class="form-control" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label>Server Vip</label>
                    <textarea name="script_content" id="prodScript" class="form-control" rows="3" placeholder="ใส่ลิงก์ Server Vip หรือข้อความที่ต้องการแสดงในหน้าสินค้า"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">บันทึก</button>
            </form>
            <script>
            (function(){
                var f = document.getElementById('productForm');
                var maxSlotsEl = document.getElementById('prodMaxSlots');
                if (f && maxSlotsEl) f.addEventListener('submit', function() {
                    var v = maxSlotsEl.value;
                    if (v === '' || isNaN(parseInt(v, 10))) maxSlotsEl.value = '0';
                });
            })();
            </script>
        </div>
    </div>

    <script>
    (function(){var t='godblackhole-theme',e=document.getElementById('themeToggle');function n(r){r==='light'?document.documentElement.setAttribute('data-theme','light'):document.documentElement.removeAttribute('data-theme');}function o(){return localStorage.getItem(t);}function s(r){localStorage.setItem(t,r);}if(o())n(o());else n(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');if(e)e.addEventListener('click',function(){var r=document.documentElement.getAttribute('data-theme')==='light';r?(s('dark'),n('dark')):(s('light'),n('light'));});})();
    </script>
    <script src="script.js?v=2.0"></script>
    <script>
        const modal = document.getElementById('productModal');

        const form = document.getElementById('productForm');
        const modalTitle = document.getElementById('modalTitle');

        function openModal(mode, data = null) {
            modal.style.display = "block";
            if (mode === 'edit' && data) {
                modalTitle.innerText = "แก้ไขสินค้า";
                form.action = "admin_action.php?action=edit_product";
                document.getElementById('prodId').value = data.id;
                document.getElementById('prodName').value = data.name;
                document.getElementById('prodSlug').value = data.slug;
                document.getElementById('prodPrice').value = data.price;
                var salePrice = (data.sale_price != null && data.sale_price !== '') ? String(data.sale_price).replace(/\s*฿\s*$/g, '').trim() : '';
                document.getElementById('prodSalePrice').value = salePrice;
                document.getElementById('prodMaxSlots').value = (data.max_slots != null && data.max_slots !== '') ? (parseInt(data.max_slots, 10) || 0) : 0;
                document.getElementById('prodCategory').value = data.category;
                // ตั้งค่า main/sub ให้สัมพันธ์กัน
                var subSel = document.getElementById('prodSubCategory');
                var mainSel = document.getElementById('prodMainCategory');
                var subId = (data.sub_category_id != null && data.sub_category_id !== '') ? String(data.sub_category_id) : '';
                if (subId) {
                    var opt = subSel.querySelector('option[value=\"' + subId.replace(/\"/g,'') + '\"]');
                    var mid = opt ? (opt.getAttribute('data-main-id') || '') : '';
                    if (mainSel) mainSel.value = mid;
                } else {
                    if (mainSel) mainSel.value = '';
                }
                filterSubCategories();
                subSel.value = subId;
                document.getElementById('prodImage').value = data.image;
                document.getElementById('prodDesc').value = data.description;
                
                // Parse features JSON to lines
                let features = "";
                try {
                    const featArr = JSON.parse(data.features);
                    if (Array.isArray(featArr)) features = featArr.join('\n');
                } catch(e) {}
                document.getElementById('prodFeatures').value = features;
                document.getElementById('prodScript').value = data.script_content || '';
            } else {
                modalTitle.innerText = "เพิ่มสินค้าใหม่";
                form.action = "admin_action.php?action=add_product";
                form.reset();
                document.getElementById('prodMaxSlots').value = 0;
                document.getElementById('prodSalePrice').value = '';
                document.getElementById('prodSubCategory').value = '';
                document.getElementById('prodMainCategory').value = '';
                filterSubCategories();
            }
        }

        function closeModal() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        function filterSubCategories() {
            var mainSel = document.getElementById('prodMainCategory');
            var subSel = document.getElementById('prodSubCategory');
            if (!mainSel || !subSel) return;
            var mid = mainSel.value;
            var keep = new Set();
            // แสดง option ที่เป็น placeholder เสมอ (value = '' หรือ disabled headers)
            Array.prototype.forEach.call(subSel.options, function(o){
                if (o.disabled) { o.hidden = false; return; }
                if (o.value === '') { o.hidden = false; return; }
                var omid = o.getAttribute('data-main-id') || '';
                o.hidden = (mid !== '' && omid !== mid);
            });
            // ถ้าเลือก sub ที่ไม่ตรง main แล้ว ให้รีเซ็ต
            var cur = subSel.value;
            if (cur && subSel.selectedOptions.length && subSel.selectedOptions[0].hidden) subSel.value = '';
        }
        var mainSelEl = document.getElementById('prodMainCategory');
        if (mainSelEl) mainSelEl.addEventListener('change', filterSubCategories);

        // ราคา: ถ้าใส่เลข ให้ลงท้ายด้วย ฿ เสมอ (ตอน blur)
        const priceInput = document.getElementById('prodPrice');
        if (priceInput) {
            priceInput.addEventListener('blur', function() {
                var v = (this.value || '').trim();
                if (v === '' || v.toLowerCase() === 'ฟรี') return;
                var num = v.replace(/[^\d.]/g, '');
                if (num !== '' && !isNaN(parseFloat(num))) {
                    var base = v.replace(/\s*฿\s*$/g, '');
                    if (!/฿\s*$/.test(base)) this.value = base + '฿';
                }
            });
        }
        const salePriceInput = document.getElementById('prodSalePrice');
        if (salePriceInput) {
            salePriceInput.addEventListener('blur', function() {
                var v = (this.value || '').trim();
                if (v === '') return;
                var num = v.replace(/[^\d.]/g, '');
                if (num !== '' && !isNaN(parseFloat(num))) {
                    var base = v.replace(/\s*฿\s*$/g, '');
                    if (!/฿\s*$/.test(base)) this.value = base + '฿';
                }
            });
        }
    </script>
    <?php /* include 'music_player.php'; */ ?>
</body>
</html>
