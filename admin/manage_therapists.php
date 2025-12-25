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

$error = '';
$success = '';
$db = new DatabaseHelper($conn);

// ตัวกรอง
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';

// ดึงข้อมูลหมอนวดทั้งหมด
$therapists = [];
$where_conditions = [];
$having_conditions = [];

if ($filter_status !== '') {
    if ($filter_status === 'available') {
        $where_conditions[] = "t.is_available = 1";
    } elseif ($filter_status === 'unavailable') {
        $where_conditions[] = "t.is_available = 0";
    }
}

if ($filter_search) {
    $search_term = $conn->real_escape_string($filter_search);
    $where_conditions[] = "(t.full_name LIKE '%{$search_term}%' OR t.specialization LIKE '%{$search_term}%' OR t.phone LIKE '%{$search_term}%')";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql = "SELECT t.*, 
               GROUP_CONCAT(mt.name SEPARATOR ', ') as specializations,
               COUNT(b.booking_id) as total_bookings
        FROM therapists t
        LEFT JOIN therapist_massage_types tmt ON t.therapist_id = tmt.therapist_id
        LEFT JOIN massage_types mt ON tmt.massage_type_id = mt.massage_type_id
        LEFT JOIN bookings b ON t.therapist_id = b.therapist_id
        $where_sql
        GROUP BY t.therapist_id
        ORDER BY t.created_at DESC";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $therapists[] = $row;
    }
}

// ดึงข้อมูลบริการทั้งหมดสำหรับ dropdown
$massage_types = [];
$type_sql = "SELECT * FROM massage_types WHERE is_active = TRUE";
$type_result = $conn->query($type_sql);
if ($type_result->num_rows > 0) {
    while ($row = $type_result->fetch_assoc()) {
        $massage_types[] = $row;
    }
}

// เพิ่มหมอนวดใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_therapist'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token. กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        $validator->required('full_name', $_POST['full_name'] ?? '', 'ชื่อ-นามสกุล');
        
        if (!empty($_POST['phone'])) {
            $validator->phone('phone', $_POST['phone'], 'เบอร์โทรศัพท์');
        }

        // Validate file upload
        $validator->file('profile_image', $_FILES['profile_image'] ?? [], 
                        ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 
                        5 * 1024 * 1024, 'รูปภาพโปรไฟล์');

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $full_name = trim($_POST['full_name']);
            $phone = trim($_POST['phone'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');

            // อัพโหลดรูปภาพ
            $image_filename = uploadProfileImage($_FILES);

            if (!$error) {
                // Insert therapist using prepared statement
                $insert_data = [
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'specialization' => $specialization,
                    'image_url' => $image_filename
                ];

                $stmt = $db->insert('therapists', $insert_data);
                $therapist_id = $db->getLastInsertId();

                // เพิ่มความเชี่ยวชาญ
                if (isset($_POST['massage_types']) && is_array($_POST['massage_types'])) {
                    foreach ($_POST['massage_types'] as $type_id) {
                        if (is_numeric($type_id)) {
                            $db->insert('therapist_massage_types', [
                                'therapist_id' => $therapist_id,
                                'massage_type_id' => (int)$type_id
                            ]);
                        }
                    }
                }

                $success = 'เพิ่มหมอนวดสำเร็จ';
                
                // โหลดข้อมูลใหม่
                $result = $conn->query($sql);
                $therapists = [];
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $therapists[] = $row;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Add therapist error: ' . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'],
            'post_data' => $_POST
        ]);
        
        // ลบรูปภาพที่อัพโหลดไปแล้วหากเพิ่มข้อมูลไม่สำเร็จ
        if (isset($image_filename) && $image_filename) {
            deleteProfileImage($image_filename);
        }
    }
}

// อัพเดทหมอนวด
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_therapist'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token. กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        $validator->required('therapist_id', $_POST['therapist_id'] ?? '', 'รหัสหมอนวด');
        $validator->required('full_name', $_POST['full_name'] ?? '', 'ชื่อ-นามสกุล');
        
        if (!empty($_POST['phone'])) {
            $validator->phone('phone', $_POST['phone'], 'เบอร์โทรศัพท์');
        }

        // Validate file upload
        $validator->file('profile_image', $_FILES['profile_image'] ?? [], 
                        ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'], 
                        5 * 1024 * 1024, 'รูปภาพโปรไฟล์');

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $therapist_id = (int)$_POST['therapist_id'];
            $full_name = trim($_POST['full_name']);
            $phone = trim($_POST['phone'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');
            $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;

            // ตรวจสอบว่า therapist_id มีอยู่จริง
            $existing = $db->fetchOne(
                "SELECT therapist_id, image_url FROM therapists WHERE therapist_id = ?",
                [$therapist_id],
                'i'
            );

            if (!$existing) {
                throw new Exception('ไม่พบข้อมูลหมอนวดที่ต้องการแก้ไข');
            }

            $current_image = $existing['image_url'];

            // อัพโหลดรูปภาพใหม่ (ถ้ามี)
            $image_filename = uploadProfileImage($_FILES, $current_image);

            if (!$error) {
                // Update therapist using prepared statement
                $update_data = [
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'specialization' => $specialization,
                    'image_url' => $image_filename,
                    'is_available' => $is_available,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $db->update('therapists', $update_data, 'therapist_id = ?', [$therapist_id], 'i');

                // อัพเดทความเชี่ยวชาญ
                $db->delete('therapist_massage_types', 'therapist_id = ?', [$therapist_id], 'i');

                if (isset($_POST['massage_types']) && is_array($_POST['massage_types'])) {
                    foreach ($_POST['massage_types'] as $type_id) {
                        if (is_numeric($type_id)) {
                            $db->insert('therapist_massage_types', [
                                'therapist_id' => $therapist_id,
                                'massage_type_id' => (int)$type_id
                            ]);
                        }
                    }
                }

                $success = 'อัพเดทหมอนวดสำเร็จ';
                
                // โหลดข้อมูลใหม่
                $result = $conn->query($sql);
                $therapists = [];
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $therapists[] = $row;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Update therapist error: ' . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'],
            'therapist_id' => $_POST['therapist_id'] ?? null,
            'post_data' => $_POST
        ]);
    }
}

// ลบหมอนวด
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_therapist'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token. กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        $validator->required('therapist_id', $_POST['therapist_id'] ?? '', 'รหัสหมอนวด');

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $therapist_id = (int)$_POST['therapist_id'];

            // ดึงข้อมูลรูปภาพก่อนลบ
            $therapist_data = $db->fetchOne(
                "SELECT image_url FROM therapists WHERE therapist_id = ?",
                [$therapist_id],
                'i'
            );

            if (!$therapist_data) {
                throw new Exception('ไม่พบข้อมูลหมอนวดที่ต้องการลบ');
            }

            $image_filename = $therapist_data['image_url'];

            // Delete therapist using prepared statement
            $db->delete('therapists', 'therapist_id = ?', [$therapist_id], 'i');

            // ลบรูปภาพออกจากเซิร์ฟเวอร์
            deleteProfileImage($image_filename);

            $success = 'ลบหมอนวดสำเร็จ';
            
            // โหลดข้อมูลใหม่
            $result = $conn->query($sql);
            $therapists = [];
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $therapists[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Delete therapist error: ' . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'],
            'therapist_id' => $_POST['therapist_id'] ?? null
        ]);
    }
}
function uploadProfileImage($file, $current_image = null)
{
    global $conn, $error;

    // ตรวจสอบว่ามีการอัพโหลดไฟล์หรือไม่
    if (!isset($file['profile_image']) || $file['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_image; // คืนค่ารูปเดิมหากไม่มีการอัพโหลดใหม่
    }

    $upload_dir = '../uploads/profile/';

    // สร้างโฟลเดอร์หากยังไม่มี
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_info = $file['profile_image'];

    // ตรวจสอบข้อผิดพลาดในการอัพโหลด
    if ($file_info['error'] !== UPLOAD_ERR_OK) {
        $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
        return $current_image;
    }

    // ตรวจสอบประเภทไฟล์
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = $file_info['type'];

    if (!in_array($file_type, $allowed_types)) {
        $error = 'ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, JPG, PNG, GIF';
        return $current_image;
    }

    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
    $file_size = $file_info['size'];
    if ($file_size > 5 * 1024 * 1024) {
        $error = 'ไฟล์มีขนาดใหญ่เกินไป อนุญาตไม่เกิน 5MB';
        return $current_image;
    }

    // สร้างชื่อไฟล์ใหม่
    $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
    $new_filename = 'therapist_' . time() . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    // อัพโหลดไฟล์
    if (move_uploaded_file($file_info['tmp_name'], $upload_path)) {
        // ลบรูปภาพเดิมหากมี
        if ($current_image && file_exists($upload_dir . $current_image)) {
            unlink($upload_dir . $current_image);
        }
        return $new_filename;
    } else {
        $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
        return $current_image;
    }
}

// ฟังก์ชันลบรูปภาพ
function deleteProfileImage($image_filename)
{
    $upload_dir = '../uploads/profile/';
    if ($image_filename && file_exists($upload_dir . $image_filename)) {
        unlink($upload_dir . $image_filename);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหมอนวด - Admin Panel</title>
    <?php include '../templates/admin-head.php'; ?>
</head>
<body>
    <?php include '../templates/navbar-admin.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-person-badge"></i> จัดการหมอนวด</h1>
                <p class="text-muted mb-0">ระบบจัดการข้อมูลพนักงานนวดทั้งหมดในระบบ</p>
            </div>
            <!-- <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> พิมพ์รายการ
            </button> -->
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
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
                            <option value="available" <?= $filter_status == 'available' ? 'selected' : '' ?>>พร้อมให้บริการ</option>
                            <option value="unavailable" <?= $filter_status == 'unavailable' ? 'selected' : '' ?>>ไม่ว่าง</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="ค้นหาชื่อ, ความเชี่ยวชาญ, หรือเบอร์โทร"
                            value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2">
                            <i class="bi bi-search me-1"></i>กรอง
                        </button>
                        <a href="manage_therapists.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>ล้าง
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form เพิ่มหมอนวดใหม่ -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> เพิ่มหมอนวดใหม่</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="add_therapist" value="1">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="กรอกชื่อ-นามสกุล" required>
                                <div class="invalid-feedback">กรุณากรอกชื่อ-นามสกุล</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" name="phone" class="form-control" placeholder="กรอกเบอร์โทรศัพท์" pattern="[0-9]{10}">
                                <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ความเชี่ยวชาญ (แสดงในโปรไฟล์)</label>
                                <input type="text" name="specialization" class="form-control" placeholder="เช่น นวดแผนไทย, นวดอโรม่า, นวดน้ำมัน">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">รูปภาพโปรไฟล์</label>
                                <input type="file" name="profile_image" class="form-control" accept="image/*" id="addImageInput">
                                <div class="form-text">รองรับไฟล์: JPG, JPEG, PNG, GIF ขนาดไม่เกิน 5MB</div>
                                <div id="addImagePreview" class="mt-3 text-center d-none">
                                    <img src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">บริการที่เชี่ยวชาญ <span class="text-danger">*</span></label>
                                <div class="border rounded p-3 overflow-auto" style="max-height: 200px;">
                                    <?php foreach ($massage_types as $type): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="massage_types[]" 
                                                   value="<?= $type['massage_type_id'] ?>" id="type_<?= $type['massage_type_id'] ?>">
                                            <label class="form-check-label d-flex justify-content-between w-100" for="type_<?= $type['massage_type_id'] ?>">
                                                <span><?= $type['name'] ?></span>
                                                <small class="text-muted"><?= $type['duration_minutes'] ?> นาที</small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> เพิ่มหมอนวด
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางหมอนวด -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> หมอนวดทั้งหมด (<?= count($therapists) ?> คน)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>รหัส</th>
                                <th>ข้อมูลส่วนตัว</th>
                                <th>เบอร์โทร</th>
                                <th>ความเชี่ยวชาญ</th>
                                <th>บริการ</th>
                                <th>การจอง</th>
                                <th>สถานะ</th>
                                <th class="text-center">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($therapists as $therapist): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?= $therapist['therapist_id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($therapist['image_url']): ?>
                                                <img src="../uploads/profile/<?= $therapist['image_url'] ?>"
                                                    alt="<?= $therapist['full_name'] ?>" 
                                                    class="rounded-circle me-3 flex-shrink-0"
                                                    style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle me-3 bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                                                     style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person text-muted fs-4"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?= $therapist['full_name'] ?></div>
                                                <?php if ($therapist['specialization']): ?>
                                                    <small class="text-muted"><?= $therapist['specialization'] ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($therapist['phone']): ?>
                                            <a href="tel:<?= $therapist['phone'] ?>" class="text-decoration-none">
                                                <i class="bi bi-telephone text-success"></i>
                                                <?= $therapist['phone'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($therapist['specialization']): ?>
                                            <?= $therapist['specialization'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $services = explode(',', $therapist['specializations']);
                                        foreach (array_slice($services, 0, 2) as $service):
                                        ?>
                                            <span class="badge bg-info text-dark me-1 mb-1"><?= trim($service) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($services) > 2): ?>
                                            <span class="badge bg-secondary">+<?= count($services) - 2 ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <i class="bi bi-calendar-check"></i>
                                            <?= $therapist['total_bookings'] ?> ครั้ง
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $therapist['is_available'] ? 'success' : 'danger' ?>">
                                            <i class="bi bi-<?= $therapist['is_available'] ? 'check' : 'x' ?>-circle"></i>
                                            <?= $therapist['is_available'] ? 'พร้อมทำงาน' : 'ไม่ว่าง' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?= $therapist['therapist_id'] ?>"
                                                    title="แก้ไข">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?= $therapist['therapist_id'] ?>"
                                                    title="ลบ">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?= $therapist['therapist_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title">
                                                            <i class="bi bi-pencil-square"></i>
                                                            แก้ไขหมอนวด: <?= $therapist['full_name'] ?>
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" enctype="multipart/form-data">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="update_therapist" value="1">
                                                        <input type="hidden" name="therapist_id" value="<?= $therapist['therapist_id'] ?>">
                                                        <div class="modal-body">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                                        <input type="text" name="full_name" class="form-control" 
                                                                               value="<?= $therapist['full_name'] ?>" required>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">เบอร์โทรศัพท์</label>
                                                                        <input type="tel" name="phone" class="form-control" 
                                                                               value="<?= $therapist['phone'] ?>">
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">ความเชี่ยวชาญ</label>
                                                                        <input type="text" name="specialization" class="form-control" 
                                                                               value="<?= $therapist['specialization'] ?>" 
                                                                               placeholder="เช่น นวดแผนไทย, นวดอโรม่า">
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">สถานะ</label>
                                                                        <select name="is_available" class="form-select" required>
                                                                            <option value="1" <?= $therapist['is_available'] ? 'selected' : '' ?>>พร้อมทำงาน</option>
                                                                            <option value="0" <?= !$therapist['is_available'] ? 'selected' : '' ?>>ไม่ว่าง</option>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">รูปภาพโปรไฟล์</label>
                                                                        <?php if (!empty($therapist['image_url'])): ?>
                                                                            <div class="mb-3 text-center">
                                                                                <img src="../uploads/profile/<?= $therapist['image_url'] ?>" 
                                                                                     alt="รูปปัจจุบัน" 
                                                                                     class="img-thumbnail mb-2"
                                                                                     style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                                                                <br>
                                                                                <small class="text-muted">รูปภาพปัจจุบัน</small>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <input type="file" name="profile_image" class="form-control" accept="image/*" 
                                                                               id="editImageInput<?= $therapist['therapist_id'] ?>">
                                                                        <div class="form-text">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรูปภาพ</div>
                                                                        <div id="editImagePreview<?= $therapist['therapist_id'] ?>" class="mt-3 text-center d-none">
                                                                            <img src="" alt="Preview" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                                                        </div>
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">บริการที่เชี่ยวชาญ <span class="text-danger">*</span></label>
                                                                        <div class="border rounded p-3 overflow-auto" style="max-height: 200px;">
                                                                            <?php
                                                                            // ดึงบริการที่เชี่ยวชาญปัจจุบัน
                                                                            $current_types = [];
                                                                            $current_sql = "SELECT massage_type_id FROM therapist_massage_types WHERE therapist_id = '{$therapist['therapist_id']}'";
                                                                            $current_result = $conn->query($current_sql);
                                                                            while ($row = $current_result->fetch_assoc()) {
                                                                                $current_types[] = $row['massage_type_id'];
                                                                            }
                                                                            ?>

                                                                            <?php foreach ($massage_types as $type): ?>
                                                                                <div class="form-check mb-2">
                                                                                    <input class="form-check-input" type="checkbox" 
                                                                                           name="massage_types[]" 
                                                                                           value="<?= $type['massage_type_id'] ?>"
                                                                                           id="edit_type_<?= $therapist['therapist_id'] ?>_<?= $type['massage_type_id'] ?>"
                                                                                           <?= in_array($type['massage_type_id'], $current_types) ? 'checked' : '' ?>>
                                                                                    <label class="form-check-label d-flex justify-content-between w-100" 
                                                                                           for="edit_type_<?= $therapist['therapist_id'] ?>_<?= $type['massage_type_id'] ?>">
                                                                                        <span><?= $type['name'] ?></span>
                                                                                        <small class="text-muted"><?= $type['duration_minutes'] ?> นาที</small>
                                                                                    </label>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
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
                                        <div class="modal fade" id="deleteModal<?= $therapist['therapist_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">
                                                            <i class="bi bi-exclamation-triangle"></i> ลบหมอนวด
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="delete_therapist" value="1">
                                                        <input type="hidden" name="therapist_id" value="<?= $therapist['therapist_id'] ?>">
                                                        <div class="modal-body">
                                                            <p>คุณแน่ใจว่าต้องการลบหมอนวด <strong>"<?= $therapist['full_name'] ?>"</strong>?</p>
                                                            <div class="alert alert-warning">
                                                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                                                <strong>คำเตือน:</strong> การกระทำนี้ไม่สามารถย้อนกลับได้ และอาจส่งผลต่อการจองที่เกี่ยวข้อง!
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
    // Image preview for add form
    document.getElementById('addImageInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('addImagePreview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.querySelector('img').src = e.target.result;
                preview.classList.remove('d-none');
            }
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
        }
    });

    // Image preview for edit forms
    document.querySelectorAll('[id^="editImageInput"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const therapistId = this.id.replace('editImageInput', '');
            const file = e.target.files[0];
            const preview = document.getElementById('editImagePreview' + therapistId);
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.querySelector('img').src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('d-none');
            }
        });
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>