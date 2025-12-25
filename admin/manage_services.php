<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/validation.php';
require_once 'includes/db_helper.php';
require_once 'includes/error_logger.php';

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// ตั้งค่าโฟลเดอร์สำหรับเก็บรูปภาพ
$upload_dir = '../uploads/services/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$error = '';
$success = '';

// สร้าง DatabaseHelper instance
$db = new DatabaseHelper($conn);

// ตัวกรอง
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';

// ดึงข้อมูลบริการทั้งหมด
$services = [];
try {
    $where_conditions = [];
    
    if ($filter_status !== '') {
        if ($filter_status === 'active') {
            $where_conditions[] = "is_active = 1";
        } elseif ($filter_status === 'inactive') {
            $where_conditions[] = "is_active = 0";
        }
    }
    
    if ($filter_search) {
        $search_term = $conn->real_escape_string($filter_search);
        $where_conditions[] = "(name LIKE '%{$search_term}%' OR description LIKE '%{$search_term}%')";
    }
    
    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    $sql = "SELECT * FROM massage_types $where_sql ORDER BY created_at DESC";
    
    $services = $db->fetchAll($sql);
} catch (Exception $e) {
    logError('Error fetching services: ' . $e->getMessage());
    $error = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
}

// ฟังก์ชันสำหรับอัพโหลดรูปภาพ
function uploadServiceImage($file, $upload_dir, $existing_image = null)
{
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // ใช้ Validator class สำหรับ validation
    $validator = new Validator();
    
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing_image; // ไม่มีไฟล์ใหม่, ใช้ไฟล์เดิม
    }

    // ตรวจสอบ error codes
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในเซิร์ฟเวอร์',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัพโหลดไม่สมบูรณ์',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวสำหรับเก็บไฟล์',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์',
            UPLOAD_ERR_EXTENSION => 'การอัพโหลดถูกหยุดโดย extension'
        ];
        $error_msg = $error_messages[$file['error']] ?? 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
        throw new Exception($error_msg);
    }

    // ตรวจสอบว่าเป็นไฟล์จริง
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('ไฟล์ไม่ถูกต้อง');
    }

    // ตรวจสอบประเภทไฟล์ด้วย MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, PNG, GIF, WebP');
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > $max_size) {
        $max_mb = $max_size / 1024 / 1024;
        throw new Exception("ขนาดไฟล์ใหญ่เกินไป อนุญาตสูงสุด {$max_mb} MB");
    }

    // ตรวจสอบขนาดไฟล์ขั้นต่ำ (ป้องกันไฟล์เปล่า)
    if ($file['size'] < 100) {
        throw new Exception('ไฟล์มีขนาดเล็กเกินไป');
    }

    // ตรวจสอบ extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('นามสกุลไฟล์ไม่ถูกต้อง');
    }

    // สร้างชื่อไฟล์ใหม่ที่ปลอดภัย
    $new_filename = 'service_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    // ตรวจสอบว่าโฟลเดอร์มีอยู่และสามารถเขียนได้
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บไฟล์');
        }
    }

    if (!is_writable($upload_dir)) {
        throw new Exception('ไม่สามารถเขียนไฟล์ในโฟลเดอร์ปลายทาง');
    }

    // ย้ายไฟล์ไปยังโฟลเดอร์ปลายทาง
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้ กรุณาลองใหม่อีกครั้ง');
    }

    // ตั้งค่า permissions
    chmod($upload_path, 0644);

    // ลบไฟล์เก่าหากมีการอัพโหลดไฟล์ใหม่และมีไฟล์เก่าอยู่
    if ($existing_image && $existing_image !== $new_filename && file_exists($upload_dir . $existing_image)) {
        @unlink($upload_dir . $existing_image);
    }

    return $new_filename;
}

// เพิ่มบริการใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_service'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration_minutes = $_POST['duration_minutes'] ?? '';
        $price = $_POST['price'] ?? '';

        // Validate required fields
        $validator->required('name', $name, 'ชื่อบริการ');
        $validator->required('duration_minutes', $duration_minutes, 'ระยะเวลา');
        $validator->required('price', $price, 'ราคา');

        // Validate duration range
        if ($duration_minutes !== '') {
            $duration_minutes = intval($duration_minutes);
            $validator->numberRange('duration_minutes', $duration_minutes, 15, 180, 'ระยะเวลา');
        }

        // Validate price
        if ($price !== '') {
            $price = floatval($price);
            if ($price < 0) {
                $validator->required('price', '', 'ราคาต้องมากกว่าหรือเท่ากับ 0');
            }
        }

        // Check for validation errors
        if ($validator->hasErrors()) {
            $errors = $validator->getErrors();
            throw new Exception('ข้อมูลไม่ถูกต้อง: ' . implode(', ', $errors));
        }

        // Validate and upload image
        $image_filename = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image_filename = uploadServiceImage($_FILES['image'], $upload_dir);
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $duration_minutes,
            'price' => $price,
            'image_url' => $image_filename
        ];

        $db->insert('massage_types', $data);
        $success = 'เพิ่มบริการสำเร็จ';
        
        // โหลดข้อมูลใหม่
        $services = $db->fetchAll("SELECT * FROM massage_types ORDER BY created_at DESC");
    } catch (Exception $e) {
        logError('Error adding service: ' . $e->getMessage(), ['post' => $_POST]);
        $error = $e->getMessage();
    }
}

// อัพเดทบริการ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_service'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        
        $massage_type_id = $_POST['massage_type_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration_minutes = $_POST['duration_minutes'] ?? '';
        $price = $_POST['price'] ?? '';
        $is_active = $_POST['is_active'] ?? '';

        // Validate required fields
        $validator->required('massage_type_id', $massage_type_id, 'รหัสบริการ');
        $validator->required('name', $name, 'ชื่อบริการ');
        $validator->required('duration_minutes', $duration_minutes, 'ระยะเวลา');
        $validator->required('price', $price, 'ราคา');
        $validator->required('is_active', $is_active, 'สถานะ');

        // Convert to proper types
        $massage_type_id = intval($massage_type_id);
        $duration_minutes = intval($duration_minutes);
        $price = floatval($price);
        $is_active = intval($is_active);

        // Validate duration range
        $validator->numberRange('duration_minutes', $duration_minutes, 15, 180, 'ระยะเวลา');

        // Validate price
        if ($price < 0) {
            $validator->required('price', '', 'ราคาต้องมากกว่าหรือเท่ากับ 0');
        }

        // Validate is_active
        if (!in_array($is_active, [0, 1])) {
            throw new Exception('สถานะไม่ถูกต้อง');
        }

        // Check for validation errors
        if ($validator->hasErrors()) {
            $errors = $validator->getErrors();
            throw new Exception('ข้อมูลไม่ถูกต้อง: ' . implode(', ', $errors));
        }

        // ตรวจสอบว่า service มีอยู่จริง
        $existing = $db->fetchOne(
            "SELECT massage_type_id, image_url FROM massage_types WHERE massage_type_id = ?",
            [$massage_type_id],
            'i'
        );

        if (!$existing) {
            throw new Exception('ไม่พบบริการที่ต้องการแก้ไข');
        }

        $current_image = $existing['image_url'] ?? '';

        // Validate and upload image
        $image_filename = $current_image;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image_filename = uploadServiceImage($_FILES['image'], $upload_dir, $current_image);
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $duration_minutes,
            'price' => $price,
            'image_url' => $image_filename,
            'is_active' => $is_active,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db->update('massage_types', $data, 'massage_type_id = ?', [$massage_type_id], 'i');
        $success = 'อัพเดทบริการสำเร็จ';
        
        // โหลดข้อมูลใหม่
        $services = $db->fetchAll("SELECT * FROM massage_types ORDER BY created_at DESC");
    } catch (Exception $e) {
        logError('Error updating service: ' . $e->getMessage(), ['post' => $_POST]);
        $error = $e->getMessage();
    }
}

// ลบบริการ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_service'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        $massage_type_id = $_POST['massage_type_id'] ?? '';
        
        $validator->required('massage_type_id', $massage_type_id, 'รหัสบริการ');
        
        if ($validator->hasErrors()) {
            $errors = $validator->getErrors();
            throw new Exception('ข้อมูลไม่ถูกต้อง: ' . implode(', ', $errors));
        }

        $massage_type_id = intval($massage_type_id);

        // ตรวจสอบว่า service มีอยู่จริง
        $image_data = $db->fetchOne(
            "SELECT massage_type_id, image_url FROM massage_types WHERE massage_type_id = ?",
            [$massage_type_id],
            'i'
        );

        if (!$image_data) {
            throw new Exception('ไม่พบบริการที่ต้องการลบ');
        }

        $image_filename = $image_data['image_url'];

        // ลบไฟล์ภาพ
        if ($image_filename && file_exists($upload_dir . $image_filename)) {
            @unlink($upload_dir . $image_filename);
        }

        $db->delete('massage_types', 'massage_type_id = ?', [$massage_type_id], 'i');
        $success = 'ลบบริการสำเร็จ';
        
        // โหลดข้อมูลใหม่
        $services = $db->fetchAll("SELECT * FROM massage_types ORDER BY created_at DESC");
    } catch (Exception $e) {
        logError('Error deleting service: ' . $e->getMessage(), ['post' => $_POST]);
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบริการนวด</title>
    <?php include '../templates/admin-head.php'; ?>
</head>
<body>
    <?php include '../templates/navbar-admin.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-spa text-primary"></i>
                    <!-- <i class="bi bi-heart-pulse text-danger"></i> -->
                    <i class="bi bi-flower1 text-success"></i>
                    จัดการบริการนวด
                </h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-list-check me-1"></i>ระบบจัดการบริการนวดทั้งหมดในระบบ
                </p>
            </div>
            <!-- <div>
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> พิมพ์รายการ
                </button>
            </div> -->
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองข้อมูล</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">สถานะ</label>
                        <select name="status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>เปิดบริการ</option>
                            <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>ปิดบริการ</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="ค้นหาชื่อบริการหรือรายละเอียด"
                            value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2">
                            <i class="bi bi-search me-1"></i>กรอง
                        </button>
                        <a href="manage_services.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>ล้าง
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Service Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">
                                    <i class="bi bi-list-ul me-1"></i>บริการทั้งหมด
                                </h6>
                                <h2 class="card-title mb-0"><?= count($services) ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-spa"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">
                                    <i class="bi bi-check-circle-fill me-1"></i>บริการที่เปิด
                                </h6>
                                <h2 class="card-title mb-0"><?= count(array_filter($services, function($s) { return $s['is_active']; })) ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">
                                    <i class="bi bi-x-circle-fill me-1"></i>บริการที่ปิด
                                </h6>
                                <h2 class="card-title mb-0"><?= count(array_filter($services, function($s) { return !$s['is_active']; })) ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-pause-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form เพิ่มบริการใหม่ -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-plus-circle-fill me-2"></i>
                    <i class="bi bi-spa me-1"></i>
                    เพิ่มบริการใหม่
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="add_service" value="1">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อบริการ <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="กรอกชื่อบริการ" required>
                            <div class="invalid-feedback">กรุณากรอกชื่อบริการ</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">ระยะเวลา (นาที) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_minutes" class="form-control" min="15" max="180" placeholder="กรอกระยะเวลา" required>
                            <div class="invalid-feedback">กรุณากรอกระยะเวลา 15-180 นาที</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">ราคา (บาท) <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" placeholder="กรอกราคา" required>
                            <div class="invalid-feedback">กรุณากรอกราคา</div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">รายละเอียดบริการ</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="กรอกรายละเอียดบริการ"></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">สถานะบริการ <span class="text-danger">*</span></label>
                            <select name="is_active" class="form-select" required>
                                <option value="1" selected>เปิดบริการ</option>
                                <option value="0">ปิดบริการ</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกสถานะบริการ</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">รูปภาพบริการ</label>
                            <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this, 'addPreview')">
                            <div class="form-text">รองรับไฟล์: JPG, JPEG, PNG, GIF, WebP ขนาดไม่เกิน 5MB</div>
                            <div id="addPreview" class="mt-2"></div>
                        </div>

                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> เพิ่มบริการ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางบริการ -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>
                    <i class="bi bi-spa me-1"></i>
                    <i class="bi bi-heart-pulse me-1"></i>
                    บริการทั้งหมด (<?= count($services) ?> รายการ)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>รหัส</th>
                                <th>รูปภาพ</th>
                                <th>ชื่อบริการ</th>
                                <th>รายละเอียด</th>
                                <th>ระยะเวลา</th>
                                <th>ราคา</th>
                                <th>สถานะ</th>
                                <th class="text-center">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">#<?= $service['massage_type_id'] ?></span>
                                </td>
                                <td>
                                    <?php if ($service['image_url']): ?>
                                    <img src="<?= $upload_dir . $service['image_url'] ?>"
                                         alt="<?= htmlspecialchars($service['name']) ?>" 
                                         class="rounded img-thumbnail"
                                         width="60" height="60">
                                    <?php else: ?>
                                    <span class="badge bg-light text-muted p-3">
                                        <i class="bi bi-spa fs-4"></i>
                                        <i class="bi bi-heart-pulse fs-5"></i>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($service['name']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($service['description']): ?>
                                    <span class="text-muted"><?= htmlspecialchars(mb_substr($service['description'], 0, 50)) ?><?= mb_strlen($service['description']) > 50 ? '...' : '' ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="bi bi-clock"></i> <?= $service['duration_minutes'] ?> นาที
                                </td>
                                <td class="fw-bold">
                                    ฿<?= number_format($service['price'], 2) ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $service['is_active'] ? 'success' : 'danger' ?>">
                                        <i class="bi bi-<?= $service['is_active'] ? 'check' : 'x' ?>-circle"></i>
                                        <?= $service['is_active'] ? 'เปิด' : 'ปิด' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= $service['massage_type_id'] ?>"
                                                title="แก้ไข">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal"
                                                data-bs-target="#deleteModal<?= $service['massage_type_id'] ?>"
                                                title="ลบ">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $service['massage_type_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">
                                                        <i class="bi bi-pencil-square"></i> แก้ไขบริการ: <?= htmlspecialchars($service['name']) ?>
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="update_service" value="1">
                                                    <input type="hidden" name="massage_type_id" value="<?= $service['massage_type_id'] ?>">
                                                    <?= csrfField() ?>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">ชื่อบริการ <span class="text-danger">*</span></label>
                                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($service['name']) ?>" required>
                                                            </div>

                                                            <div class="col-md-3">
                                                                <label class="form-label">ระยะเวลา (นาที) <span class="text-danger">*</span></label>
                                                                <input type="number" name="duration_minutes" class="form-control" value="<?= $service['duration_minutes'] ?>" min="15" max="180" required>
                                                            </div>

                                                            <div class="col-md-3">
                                                                <label class="form-label">ราคา (บาท) <span class="text-danger">*</span></label>
                                                                <input type="number" name="price" class="form-control" value="<?= $service['price'] ?>" step="0.01" min="0" required>
                                                            </div>

                                                            <div class="col-md-8">
                                                                <label class="form-label">รายละเอียด</label>
                                                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($service['description']) ?></textarea>
                                                            </div>

                                                            <div class="col-md-4">
                                                                <label class="form-label">สถานะ</label>
                                                                <select name="is_active" class="form-select" required>
                                                                    <option value="1" <?= $service['is_active'] ? 'selected' : '' ?>>เปิดบริการ</option>
                                                                    <option value="0" <?= !$service['is_active'] ? 'selected' : '' ?>>ปิดบริการ</option>
                                                                </select>
                                                            </div>

                                                            <div class="col-12">
                                                                <label class="form-label">รูปภาพบริการ</label>
                                                                <?php if ($service['image_url']): ?>
                                                                <div class="mb-2">
                                                                    <p class="text-muted mb-1">รูปภาพปัจจุบัน:</p>
                                                                    <img src="<?= $upload_dir . $service['image_url'] ?>" alt="Current" class="rounded img-thumbnail" width="120" height="100">
                                                                </div>
                                                                <?php endif; ?>
                                                                <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this, 'editPreview<?= $service['massage_type_id'] ?>')">
                                                                <div class="form-text">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรูปภาพ</div>
                                                                <div id="editPreview<?= $service['massage_type_id'] ?>" class="mt-2"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            <i class="bi bi-x-circle"></i> ปิด
                                                        </button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-save"></i> อัพเดท
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?= $service['massage_type_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">
                                                        <i class="bi bi-exclamation-triangle"></i> ลบบริการ
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="delete_service" value="1">
                                                    <input type="hidden" name="massage_type_id" value="<?= $service['massage_type_id'] ?>">
                                                    <?= csrfField() ?>
                                                    <div class="modal-body">
                                                        <p>คุณแน่ใจว่าต้องการลบบริการ <strong>"<?= htmlspecialchars($service['name']) ?>"</strong>?</p>
                                                        <?php if ($service['image_url']): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-circle"></i> รูปภาพที่เกี่ยวข้องจะถูกลบออกจากระบบด้วย
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="alert alert-danger">
                                                            <i class="bi bi-exclamation-triangle"></i> <strong>คำเตือน:</strong> การกระทำนี้ไม่สามารถย้อนกลับได้และอาจส่งผลต่อการจองที่เกี่ยวข้อง!
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            <i class="bi bi-x-circle"></i> ยกเลิก
                                                        </button>
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="bi bi-trash"></i> ลบ
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>

    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';

            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, PNG, GIF, WebP');
                    input.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('ขนาดไฟล์ใหญ่เกินไป อนุญาตสูงสุด 5 MB');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail';
                    img.width = 200;
                    img.height = 150;
                    preview.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>