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

// สร้าง DatabaseHelper instance
$db = new DatabaseHelper($conn);

// รับค่าการกรองจากฟอร์ม
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// สร้างเงื่อนไข WHERE สำหรับการกรอง
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_role)) {
    $where_conditions[] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

if (!empty($filter_status)) {
    $where_conditions[] = "is_active = ?";
    $params[] = $filter_status;
    $types .= 'i';
}

if (!empty($filter_search)) {
    $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$filter_search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

// รวมเงื่อนไขทั้งหมด
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

// ดึงข้อมูลผู้ใช้ทั้งหมด โดยแยกประเภท
$customers = [];
$admins = [];
$all_users = [];
$sql = "SELECT * FROM users $where_clause ORDER BY role, created_at DESC";

try {
    $all_users = $db->fetchAll($sql, $params, $types);
    foreach ($all_users as $row) {
        if ($row['role'] == 'admin') {
            $admins[] = $row;
        } else {
            $customers[] = $row;
        }
    }
} catch (Exception $e) {
    logError('Error fetching users: ' . $e->getMessage());
    $error = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
    $all_users = [];
}

// นับจำนวนผู้ใช้ทั้งหมดและตามประเภท
$total_users = count($all_users);
$total_admins = count($admins);
$total_customers = count($customers);
$total_active = 0;
$total_inactive = 0;

foreach ($all_users as $user) {
    if ($user['is_active']) {
        $total_active++;
    } else {
        $total_inactive++;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Session หมดอายุ กรุณา refresh หน้าและลองใหม่';
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];

        // Validate input
        $validator = new Validator();
        $validator->required('username', $username, 'ชื่อผู้ใช้');
        $validator->required('email', $email, 'อีเมล');
        $validator->email('email', $email, 'อีเมล');
        $validator->required('password', $password, 'รหัสผ่าน');
        
        // Password strength validation
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $validator->addError('password', 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $validator->addError('password', 'รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว');
            } elseif (!preg_match('/[a-z]/', $password)) {
                $validator->addError('password', 'รหัสผ่านต้องมีตัวอักษรพิมพ์เล็กอย่างน้อย 1 ตัว');
            } elseif (!preg_match('/[0-9]/', $password)) {
                $validator->addError('password', 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว');
            }
        }
        
        $validator->required('full_name', $full_name, 'ชื่อ-นามสกุล');
        
        if (!empty($phone)) {
            $validator->phone('phone', $phone, 'เบอร์โทร');
        }
        
        // Validate role
        if (!in_array($role, ['admin', 'customer'])) {
            $validator->addError('role', 'บทบาทไม่ถูกต้อง');
        }

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            try {
                // ตรวจสอบว่ามีผู้ใช้อยู่แล้วหรือไม่
                $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
                $existing = $db->fetchOne($check_sql, [$username, $email], 'ss');

                if ($existing) {
                    $error = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
                } else {
                    // เข้ารหัสรหัสผ่านด้วย password_hash เพื่อความปลอดภัย
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $data = [
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => $password_hash,
                        'full_name' => $full_name,
                        'phone' => $phone,
                        'role' => $role
                    ];

                    $db->insert('users', $data);
                    $success = 'เพิ่มผู้ใช้สำเร็จ';
                    // โหลดข้อมูลใหม่
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
            } catch (Exception $e) {
                logError('Error adding user: ' . $e->getMessage(), ['username' => $username]);
                $error = 'เกิดข้อผิดพลาดในการเพิ่มผู้ใช้';
            }
        }
    }
}

// อัพเดทสถานะผู้ใช้
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Session หมดอายุ กรุณา refresh หน้าและลองใหม่';
    } else {
        $user_id = intval($_POST['user_id']);
        $is_active = intval($_POST['is_active']);
        $role = $_POST['role'];

        // Validate input
        $validator = new Validator();
        
        if ($user_id <= 0) {
            $validator->addError('user_id', 'รหัสผู้ใช้ไม่ถูกต้อง');
        }
        
        if (!in_array($is_active, [0, 1])) {
            $validator->addError('is_active', 'สถานะไม่ถูกต้อง');
        }
        
        if (!in_array($role, ['admin', 'customer'])) {
            $validator->addError('role', 'บทบาทไม่ถูกต้อง');
        }

        // ป้องกันการเปลี่ยนสถานะตัวเอง
        if ($user_id == $_SESSION['user_id']) {
            $error = 'ไม่สามารถเปลี่ยนสถานะบัญชีของตัวเองได้';
        } elseif ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            try {
                // ตรวจสอบว่า user_id มีอยู่จริง
                $check_sql = "SELECT user_id FROM users WHERE user_id = ?";
                $existing = $db->fetchOne($check_sql, [$user_id], 'i');
                
                if (!$existing) {
                    $error = 'ไม่พบผู้ใช้ที่ต้องการแก้ไข';
                } else {
                    $data = [
                        'is_active' => $is_active,
                        'role' => $role,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    $db->update('users', $data, 'user_id = ?', [$user_id], 'i');
                    $success = 'อัพเดทข้อมูลผู้ใช้สำเร็จ';
                    // โหลดข้อมูลใหม่
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
            } catch (Exception $e) {
                logError('Error updating user: ' . $e->getMessage(), ['user_id' => $user_id]);
                $error = 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล';
            }
        }
    }
}

// ลบผู้ใช้
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Session หมดอายุ กรุณา refresh หน้าและลองใหม่';
    } else {
        $user_id = intval($_POST['user_id']);

        // Validate user_id
        if ($user_id <= 0) {
            $error = 'รหัสผู้ใช้ไม่ถูกต้อง';
        } elseif ($user_id == $_SESSION['user_id']) {
            // ป้องกันการลบตัวเอง
            $error = 'ไม่สามารถลบบัญชีของตัวเองได้';
        } else {
            try {
                // ตรวจสอบว่า user_id มีอยู่จริง
                $check_sql = "SELECT user_id FROM users WHERE user_id = ?";
                $existing = $db->fetchOne($check_sql, [$user_id], 'i');
                
                if (!$existing) {
                    $error = 'ไม่พบผู้ใช้ที่ต้องการลบ';
                } else {
                    $db->delete('users', 'user_id = ?', [$user_id], 'i');
                    $success = 'ลบผู้ใช้สำเร็จ';
                    // โหลดข้อมูลใหม่
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
            } catch (Exception $e) {
                logError('Error deleting user: ' . $e->getMessage(), ['user_id' => $user_id]);
                $error = 'เกิดข้อผิดพลาดในการลบผู้ใช้';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><i class="bi bi-people-fill me-2"></i>จัดการผู้ใช้</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle me-2"></i>เพิ่มผู้ใช้ใหม่
            </button>
        </div>

        <!-- Alerts -->
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

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ผู้ใช้ทั้งหมด</h6>
                                <h2 class="card-title mb-0"><?= $total_users ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ใช้งาน</h6>
                                <h2 class="card-title mb-0"><?= $total_active ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ระงับ</h6>
                                <h2 class="card-title mb-0"><?= $total_inactive ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-x-circle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-dark-50">ผู้ดูแล</h6>
                                <h2 class="card-title mb-0 text-dark"><?= $total_admins ?></h2>
                            </div>
                            <div class="fs-1 opacity-50 text-dark">
                                <i class="bi bi-shield-fill-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ลูกค้า</h6>
                                <h2 class="card-title mb-0"><?= $total_customers ?></h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>ตัวกรองข้อมูล</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="role" class="form-label">บทบาท</label>
                        <select name="role" id="role" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                            <option value="customer" <?= $filter_role == 'customer' ? 'selected' : '' ?>>ลูกค้า</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">สถานะ</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>ใช้งาน</option>
                            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>ระงับ</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">วันที่เริ่มต้น</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?= $filter_date_from ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?= $filter_date_to ?>">
                    </div>
                    <div class="col-12">
                        <label for="search" class="form-label">ค้นหา</label>
                        <input type="text" name="search" id="search" class="form-control" 
                            placeholder="ค้นหาด้วยชื่อผู้ใช้, ชื่อ-นามสกุล, อีเมล, เบอร์โทร..." value="<?= $filter_search ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>ค้นหา
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>ล้างตัวกรอง
                        </a>
                        <span class="text-muted ms-3">
                            พบข้อมูลทั้งหมด <?= $total_users ?> รายการ
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users List Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>รายการผู้ใช้ทั้งหมด
                    <?php if (!empty($where_conditions)): ?>
                        <span class="badge bg-info ms-2">กำลังกรองข้อมูล</span>
                    <?php endif; ?>
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark">ทั้งหมด: <?= $total_users ?></span>
                    <span class="badge bg-warning text-dark">ผู้ดูแล: <?= $total_admins ?></span>
                    <span class="badge bg-info">ลูกค้า: <?= $total_customers ?></span>
                </div>
            </div>
            <div class="card-body p-0">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs px-3 pt-3" id="userTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all"
                                    type="button" role="tab">
                                    ผู้ใช้ทั้งหมด
                                    <span class="badge bg-primary"><?= $total_users ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins"
                                    type="button" role="tab">
                                    ผู้ดูแลระบบ
                                    <span class="badge bg-danger"><?= $total_admins ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="customers-tab" data-bs-toggle="tab"
                                    data-bs-target="#customers" type="button" role="tab">
                                    ลูกค้า
                                    <span class="badge bg-success"><?= $total_customers ?></span>
                                </button>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content p-3">
                            <!-- แท็บผู้ใช้ทั้งหมด -->
                            <div class="tab-pane fade show active" id="all" role="tabpanel">
                                <?php if (count($all_users) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ชื่อผู้ใช้</th>
                                                    <th>ชื่อ-นามสกุล</th>
                                                    <th>อีเมล</th>
                                                    <th>เบอร์โทร</th>
                                                    <th>บทบาท</th>
                                                    <th>สถานะ</th>
                                                    <th>วันที่สมัคร</th>
                                                    <th>การดำเนินการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_users as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td><?= htmlspecialchars($user['phone']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $user['role'] == 'admin' ? 'bg-warning text-dark' : 'bg-info' ?>">
                                                                <?= $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 'ลูกค้า' ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                                <?= $user['is_active'] ? 'ใช้งาน' : 'ระงับ' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                                                    data-bs-target="#editModal<?= $user['user_id'] ?>">
                                                                    <i class="bi bi-pencil-fill"></i>
                                                                </button>
                                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                                        data-bs-target="#deleteModal<?= $user['user_id'] ?>">
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-secondary btn-sm" disabled>
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-people-fill fs-1 text-muted mb-3"></i>
                                        <h5 class="text-muted">ไม่พบข้อมูลผู้ใช้</h5>
                                        <?php if (!empty($where_conditions)): ?>
                                            <p class="text-muted">ลองเปลี่ยนเงื่อนไขการค้นหาหรือ <a href="<?= $_SERVER['PHP_SELF'] ?>">ล้างตัวกรอง</a></p>
                                        <?php else: ?>
                                            <p class="text-muted">ยังไม่มีข้อมูลผู้ใช้ในระบบ</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- แท็บผู้ดูแลระบบ -->
                            <div class="tab-pane fade" id="admins" role="tabpanel">
                                <?php
                                $admin_users = array_filter($all_users, function ($user) {
                                    return $user['role'] == 'admin';
                                });
                                ?>
                                <?php if (count($admin_users) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ชื่อผู้ใช้</th>
                                                    <th>ชื่อ-นามสกุล</th>
                                                    <th>อีเมล</th>
                                                    <th>เบอร์โทร</th>
                                                    <th>สถานะ</th>
                                                    <th>วันที่สมัคร</th>
                                                    <th>การดำเนินการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($admin_users as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td><?= htmlspecialchars($user['phone']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                                <?= $user['is_active'] ? 'ใช้งาน' : 'ระงับ' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                                                    data-bs-target="#editModal<?= $user['user_id'] ?>">
                                                                    <i class="bi bi-pencil-fill"></i>
                                                                </button>
                                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                                        data-bs-target="#deleteModal<?= $user['user_id'] ?>">
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-secondary btn-sm" disabled>
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-shield-fill-check fs-1 text-muted mb-3"></i>
                                        <h5 class="text-muted">ไม่พบข้อมูลผู้ดูแลระบบ</h5>
                                        <p class="text-muted">ยังไม่มีผู้ดูแลระบบในระบบ</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- แท็บลูกค้า -->
                            <div class="tab-pane fade" id="customers" role="tabpanel">
                                <?php
                                $customer_users = array_filter($all_users, function ($user) {
                                    return $user['role'] == 'customer';
                                });
                                ?>
                                <?php if (count($customer_users) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ชื่อผู้ใช้</th>
                                                    <th>ชื่อ-นามสกุล</th>
                                                    <th>อีเมล</th>
                                                    <th>เบอร์โทร</th>
                                                    <th>สถานะ</th>
                                                    <th>วันที่สมัคร</th>
                                                    <th>การดำเนินการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($customer_users as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td><?= htmlspecialchars($user['phone']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                                                <?= $user['is_active'] ? 'ใช้งาน' : 'ระงับ' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                                                    data-bs-target="#editModal<?= $user['user_id'] ?>">
                                                                    <i class="bi bi-pencil-fill"></i>
                                                                </button>
                                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                                        data-bs-target="#deleteModal<?= $user['user_id'] ?>">
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-secondary btn-sm" disabled>
                                                                        <i class="bi bi-trash-fill"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-person-fill fs-1 text-muted mb-3"></i>
                                        <h5 class="text-muted">ไม่พบข้อมูลลูกค้า</h5>
                                        <p class="text-muted">ยังไม่มีลูกค้าในระบบ</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับเพิ่มผู้ใช้ใหม่ -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>เพิ่มผู้ใช้ใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="add_user" value="1">
                    <?= csrfField() ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required minlength="3">
                            <div class="invalid-feedback">กรุณากรอกชื่อผู้ใช้ (อย่างน้อย 3 ตัวอักษร)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">อีเมล <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                            <div class="invalid-feedback">กรุณากรอกอีเมลที่ถูกต้อง</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="add_password" class="form-control" required minlength="8">
                            <div class="form-text">รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร ประกอบด้วยตัวพิมพ์ใหญ่ พิมพ์เล็ก และตัวเลข</div>
                            <div class="invalid-feedback">รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                            <div class="invalid-feedback">กรุณากรอกชื่อ-นามสกุล</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">เบอร์โทร</label>
                            <input type="tel" name="phone" class="form-control" pattern="[0-9]{10}">
                            <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">บทบาท <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="customer">ลูกค้า</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกบทบาท</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i>เพิ่มผู้ใช้
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modals สำหรับแก้ไขและลบผู้ใช้ -->
    <?php foreach ($all_users as $user): ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $user['user_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title text-dark">
                            <i class="bi bi-pencil-square me-2"></i>แก้ไขผู้ใช้: <?= htmlspecialchars($user['full_name']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="update_user" value="1">
                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                        <?= csrfField() ?>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">บทบาท <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="customer" <?= $user['role'] == 'customer' ? 'selected' : '' ?>>ลูกค้า</option>
                                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกบทบาท</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">สถานะ <span class="text-danger">*</span></label>
                                <select name="is_active" class="form-select" required>
                                    <option value="1" <?= $user['is_active'] ? 'selected' : '' ?>>ใช้งาน</option>
                                    <option value="0" <?= !$user['is_active'] ? 'selected' : '' ?>>ระงับ</option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกสถานะ</div>
                            </div>
                            <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    คุณไม่สามารถเปลี่ยนสถานะบัญชีของตัวเองได้
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                            <button type="submit" class="btn btn-primary" <?= $user['user_id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                <i class="bi bi-check-circle me-2"></i>อัพเดท
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
            <div class="modal fade" id="deleteModal<?= $user['user_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title"><i class="bi bi-trash-fill me-2"></i>ลบผู้ใช้</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="delete_user" value="1">
                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                            <?= csrfField() ?>
                            <div class="modal-body">
                                <p>คุณแน่ใจว่าต้องการลบผู้ใช้ <strong><?= htmlspecialchars($user['full_name']) ?></strong>?</p>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    การกระทำนี้ไม่สามารถย้อนกลับได้!
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash-fill me-2"></i>ลบ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>

    <script>
        // ตั้งค่าวันที่เริ่มต้นและสิ้นสุดเริ่มต้น
        document.addEventListener('DOMContentLoaded', function () {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');

            // ตั้งค่าวันที่เริ่มต้นเป็น 30 วันที่ผ่านมา
            if (!dateFrom.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                dateFrom.valueAsDate = thirtyDaysAgo;
            }

            // ตั้งค่าวันที่สิ้นสุดเป็นวันปัจจุบัน
            if (!dateTo.value) {
                dateTo.valueAsDate = new Date();
            }

            // เปิดแท็บตาม URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const role = urlParams.get('role');
            if (role === 'admin') {
                const adminTab = new bootstrap.Tab(document.getElementById('admins-tab'));
                adminTab.show();
            } else if (role === 'customer') {
                const customerTab = new bootstrap.Tab(document.getElementById('customers-tab'));
                customerTab.show();
            }

            // Password validation for add user form
            const addPasswordField = document.getElementById('add_password');
            if (addPasswordField) {
                addPasswordField.addEventListener('input', function() {
                    const password = this.value;
                    let message = '';
                    let isValid = true;

                    if (password.length < 8) {
                        message = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
                        isValid = false;
                    } else if (!/[A-Z]/.test(password)) {
                        message = 'รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว';
                        isValid = false;
                    } else if (!/[a-z]/.test(password)) {
                        message = 'รหัสผ่านต้องมีตัวอักษรพิมพ์เล็กอย่างน้อย 1 ตัว';
                        isValid = false;
                    } else if (!/[0-9]/.test(password)) {
                        message = 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว';
                        isValid = false;
                    }

                    // Remove existing feedback
                    const existingFeedback = this.parentNode.querySelector('.invalid-feedback');
                    if (existingFeedback) {
                        existingFeedback.remove();
                    }
                    this.classList.remove('is-invalid', 'is-valid');

                    // Add feedback
                    if (password.length > 0) {
                        if (isValid) {
                            this.classList.add('is-valid');
                        } else {
                            this.classList.add('is-invalid');
                            const feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            feedback.textContent = message;
                            this.parentNode.appendChild(feedback);
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>