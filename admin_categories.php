<?php
session_start();
require_once 'config.php';
require_once 'auth_guard.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] != 10) {
    header("Location: index.php");
    exit;
}
if (!isset($_GET['inapp'])) {
    header('Location: app.php?page=admin_categories&inapp=1');
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS main_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$conn->query("CREATE TABLE IF NOT EXISTS sub_categories (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    main_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_main (main_id),
    UNIQUE KEY uq_main_slug (main_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ให้ทุกหมวดหมู่หลักมีหมวดหมู่ย่อย "ทั้งหมด" เสมอ (รองรับหมวดที่สร้างไปก่อนหน้า)
$resEnsure = $conn->query("SELECT id FROM main_categories");
if ($resEnsure) {
    while ($mr = $resEnsure->fetch_assoc()) {
        $mid = (int)($mr['id'] ?? 0);
        if ($mid > 0) {
            $conn->query("INSERT IGNORE INTO sub_categories (main_id, name, slug, sort_order) VALUES ($mid, 'ทั้งหมด', 'all', 0)");
        }
    }
}

// บันทึกจากฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'add_main') {
        $name = trim($_POST['main_name'] ?? '');
        $slug_in = trim((string)($_POST['main_slug'] ?? ''));
        $slug_base = $slug_in !== '' ? $slug_in : $name;
        $slug = trim(preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug_base)), '-') ?: 'cat-' . time();
        $image = trim((string)($_POST['main_image'] ?? ''));
        $image = $image === '' ? null : $image;
        // รองรับอัปโหลดไฟล์รูป
        if (isset($_FILES['main_image_file']) && $_FILES['main_image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['main_image_file']['tmp_name'];
            $file_name = $_FILES['main_image_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed, true)) {
                $dir = __DIR__ . '/uploads/categories';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $new_name = 'cat_' . uniqid('', true) . '.' . $file_ext;
                $upload_path = $dir . '/' . $new_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $image = 'uploads/categories/' . $new_name;
                }
            }
        }
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO main_categories (name, slug, image, sort_order) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("sss", $name, $slug, $image);
            $stmt->execute();
            $new_main_id = (int)$conn->insert_id;
            $stmt->close();
            // สร้างหมวดหมู่ย่อย "ทั้งหมด" ให้ทุกหมวดหลัก (จะใช้เป็นหน้ารวมของหมวดหลักนั้น)
            if ($new_main_id > 0) {
                $stmt2 = $conn->prepare("INSERT IGNORE INTO sub_categories (main_id, name, slug, sort_order) VALUES (?, 'ทั้งหมด', 'all', 0)");
                $stmt2->bind_param("i", $new_main_id);
                $stmt2->execute();
                $stmt2->close();
            }
            $_SESSION['success'] = 'เพิ่มหมวดหมู่หลักแล้ว';
        }
    } elseif ($action === 'add_sub') {
        $main_id = (int)($_POST['main_id'] ?? 0);
        $name = trim($_POST['sub_name'] ?? '');
        $slug_in = trim((string)($_POST['sub_slug'] ?? ''));
        $slug_base = $slug_in !== '' ? $slug_in : $name;
        $slug = trim(preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug_base)), '-') ?: 'sub-' . time();
        $image = trim((string)($_POST['sub_image'] ?? ''));
        $image = $image === '' ? null : $image;
        // รองรับอัปโหลดไฟล์รูป
        if (isset($_FILES['sub_image_file']) && $_FILES['sub_image_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['sub_image_file']['tmp_name'];
            $file_name = $_FILES['sub_image_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed, true)) {
                $dir = __DIR__ . '/uploads/categories';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $new_name = 'sub_' . uniqid('', true) . '.' . $file_ext;
                $upload_path = $dir . '/' . $new_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $image = 'uploads/categories/' . $new_name;
                }
            }
        }
        if ($main_id && $name !== '') {
            $stmt = $conn->prepare("INSERT INTO sub_categories (main_id, name, slug, image, sort_order) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param("isss", $main_id, $name, $slug, $image);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'เพิ่มหมวดหมู่ย่อยแล้ว';
        }
    } elseif ($action === 'update_main') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug_in = trim((string)($_POST['slug'] ?? ''));
        $image = trim((string)($_POST['image'] ?? ''));
        $slug = trim(preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug_in !== '' ? $slug_in : $name)), '-') ?: null;
        if ($id && $name !== '' && $slug) {
            // ถ้าไม่ได้กรอก image และไม่ได้อัปโหลด ให้คงรูปเดิมไว้ (ลบรูปใช้ปุ่มลบรูป)
            $current_image = null;
            $r = $conn->query("SELECT image FROM main_categories WHERE id = $id LIMIT 1");
            if ($r && $row = $r->fetch_assoc()) $current_image = $row['image'] ?? null;

            $image_final = ($image === '') ? $current_image : $image;
            // ถ้ามีอัปโหลดไฟล์ ให้ใช้ไฟล์แทน
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image_file']['tmp_name'];
                $file_name = $_FILES['image_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($file_ext, $allowed, true)) {
                    $dir = __DIR__ . '/uploads/categories';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $new_name = 'cat_' . uniqid('', true) . '.' . $file_ext;
                    $upload_path = $dir . '/' . $new_name;
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $image_final = 'uploads/categories/' . $new_name;
                    }
                }
            }
            if ($image_final === '') $image_final = null;
            $stmt = $conn->prepare("UPDATE main_categories SET name = ?, slug = ?, image = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $slug, $image_final, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'อัปเดตหมวดหมู่หลักแล้ว';
        }
    } elseif ($action === 'update_sub') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $slug_in = trim((string)($_POST['slug'] ?? ''));
        $image = trim((string)($_POST['image'] ?? ''));
        $slug = trim(preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug_in !== '' ? $slug_in : $name)), '-') ?: null;
        if ($id && $name !== '' && $slug) {
            $current_image = null;
            $r = $conn->query("SELECT image FROM sub_categories WHERE id = $id LIMIT 1");
            if ($r && $row = $r->fetch_assoc()) $current_image = $row['image'] ?? null;

            $image_final = ($image === '') ? $current_image : $image;
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image_file']['tmp_name'];
                $file_name = $_FILES['image_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($file_ext, $allowed, true)) {
                    $dir = __DIR__ . '/uploads/categories';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $new_name = 'sub_' . uniqid('', true) . '.' . $file_ext;
                    $upload_path = $dir . '/' . $new_name;
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $image_final = 'uploads/categories/' . $new_name;
                    }
                }
            }
            if ($image_final === '') $image_final = null;
            $stmt = $conn->prepare("UPDATE sub_categories SET name = ?, slug = ?, image = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $slug, $image_final, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'อัปเดตหมวดหมู่ย่อยแล้ว';
        }
    } elseif ($action === 'clear_main_image') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("UPDATE main_categories SET image = NULL WHERE id = $id");
            $_SESSION['success'] = 'ลบรูปหมวดหมู่หลักแล้ว';
        }
    } elseif ($action === 'clear_sub_image') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("UPDATE sub_categories SET image = NULL WHERE id = $id");
            $_SESSION['success'] = 'ลบรูปหมวดหมู่ย่อยแล้ว';
        }
    } elseif ($action === 'delete_main') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $sub_ids = [];
            $r = $conn->query("SELECT id FROM sub_categories WHERE main_id = $id");
            if ($r) while ($row = $r->fetch_assoc()) $sub_ids[] = (int)$row['id'];
            foreach ($sub_ids as $sid) $conn->query("UPDATE products SET sub_category_id = NULL WHERE sub_category_id = $sid");
            $conn->query("DELETE FROM sub_categories WHERE main_id = $id");
            $conn->query("DELETE FROM main_categories WHERE id = $id");
            $_SESSION['success'] = 'ลบหมวดหมู่หลักแล้ว';
        }
    } elseif ($action === 'delete_sub') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("UPDATE products SET sub_category_id = NULL WHERE sub_category_id = $id");
            $conn->query("DELETE FROM sub_categories WHERE id = $id");
            $_SESSION['success'] = 'ลบหมวดหมู่ย่อยแล้ว';
        }
    }
    header('Location: admin_categories.php?inapp=1');
    exit;
}

$main_list = [];
$res = $conn->query("SELECT * FROM main_categories ORDER BY sort_order ASC, id ASC");
if ($res) while ($r = $res->fetch_assoc()) $main_list[] = $r;
$sub_by_main = [];
$res = $conn->query("SELECT * FROM sub_categories ORDER BY main_id, sort_order ASC, id ASC");
if ($res) while ($r = $res->fetch_assoc()) $sub_by_main[(int)$r['main_id']][] = $r;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>จัดการหมวดหมู่หลัก/ย่อย - Admin</title>
    <link rel="stylesheet" href="index.css?v=1.5">
    <?php include 'protection_header.php'; ?>
    <style>
        .admin-container { padding-top: 120px; padding-bottom: 50px; }
        .card { background: var(--card-bg); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.08); }
        .card h3 { margin: 0 0 1rem 0; font-size: 1.1rem; }
        .form-inline { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.75rem; }
        .form-inline input[type="text"] { padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); color: #fff; min-width: 160px; }
        .form-inline select { padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; }
        .sub-list { margin-left: 1.5rem; margin-top: 0.5rem; }
        .sub-list li { margin: 0.35rem 0; display: flex; align-items: center; gap: 0.5rem; }
        .btn-sm { padding: 4px 10px; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="noise"></div>
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <a href="admin_panel.php" class="logo"><div class="logo-icon">⚡</div><span>Admin Panel</span></a>
            <div class="nav-buttons">
                <a href="admin_panel.php" class="btn btn-secondary">ย้อนกลับ</a>
                <a href="admin_products.php?inapp=1" class="btn btn-primary">จัดการสินค้า</a>
            </div>
        </div>
    </nav>
    <div class="container admin-container">
        <h1>จัดการหมวดหมู่หลัก / หมวดหมู่ย่อย</h1>
        <?php if (isset($_SESSION['success'])): ?>
        <div style="background: rgba(34,197,94,0.2); color: #86efac; padding: 12px; border-radius: 8px; margin-bottom: 1rem;"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); endif; ?>

        <div class="card">
            <h3>➕ เพิ่มหมวดหมู่หลัก</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="form_action" value="add_main">
                <div class="form-inline">
                    <input type="text" name="main_name" placeholder="ชื่อหมวดหมู่หลัก (เช่น Gift Item)" required>
                    <input type="text" name="main_slug" placeholder="URL/Slug (เช่น gift-item) (ไม่ใส่ = สร้างอัตโนมัติ)">
                    <input type="text" name="main_image" placeholder="ลิงก์รูปภาพ (URL รูป)">
                    <input type="file" name="main_image_file" accept="image/*" class="btn-sm" style="max-width:240px;">
                    <button type="submit" class="btn btn-primary btn-sm">เพิ่ม</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>➕ เพิ่มหมวดหมู่ย่อย</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="form_action" value="add_sub">
                <div class="form-inline">
                    <select name="main_id" required>
                        <option value="">-- เลือกหมวดหมู่หลัก --</option>
                        <?php foreach ($main_list as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="sub_name" placeholder="ชื่อหมวดหมู่ย่อย (เช่น เกมพาส BloxFruit)" required>
                    <input type="text" name="sub_slug" placeholder="URL/Slug (เช่น gamepass) (ไม่ใส่ = สร้างอัตโนมัติ)">
                    <input type="text" name="sub_image" placeholder="ลิงก์รูปภาพ (URL รูป)">
                    <input type="file" name="sub_image_file" accept="image/*" class="btn-sm" style="max-width:240px;">
                    <button type="submit" class="btn btn-primary btn-sm">เพิ่ม</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>📁 หมวดหมู่หลัก และหมวดหมู่ย่อย</h3>
            <?php foreach ($main_list as $m): ?>
            <div style="margin-bottom: 1rem;">
                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                    <?php if (!empty($m['image'])): ?>
                        <img src="<?= htmlspecialchars($m['image']) ?>" alt="" style="width:84px;height:48px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.15);">
                    <?php endif; ?>
                    <div>
                        <strong><?= htmlspecialchars($m['name']) ?></strong> (slug: <?= htmlspecialchars($m['slug']) ?>)
                        <?php if (!empty($m['image'])): ?>
                            <div style="font-size:0.85rem;color:var(--text-muted);max-width:900px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                รูป: <?= htmlspecialchars($m['image']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="post" enctype="multipart/form-data" style="margin-top:8px;">
                    <input type="hidden" name="form_action" value="update_main">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <div class="form-inline">
                        <input type="text" name="name" value="<?= htmlspecialchars($m['name']) ?>" placeholder="ชื่อ">
                        <input type="text" name="slug" value="<?= htmlspecialchars($m['slug']) ?>" placeholder="URL/Slug">
                        <input type="text" name="image" value="<?= htmlspecialchars($m['image'] ?? '') ?>" placeholder="ลิงก์รูปภาพ (URL รูป)">
                        <input type="file" name="image_file" accept="image/*" class="btn-sm" style="max-width:240px;">
                        <button type="submit" class="btn btn-primary btn-sm">บันทึก</button>
                        <button type="submit" name="form_action" value="clear_main_image" class="btn btn-secondary btn-sm" style="background:#ef4444;border:none;" onclick="return confirm('ลบรูปหมวดหมู่หลักนี้?');">ลบรูป</button>
                    </div>
                </form>
                <form method="post" style="display:inline; margin-left: 8px;">
                    <input type="hidden" name="form_action" value="delete_main">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="background:#ef4444; border:none;" onclick="return confirm('ลบหมวดหมู่หลักและหมวดหมู่ย่อยภายในทั้งหมด?');">ลบ</button>
                </form>
                <ul class="sub-list">
                    <?php
                    $subs = $sub_by_main[(int)$m['id']] ?? [];
                    foreach ($subs as $s):
                    ?>
                    <li>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%;">
                            <?php if (!empty($s['image'])): ?>
                                <img src="<?= htmlspecialchars($s['image']) ?>" alt="" style="width:72px;height:42px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,0.15);">
                            <?php endif; ?>
                            <div style="flex:1; min-width: 220px;">
                                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['slug']) ?>)
                                <?php if (!empty($s['image'])): ?>
                                    <div style="font-size:0.82rem;color:var(--text-muted);max-width:700px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        รูป: <?= htmlspecialchars($s['image']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" enctype="multipart/form-data" style="width:100%; margin-top:6px;">
                            <input type="hidden" name="form_action" value="update_sub">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <div class="form-inline">
                                <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" placeholder="ชื่อ">
                                <input type="text" name="slug" value="<?= htmlspecialchars($s['slug']) ?>" placeholder="URL/Slug">
                                <input type="text" name="image" value="<?= htmlspecialchars($s['image'] ?? '') ?>" placeholder="ลิงก์รูปภาพ (URL รูป)">
                                <input type="file" name="image_file" accept="image/*" class="btn-sm" style="max-width:240px;">
                                <button type="submit" class="btn btn-primary btn-sm">บันทึก</button>
                                <button type="submit" name="form_action" value="clear_sub_image" class="btn btn-secondary btn-sm" style="background:#ef4444;border:none;" onclick="return confirm('ลบรูปหมวดหมู่ย่อยนี้?');">ลบรูป</button>
                            </div>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="form_action" value="delete_sub">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" style="background:#ef4444; border:none;" onclick="return confirm('ลบหมวดหมู่ย่อยนี้?');">ลบ</button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
            <?php if (count($main_list) === 0): ?>
            <p style="color: var(--text-muted);">ยังไม่มีหมวดหมู่ — เพิ่มหมวดหมู่หลักด้านบน</p>
            <?php endif; ?>
        </div>
    </div>
    <script src="script.js?v=2.0"></script>
</body>
</html>
