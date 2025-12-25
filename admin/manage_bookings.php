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
$dbHelper = new DatabaseHelper($conn);

// รับค่าตัวกรองจาก URL parameters
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
$filter_search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_payment = isset($_GET['payment_status']) ? $conn->real_escape_string($_GET['payment_status']) : '';

// สร้าง SQL query พื้นฐาน
$sql = "SELECT b.*, u.full_name as customer_name, u.phone as customer_phone,
               t.full_name as therapist_name, mt.name as service_name, mt.duration_minutes,
               p.payment_id, p.payment_status, p.payment_method, p.payment_slip, p.amount as payment_amount
        FROM bookings b
        JOIN users u ON b.customer_id = u.user_id
        JOIN therapists t ON b.therapist_id = t.therapist_id
        JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
        LEFT JOIN payments p ON b.booking_id = p.booking_id
        WHERE 1=1";

// เพิ่มเงื่อนไขการกรอง
$where_conditions = [];

if (!empty($filter_status)) {
    $where_conditions[] = "b.status = '$filter_status'";
}

if (!empty($filter_date)) {
    $where_conditions[] = "b.booking_date = '$filter_date'";
}

if (!empty($filter_search)) {
    $search_condition = "(u.full_name LIKE '%$filter_search%' OR 
                         u.phone LIKE '%$filter_search%' OR 
                         t.full_name LIKE '%$filter_search%' OR 
                         mt.name LIKE '%$filter_search%' OR 
                         b.booking_id = '$filter_search')";
    $where_conditions[] = $search_condition;
}

if (!empty($filter_payment)) {
    if ($filter_payment == 'no_payment') {
        $where_conditions[] = "p.payment_id IS NULL";
    } else {
        $where_conditions[] = "p.payment_status = '$filter_payment'";
    }
}

// รวมเงื่อนไขทั้งหมด
if (!empty($where_conditions)) {
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY b.booking_id DESC";

// ดึงจำนวนการจองทั้งหมดสำหรับสถิติ
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// ดึงการจองตามเงื่อนไขการกรอง
$result = $conn->query($sql);
$bookings = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// อัพเดทสถานะการจอง
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        $validator->required('booking_id', $_POST['booking_id'] ?? '', 'รหัสการจอง');
        $validator->required('status', $_POST['status'] ?? '', 'สถานะการจอง');

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $booking_id = intval($_POST['booking_id']);
            $status = $_POST['status'];

            // Validate status value
            $allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
            if (!in_array($status, $allowed_statuses)) {
                throw new Exception('สถานะการจองไม่ถูกต้อง');
            }

            // Check if booking exists
            $check_sql = "SELECT booking_id FROM bookings WHERE booking_id = ?";
            $existing = $dbHelper->fetchOne($check_sql, [$booking_id], 'i');
            
            if (!$existing) {
                throw new Exception('ไม่พบข้อมูลการจองที่ต้องการอัพเดท');
            }

            // Update booking status
            $update_sql = "UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?";
            $dbHelper->execute($update_sql, [$status, $booking_id], 'si');

            $success = 'อัพเดทสถานะการจองสำเร็จ';
            
            // โหลดข้อมูลใหม่
            $result = $conn->query($sql);
            $bookings = [];
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $bookings[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Booking status update error: ' . $e->getMessage(), [
            'booking_id' => $_POST['booking_id'] ?? 'N/A',
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// อัพเดทสถานะการชำระเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment_status'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate input
        $validator = new Validator();
        $validator->required('payment_id', $_POST['payment_id'] ?? '', 'รหัสการชำระเงิน');
        $validator->required('payment_status', $_POST['payment_status'] ?? '', 'สถานะการชำระเงิน');

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $payment_id = intval($_POST['payment_id']);
            $payment_status = $_POST['payment_status'];

            // Validate payment status value
            $allowed_payment_statuses = ['pending', 'completed', 'failed', 'refunded'];
            if (!in_array($payment_status, $allowed_payment_statuses)) {
                throw new Exception('สถานะการชำระเงินไม่ถูกต้อง');
            }

            // Check if payment exists
            $check_sql = "SELECT payment_id FROM payments WHERE payment_id = ?";
            $existing = $dbHelper->fetchOne($check_sql, [$payment_id], 'i');
            
            if (!$existing) {
                throw new Exception('ไม่พบข้อมูลการชำระเงินที่ต้องการอัพเดท');
            }

            // Update payment status
            if ($payment_status == 'completed') {
                $update_sql = "UPDATE payments SET payment_status = ?, paid_at = NOW() WHERE payment_id = ?";
            } else {
                $update_sql = "UPDATE payments SET payment_status = ? WHERE payment_id = ?";
            }
            $dbHelper->execute($update_sql, [$payment_status, $payment_id], 'si');

            $success = 'อัพเดทสถานะการชำระเงินสำเร็จ';
            
            // โหลดข้อมูลใหม่
            $result = $conn->query($sql);
            $bookings = [];
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $bookings[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Payment status update error: ' . $e->getMessage(), [
            'payment_id' => $_POST['payment_id'] ?? 'N/A',
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// สร้าง URL สำหรับล้างตัวกรอง
$clear_filters_url = "manage_bookings.php";
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-calendar-check me-2"></i>จัดการการจอง</h1>
                <p class="text-muted mb-0">ระบบจัดการการจองนวดทั้งหมดในระบบ</p>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองการจอง</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">สถานะการจอง</label>
                        <select name="status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                            <option value="confirmed" <?= $filter_status == 'confirmed' ? 'selected' : '' ?>>ยืนยันแล้ว</option>
                            <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                            <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">สถานะการชำระเงิน</label>
                        <select name="payment_status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="no_payment" <?= $filter_payment == 'no_payment' ? 'selected' : '' ?>>ยังไม่ชำระ</option>
                            <option value="pending" <?= $filter_payment == 'pending' ? 'selected' : '' ?>>รอตรวจสอบ</option>
                            <option value="completed" <?= $filter_payment == 'completed' ? 'selected' : '' ?>>ชำระแล้ว</option>
                            <option value="failed" <?= $filter_payment == 'failed' ? 'selected' : '' ?>>ล้มเหลว</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">วันที่จอง</label>
                        <input type="date" name="date" class="form-control" value="<?= $filter_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="ค้นหาชื่อลูกค้า, เบอร์โทร, หมอนวด, บริการ, หรือรหัสการจอง"
                            value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-1"></i>กรองข้อมูล
                        </button>
                        <a href="<?= $clear_filters_url ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>ล้างตัวกรอง
                        </a>
                    </div>
                </form>

                <!-- Active Filters -->
                <?php if ($filter_status || $filter_date || $filter_search || $filter_payment): ?>
                    <div class="mt-3">
                        <h6 class="text-muted"><i class="bi bi-info-circle me-2"></i>ตัวกรองที่ใช้งานอยู่:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($filter_status): ?>
                                <span class="badge bg-primary">
                                    สถานะ: <?= $filter_status ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>"
                                        class="text-white ms-1 text-decoration-none">×</a>
                                </span>
                            <?php endif; ?>
                            <?php if ($filter_payment): ?>
                                <span class="badge bg-primary">
                                    ชำระเงิน: <?= $filter_payment == 'no_payment' ? 'ยังไม่ชำระ' : $filter_payment ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['payment_status' => ''])) ?>"
                                        class="text-white ms-1 text-decoration-none">×</a>
                                </span>
                            <?php endif; ?>
                            <?php if ($filter_date): ?>
                                <span class="badge bg-primary">
                                    วันที่: <?= $filter_date ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['date' => ''])) ?>"
                                        class="text-white ms-1 text-decoration-none">×</a>
                                </span>
                            <?php endif; ?>
                            <?php if ($filter_search): ?>
                                <span class="badge bg-primary">
                                    ค้นหา: <?= htmlspecialchars($filter_search) ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>"
                                        class="text-white ms-1 text-decoration-none">×</a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>เกิดข้อผิดพลาด!</strong> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>สำเร็จ!</strong> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ทั้งหมด</h6>
                                <h2 class="card-title mb-0"><?= $stats['total'] ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-calendar-check"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">ยืนยันแล้ว</h6>
                                <h2 class="card-title mb-0"><?= $stats['confirmed'] ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-check-circle"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">รอดำเนินการ</h6>
                                <h2 class="card-title mb-0"><?= $stats['pending'] ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">เสร็จสิ้น</h6>
                                <h2 class="card-title mb-0"><?= $stats['completed'] ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-calendar-check-fill"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">ยกเลิก</h6>
                                <h2 class="card-title mb-0"><?= $stats['cancelled'] ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-x-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">แสดงผล</h6>
                                <h2 class="card-title mb-0"><?= count($bookings) ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Table -->
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-table me-2"></i>การจองทั้งหมด</h5>
                <span class="badge bg-light text-dark">แสดง <?= count($bookings) ?> รายการ</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                        <h5>ไม่พบข้อมูลการจอง</h5>
                        <p class="text-muted">ไม่มีการจองที่ตรงกับเงื่อนไขการกรอง</p>
                        <a href="<?= $clear_filters_url ?>" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i>ดูการจองทั้งหมด
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>รหัส</th>
                                    <th>ลูกค้า</th>
                                    <th>บริการ</th>
                                    <th>หมอนวด</th>
                                    <th>วันที่/เวลา</th>
                                    <th>ราคา</th>
                                    <th>สถานะ</th>
                                    <th>ชำระเงิน</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><strong>#<?= $booking['booking_id'] ?></strong></td>
                                        <td>
                                            <strong><?= $booking['customer_name'] ?></strong><br>
                                            <small class="text-muted"><?= $booking['customer_phone'] ?></small>
                                        </td>
                                        <td><?= $booking['service_name'] ?></td>
                                        <td><?= $booking['therapist_name'] ?></td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($booking['booking_date'])) ?><br>
                                            <small class="text-muted"><?= substr($booking['start_time'], 0, 5) ?> - <?= substr($booking['end_time'], 0, 5) ?></small>
                                        </td>
                                        <td><strong>฿<?= number_format($booking['total_price'], 2) ?></strong></td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'confirmed' => 'bg-info',
                                                'pending' => 'bg-warning text-dark',
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                                'no_show' => 'bg-secondary'
                                            ];
                                            $badge_class = $status_badges[$booking['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= $booking['status'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($booking['payment_status']): ?>
                                                <?php
                                                $payment_badges = [
                                                    'completed' => 'bg-success',
                                                    'pending' => 'bg-warning text-dark',
                                                    'failed' => 'bg-danger',
                                                    'refunded' => 'bg-secondary'
                                                ];
                                                $payment_badge_class = $payment_badges[$booking['payment_status']] ?? 'bg-secondary';
                                                ?>
                                                <span class="badge <?= $payment_badge_class ?>"><?= $booking['payment_status'] ?></span>
                                                <?php if ($booking['payment_amount']): ?>
                                                    <br><small class="text-muted">฿<?= number_format($booking['payment_amount'], 2) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">ยังไม่ชำระ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary dropdown-toggle"
                                                    data-bs-toggle="dropdown">
                                                    <i class="bi bi-gear me-1"></i>จัดการ
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item" data-bs-toggle="modal"
                                                            data-bs-target="#statusModal<?= $booking['booking_id'] ?>">
                                                            <i class="bi bi-arrow-repeat me-2"></i>เปลี่ยนสถานะการจอง
                                                        </button>
                                                    </li>
                                                    <?php if ($booking['payment_id']): ?>
                                                        <li>
                                                            <button class="dropdown-item" data-bs-toggle="modal"
                                                                data-bs-target="#paymentModal<?= $booking['booking_id'] ?>">
                                                                <i class="bi bi-credit-card me-2"></i>จัดการการชำระเงิน
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($booking['payment_slip'])): ?>
                                                        <li>
                                                            <button class="dropdown-item" data-bs-toggle="modal"
                                                                data-bs-target="#slipModal<?= $booking['booking_id'] ?>">
                                                                <i class="bi bi-receipt me-2"></i>ดูสลิป
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>

                                            <!-- Status Modal -->
                                            <div class="modal fade" id="statusModal<?= $booking['booking_id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title">
                                                                <i class="bi bi-arrow-repeat me-2"></i>เปลี่ยนสถานะการจอง #<?= $booking['booking_id'] ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" id="statusForm<?= $booking['booking_id'] ?>" class="needs-validation" novalidate>
                                                            <input type="hidden" name="update_status" value="1">
                                                            <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                                            <?= csrfField() ?>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label class="form-label">สถานะการจอง <span class="text-danger">*</span></label>
                                                                    <select name="status" class="form-select" required>
                                                                        <option value="pending" <?= $booking['status'] == 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                                                                        <option value="confirmed" <?= $booking['status'] == 'confirmed' ? 'selected' : '' ?>>ยืนยันแล้ว</option>
                                                                        <option value="completed" <?= $booking['status'] == 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                                                                        <option value="cancelled" <?= $booking['status'] == 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
                                                                        <option value="no_show" <?= $booking['status'] == 'no_show' ? 'selected' : '' ?>>ไม่มาตามนัด</option>
                                                                    </select>
                                                                    <div class="invalid-feedback">กรุณาเลือกสถานะการจอง</div>
                                                                </div>
                                                                <div class="alert alert-info">
                                                                    <i class="bi bi-info-circle me-2"></i>
                                                                    <strong>รายละเอียดการจอง:</strong><br>
                                                                    ลูกค้า: <?= $booking['customer_name'] ?><br>
                                                                    บริการ: <?= $booking['service_name'] ?><br>
                                                                    หมอนวด: <?= $booking['therapist_name'] ?><br>
                                                                    วันที่: <?= date('d/m/Y', strtotime($booking['booking_date'])) ?><br>
                                                                    เวลา: <?= substr($booking['start_time'], 0, 5) ?> - <?= substr($booking['end_time'], 0, 5) ?>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="bi bi-check-circle me-1"></i>อัพเดทสถานะ
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Payment Modal -->
                                            <?php if ($booking['payment_id']): ?>
                                                <div class="modal fade" id="paymentModal<?= $booking['booking_id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title">
                                                                    <i class="bi bi-credit-card me-2"></i>จัดการการชำระเงิน #<?= $booking['payment_id'] ?>
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST" id="paymentForm<?= $booking['booking_id'] ?>" class="needs-validation" novalidate>
                                                                <input type="hidden" name="update_payment_status" value="1">
                                                                <input type="hidden" name="payment_id" value="<?= $booking['payment_id'] ?>">
                                                                <?= csrfField() ?>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">สถานะการชำระเงิน <span class="text-danger">*</span></label>
                                                                        <select name="payment_status" class="form-select" required>
                                                                            <option value="pending" <?= $booking['payment_status'] == 'pending' ? 'selected' : '' ?>>รอชำระ</option>
                                                                            <option value="completed" <?= $booking['payment_status'] == 'completed' ? 'selected' : '' ?>>ชำระแล้ว</option>
                                                                            <option value="failed" <?= $booking['payment_status'] == 'failed' ? 'selected' : '' ?>>ล้มเหลว</option>
                                                                            <option value="refunded" <?= $booking['payment_status'] == 'refunded' ? 'selected' : '' ?>>คืนเงินแล้ว</option>
                                                                        </select>
                                                                        <div class="invalid-feedback">กรุณาเลือกสถานะการชำระเงิน</div>
                                                                    </div>
                                                                    <div class="alert alert-info">
                                                                        <i class="bi bi-info-circle me-2"></i>
                                                                        <strong>รายละเอียดการชำระเงิน:</strong><br>
                                                                        การจอง: #<?= $booking['booking_id'] ?><br>
                                                                        ลูกค้า: <?= $booking['customer_name'] ?><br>
                                                                        จำนวนเงิน: ฿<?= number_format($booking['payment_amount'] ?: $booking['total_price'], 2) ?><br>
                                                                        วิธีการชำระ: <?= $booking['payment_method'] ?><br>
                                                                        <?php if (!empty($booking['payment_slip'])): ?>
                                                                            <a href="#" data-bs-toggle="modal" data-bs-target="#slipModal<?= $booking['booking_id'] ?>">
                                                                                <i class="bi bi-eye me-1"></i>ดูสลิป
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                                    <button type="submit" class="btn btn-primary">
                                                                        <i class="bi bi-check-circle me-1"></i>อัพเดทสถานะ
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Slip Modal -->
                                            <?php if (!empty($booking['payment_slip'])): ?>
                                                <div class="modal fade" id="slipModal<?= $booking['booking_id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title">
                                                                    <i class="bi bi-receipt me-2"></i>สลิปการชำระเงิน #<?= $booking['booking_id'] ?>
                                                                </h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-center">
                                                                <img src="../uploads/payment_slips/<?= htmlspecialchars($booking['payment_slip']) ?>"
                                                                    alt="Slip #<?= $booking['booking_id'] ?>"
                                                                    class="img-fluid rounded shadow"
                                                                    onerror="this.onerror=null; this.src='../assets/images/no-image.png'; this.alt='ไม่พบรูปสลิป';">
                                                                <div class="mt-3">
                                                                    <a href="../uploads/payment_slips/<?= htmlspecialchars($booking['payment_slip']) ?>" 
                                                                       target="_blank" class="btn btn-outline-primary">
                                                                        <i class="bi bi-box-arrow-up-right me-1"></i>เปิดในแท็บใหม่
                                                                    </a>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
    <script>
        // Client-side validation for status forms
        document.addEventListener('DOMContentLoaded', function() {
            // Validate all status forms
            document.querySelectorAll('[id^="statusForm"]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const statusSelect = form.querySelector('select[name="status"]');
                    if (!statusSelect.value) {
                        e.preventDefault();
                        alert('กรุณาเลือกสถานะการจอง');
                    }
                });
            });

            // Validate all payment forms
            document.querySelectorAll('[id^="paymentForm"]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const paymentStatusSelect = form.querySelector('select[name="payment_status"]');
                    if (!paymentStatusSelect.value) {
                        e.preventDefault();
                        alert('กรุณาเลือกสถานะการชำระเงิน');
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>