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

// สร้าง instances
$validator = new Validator();
$dbHelper = new DatabaseHelper($conn);

// ตั้งค่าโฟลเดอร์สำหรับเก็บสลิปการชำระเงินและใบเสร็จรับเงิน
$upload_dir = '../uploads/payment_slips/';
$receipt_dir = '../uploads/receipts/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_dir($receipt_dir)) {
    mkdir($receipt_dir, 0755, true);
}

$error = '';
$success = '';

// ฟังก์ชันสำหรับอัพโหลดสลิป
function uploadPaymentSlip($file, $upload_dir, $existing_slip = null)
{
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return $existing_slip; // ไม่มีไฟล์ใหม่, ใช้ไฟล์เดิม
        }
        throw new Exception('เกิดข้อผิดพลาดในการอัพโหลดไฟล์: ' . $file['error']);
    }

    // ตรวจสอบประเภทไฟล์
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, JPG, PNG, GIF, WebP, PDF');
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > $max_size) {
        throw new Exception('ขนาดไฟล์ใหญ่เกินไป อนุญาตสูงสุด 5MB');
    }

    // สร้างชื่อไฟล์ใหม่
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'slip_' . time() . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    // ย้ายไฟล์ไปยังโฟลเดอร์ปลายทาง
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
    }

    // ลบไฟล์เก่าหากมีการอัพโหลดไฟล์ใหม่และมีไฟล์เก่าอยู่
    if ($existing_slip && file_exists($upload_dir . $existing_slip)) {
        unlink($upload_dir . $existing_slip);
    }

    return $new_filename;
}

// ฟังก์ชันสำหรับอัพโหลดใบเสร็จรับเงิน
function uploadReceipt($file, $receipt_dir, $existing_receipt = null)
{
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('เกิดข้อผิดพลาดในการอัพโหลดไฟล์: ' . $file['error']);
    }

    // ตรวจสอบประเภทไฟล์
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, JPG, PNG, GIF, WebP, PDF');
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > $max_size) {
        throw new Exception('ขนาดไฟล์ใหญ่เกินไป อนุญาตสูงสุด 5MB');
    }

    // สร้างชื่อไฟล์ใหม่
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $receipt_dir . $new_filename;

    // ย้ายไฟล์ไปยังโฟลเดอร์ปลายทาง
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
    }

    // ลบไฟล์เก่าหากมีการอัพโหลดไฟล์ใหม่และมีไฟล์เก่าอยู่
    if ($existing_receipt && file_exists($receipt_dir . $existing_receipt)) {
        unlink($receipt_dir . $existing_receipt);
    }

    return $new_filename;
}

// รับค่าการกรองจากฟอร์ม
$filter_status = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : '';
$filter_method = isset($_GET['method']) ? htmlspecialchars($_GET['method']) : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_customer = isset($_GET['customer']) ? htmlspecialchars($_GET['customer']) : '';
$filter_search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

// สร้างเงื่อนไข WHERE สำหรับการกรอง
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_status)) {
    $where_conditions[] = "p.payment_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_method)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $filter_method;
    $types .= 's';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

if (!empty($filter_customer)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$filter_customer%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if (!empty($filter_search)) {
    $where_conditions[] = "(p.payment_id LIKE ? OR b.booking_id LIKE ? OR u.full_name LIKE ? OR mt.name LIKE ? OR t.full_name LIKE ?)";
    $search_term = "%$filter_search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sssss';
}

// รวมเงื่อนไขทั้งหมด
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

// ดึงข้อมูลการชำระเงินทั้งหมด - แก้ไข query ให้ดึง receipt_file ด้วย
$payments = [];
$sql = "SELECT p.*, b.booking_id, b.total_price, b.status as booking_status, 
               u.full_name as customer_name, u.phone as customer_phone,
               mt.name as service_name, t.full_name as therapist_name,
               b.booking_date, b.start_time, b.end_time
        FROM payments p
        JOIN bookings b ON p.booking_id = b.booking_id
        JOIN users u ON b.customer_id = u.user_id
        JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
        JOIN therapists t ON b.therapist_id = t.therapist_id
        $where_clause
        ORDER BY p.created_at DESC";

try {
    if (!empty($params)) {
        $payments = $dbHelper->fetchAll($sql, $params, $types);
    } else {
        $payments = $dbHelper->fetchAll($sql);
    }
} catch (Exception $e) {
    logError('Error fetching payments: ' . $e->getMessage());
    $error = 'เกิดข้อผิดพลาดในการดึงข้อมูล';
    $payments = [];
}

// อัพเดทสถานะการชำระเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate payment_id
        if (!$validator->required('payment_id', $_POST['payment_id'] ?? '', 'รหัสการชำระเงิน')) {
            throw new Exception($validator->getError('payment_id'));
        }
        $payment_id = intval($_POST['payment_id']);

        // Validate payment_status
        $allowed_statuses = ['pending', 'completed', 'failed', 'refunded'];
        if (!$validator->required('payment_status', $_POST['payment_status'] ?? '', 'สถานะการชำระเงิน')) {
            throw new Exception($validator->getError('payment_status'));
        }
        $payment_status = $_POST['payment_status'];
        if (!in_array($payment_status, $allowed_statuses)) {
            throw new Exception('สถานะการชำระเงินไม่ถูกต้อง');
        }

        // ตรวจสอบว่า payment_id มีอยู่จริง
        $check_sql = "SELECT payment_id, payment_slip, amount FROM payments WHERE payment_id = ?";
        $payment_data = $dbHelper->fetchOne($check_sql, [$payment_id], 'i');
        
        if (!$payment_data) {
            throw new Exception('ไม่พบข้อมูลการชำระเงิน');
        }

        // Validate amount (should be positive)
        if ($payment_data['amount'] <= 0) {
            throw new Exception('จำนวนเงินไม่ถูกต้อง');
        }

        $current_slip = $payment_data['payment_slip'];
        $slip_filename = $current_slip;

        // Handle file upload
        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] !== UPLOAD_ERR_NO_FILE) {
            $slip_filename = uploadPaymentSlip($_FILES['payment_slip'], $upload_dir, $current_slip);
        }

        // Prepare update data
        $update_data = [
            'payment_status' => $payment_status
        ];

        if ($payment_status == 'completed') {
            $update_data['paid_at'] = date('Y-m-d H:i:s');
        }

        if ($slip_filename) {
            $update_data['payment_slip'] = $slip_filename;
        }

        // Update using prepared statement
        $dbHelper->update('payments', $update_data, 'payment_id = ?', [$payment_id], 'i');

        $success = 'อัพเดทสถานะการชำระเงินสำเร็จ';
        logError('Payment updated successfully: Payment ID ' . $payment_id);
        
        // โหลดข้อมูลใหม่
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Error updating payment: ' . $e->getMessage(), [
            'payment_id' => $_POST['payment_id'] ?? 'N/A',
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// อัพโหลดใบเสร็จรับเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_receipt'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate payment_id
        if (!$validator->required('payment_id', $_POST['payment_id'] ?? '', 'รหัสการชำระเงิน')) {
            throw new Exception($validator->getError('payment_id'));
        }
        $payment_id = intval($_POST['payment_id']);

        // ตรวจสอบว่า payment_id มีอยู่จริงและสถานะการชำระเงินเป็น completed
        $check_sql = "SELECT payment_status, receipt_file, amount FROM payments WHERE payment_id = ?";
        $check_data = $dbHelper->fetchOne($check_sql, [$payment_id], 'i');

        if (!$check_data) {
            throw new Exception('ไม่พบข้อมูลการชำระเงิน');
        }

        if ($check_data['payment_status'] != 'completed') {
            throw new Exception('สามารถแนบใบเสร็จรับเงินได้เฉพาะการชำระเงินที่เสร็จสมบูรณ์แล้วเท่านั้น');
        }

        // Validate amount
        if ($check_data['amount'] <= 0) {
            throw new Exception('จำนวนเงินไม่ถูกต้อง');
        }

        $current_receipt = $check_data['receipt_file'];

        // Validate file upload
        if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('กรุณาเลือกไฟล์ใบเสร็จรับเงิน');
        }

        $receipt_filename = uploadReceipt($_FILES['receipt_file'], $receipt_dir, $current_receipt);

        // Update using prepared statement
        $dbHelper->update('payments', ['receipt_file' => $receipt_filename], 'payment_id = ?', [$payment_id], 'i');

        $success = 'อัพโหลดใบเสร็จรับเงินสำเร็จ';
        logError('Receipt uploaded successfully: Payment ID ' . $payment_id);
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Error uploading receipt: ' . $e->getMessage(), [
            'payment_id' => $_POST['payment_id'] ?? 'N/A',
            'user_id' => $_SESSION['user_id']
        ]);
    }
}

// ลบใบเสร็จรับเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_receipt'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Session หมดอายุ กรุณา refresh หน้าและลองใหม่');
        }

        // Validate payment_id
        if (!$validator->required('payment_id', $_POST['payment_id'] ?? '', 'รหัสการชำระเงิน')) {
            throw new Exception($validator->getError('payment_id'));
        }
        $payment_id = intval($_POST['payment_id']);

        // ดึงข้อมูลใบเสร็จรับเงิน
        $receipt_sql = "SELECT receipt_file FROM payments WHERE payment_id = ?";
        $receipt_data = $dbHelper->fetchOne($receipt_sql, [$payment_id], 'i');

        if (!$receipt_data) {
            throw new Exception('ไม่พบข้อมูลการชำระเงิน');
        }

        $receipt_file = $receipt_data['receipt_file'];

        // ลบไฟล์ใบเสร็จรับเงิน
        if ($receipt_file && file_exists($receipt_dir . $receipt_file)) {
            unlink($receipt_dir . $receipt_file);
        }

        // อัพเดทฐานข้อมูล using prepared statement
        $dbHelper->update('payments', ['receipt_file' => null], 'payment_id = ?', [$payment_id], 'i');

        $success = 'ลบใบเสร็จรับเงินสำเร็จ';
        logError('Receipt deleted successfully: Payment ID ' . $payment_id);
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Error deleting receipt: ' . $e->getMessage(), [
            'payment_id' => $_POST['payment_id'] ?? 'N/A',
            'user_id' => $_SESSION['user_id']
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการชำระเงิน</title>
    <?php include '../templates/admin-head.php'; ?>
</head>
<body>
    <?php include '../templates/navbar-admin.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-credit-card me-2"></i>จัดการการชำระเงิน</h1>
                <p class="text-muted mb-0">ระบบจัดการข้อมูลการชำระเงินทั้งหมดในระบบ</p>
            </div>
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

        <!-- สถิติการชำระเงิน -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ทั้งหมด</h6>
                                <h2 class="card-title mb-0">
                                    <?php
                                    $total_sql = "SELECT COUNT(*) as total FROM payments";
                                    $total_result = $conn->query($total_sql);
                                    echo $total_result->fetch_assoc()['total'];
                                    ?>
                                </h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-credit-card"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">ชำระแล้ว</h6>
                                <h2 class="card-title mb-0">
                                    <?php
                                    $completed_sql = "SELECT COUNT(*) as total FROM payments WHERE payment_status = 'completed'";
                                    $completed_result = $conn->query($completed_sql);
                                    echo $completed_result->fetch_assoc()['total'];
                                    ?>
                                </h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-dark-50">รอชำระ</h6>
                                <h2 class="card-title mb-0 text-dark">
                                    <?php
                                    $pending_sql = "SELECT COUNT(*) as total FROM payments WHERE payment_status = 'pending'";
                                    $pending_result = $conn->query($pending_sql);
                                    echo $pending_result->fetch_assoc()['total'];
                                    ?>
                                </h2>
                            </div>
                            <div class="fs-1 opacity-50 text-dark">
                                <i class="bi bi-clock"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">รายได้รวม</h6>
                                <h2 class="card-title mb-0">
                                    ฿<?php
                                    $revenue_sql = "SELECT SUM(amount) as total FROM payments WHERE payment_status = 'completed'";
                                    $revenue_result = $conn->query($revenue_sql);
                                    echo number_format($revenue_result->fetch_assoc()['total'] ?: 0, 2);
                                    ?>
                                </h2>
                            </div>
                            <div class="fs-1 opacity-50">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ตัวกรองข้อมูล -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองข้อมูล</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">สถานะการชำระเงิน</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">ทั้งหมด</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>รอชำระ</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>ชำระแล้ว</option>
                        <option value="failed" <?= $filter_status == 'failed' ? 'selected' : '' ?>>ล้มเหลว</option>
                        <option value="refunded" <?= $filter_status == 'refunded' ? 'selected' : '' ?>>คืนเงินแล้ว</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="method" class="form-label">วิธีการชำระเงิน</label>
                    <select name="method" id="method" class="form-select">
                        <option value="">ทั้งหมด</option>
                        <option value="cash" <?= $filter_method == 'cash' ? 'selected' : '' ?>>เงินสด</option>
                        <option value="credit_card" <?= $filter_method == 'credit_card' ? 'selected' : '' ?>>บัตรเครดิต</option>
                        <option value="debit_card" <?= $filter_method == 'debit_card' ? 'selected' : '' ?>>บัตรเดบิต</option>
                        <option value="promptpay" <?= $filter_method == 'promptpay' ? 'selected' : '' ?>>พร้อมเพย์</option>
                        <option value="bank_transfer" <?= $filter_method == 'bank_transfer' ? 'selected' : '' ?>>โอนเงิน</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">วันที่เริ่มต้น</label>
                    <input type="date" name="date_from" id="date_from" class="form-control"
                        value="<?= $filter_date_from ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">วันที่สิ้นสุด</label>
                    <input type="date" name="date_to" id="date_to" class="form-control"
                        value="<?= $filter_date_to ?>">
                </div>
                <div class="col-md-2">
                    <label for="customer" class="form-label">ค้นหาลูกค้า</label>
                    <input type="text" name="customer" id="customer" class="form-control"
                        placeholder="ชื่อหรือเบอร์โทร" value="<?= $filter_customer ?>">
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">ค้นหาแบบครอบคลุม</label>
                    <input type="text" name="search" id="search" class="form-control"
                        placeholder="ค้นหาด้วยรหัส, ชื่อ, บริการ..." value="<?= $filter_search ?>">
                </div>
                <div class="col-md-12">
                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-2"></i>ค้นหา
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>ล้างตัวกรอง
                        </a>
                        <span class="text-muted ms-auto">
                            พบข้อมูลทั้งหมด <?= count($payments) ?> รายการ
                        </span>
                    </div>
                </div>
            </form>
            </div>
        </div>

        <!-- ตารางการชำระเงิน -->
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>การชำระเงินทั้งหมด</h5>
                <div>
                    <span class="badge bg-light text-dark me-2">ทั้งหมด: <?= count($payments) ?></span>
                    <?php if (!empty($where_conditions)): ?>
                    <span class="badge bg-warning text-dark">กำลังกรองข้อมูล</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($payments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสการชำระ</th>
                                <th>การจอง</th>
                                <th>ลูกค้า</th>
                                <th>บริการ</th>
                                <th>หมอนวด</th>
                                <th>จำนวนเงิน</th>
                                <th>วิธีการ</th>
                                <th>สลิป</th>
                                <th>ใบเสร็จ</th>
                                <th>สถานะ</th>
                                <th>วันที่ชำระ</th>
                                <th class="text-center">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td data-label="รหัสการชำระ" class="fw-semibold">
                                                    <span class="badge bg-secondary">#<?= $payment['payment_id'] ?></span>
                                                </td>
                                                <td data-label="การจอง">
                                                    <a href="manage_bookings.php" class="text-decoration-none">
                                                        #<?= $payment['booking_id'] ?>
                                                    </a>
                                                </td>
                                                <td data-label="ลูกค้า">
                                                    <strong class="text-dark"><?= $payment['customer_name'] ?></strong>
                                                    <?php if ($payment['customer_phone']): ?>
                                                            <br><small class="text-muted"><?= $payment['customer_phone'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="บริการ"><?= $payment['service_name'] ?></td>
                                                <td data-label="หมอนวด"><?= $payment['therapist_name'] ?></td>
                                                <td data-label="จำนวนเงิน" class="fw-bold">฿<?= number_format($payment['amount'], 2) ?></td>
                                                <td data-label="วิธีการ">
                                                    <?php
                                                    $payment_methods = [
                                                        'cash' => 'เงินสด',
                                                        'credit_card' => 'บัตรเครดิต',
                                                        'debit_card' => 'บัตรเดบิต',
                                                        'promptpay' => 'พร้อมเพย์',
                                                        'bank_transfer' => 'โอนเงิน'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-secondary">
                                                        <?= $payment_methods[$payment['payment_method']] ?? $payment['payment_method'] ?>
                                                    </span>
                                                </td>
                                                <td data-label="สลิป">
                                                    <?php if ($payment['payment_slip']): ?>
                                                            <?php
                                                            $slip_path = $upload_dir . $payment['payment_slip'];
                                                            $is_pdf = pathinfo($payment['payment_slip'], PATHINFO_EXTENSION) === 'pdf';
                                                            ?>
                                                            <?php if ($is_pdf): ?>
                                                                    <a href="<?= $slip_path ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                                                        <i class="bi bi-file-pdf"></i> PDF
                                                                    </a>
                                                            <?php else: ?>
                                                                    <img src="<?= $slip_path ?>" alt="สลิปการชำระเงิน" class="img-thumbnail" style="max-width: 60px; cursor: pointer;"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#slipModal<?= $payment['payment_id'] ?>">
                                                            <?php endif; ?>
                                                    <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                    <?php endif; ?>

                                                    <!-- Slip Modal -->
                                                    <?php if ($payment['payment_slip'] && !$is_pdf): ?>
                                                    <div class="modal fade" id="slipModal<?= $payment['payment_id'] ?>"
                                                        tabindex="-1">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="bi bi-receipt me-2"></i>
                                                                        สลิปการชำระเงิน #<?= $payment['payment_id'] ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal"></button>
                                                                </div>
                                                                        <div class="modal-body text-center">
                                                                            <img src="<?= $slip_path ?>" alt="สลิปการชำระเงิน"
                                                                                class="img-fluid">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="ใบเสร็จ">
                                                    <?php if ($payment['receipt_file']): ?>
                                                            <?php
                                                            $receipt_path = $receipt_dir . $payment['receipt_file'];
                                                            $is_receipt_pdf = pathinfo($payment['receipt_file'], PATHINFO_EXTENSION) === 'pdf';
                                                            ?>
                                                            <?php if ($is_receipt_pdf): ?>
                                                                    <a href="<?= $receipt_path ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                                        <i class="bi bi-file-pdf"></i> PDF
                                                                    </a>
                                                            <?php else: ?>
                                                                    <img src="<?= $receipt_path ?>" alt="ใบเสร็จรับเงิน"
                                                                        class="img-thumbnail" style="max-width: 60px; cursor: pointer;" data-bs-toggle="modal"
                                                                        data-bs-target="#receiptModal<?= $payment['payment_id'] ?>">
                                                            <?php endif; ?>
                                                    <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                    <?php endif; ?>

                                                    <!-- Receipt Modal -->
                                                    <?php if ($payment['receipt_file'] && !$is_receipt_pdf): ?>
                                                    <div class="modal fade" id="receiptModal<?= $payment['payment_id'] ?>"
                                                        tabindex="-1">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-success text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="bi bi-file-earmark-text me-2"></i>
                                                                        ใบเสร็จรับเงิน #<?= $payment['payment_id'] ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal"></button>
                                                                </div>
                                                                        <div class="modal-body text-center">
                                                                            <img src="<?= $receipt_path ?>" alt="ใบเสร็จรับเงิน"
                                                                                class="img-fluid">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="สถานะ">
                                                    <span class="badge bg-<?=
                                                        $payment['payment_status'] == 'completed' ? 'success' :
                                                        ($payment['payment_status'] == 'pending' ? 'warning text-dark' :
                                                            ($payment['payment_status'] == 'failed' ? 'danger' : 'info'))
                                                        ?>">
                                                        <i class="bi bi-<?=
                                                            $payment['payment_status'] == 'completed' ? 'check-circle' :
                                                            ($payment['payment_status'] == 'pending' ? 'clock' :
                                                                ($payment['payment_status'] == 'failed' ? 'x-circle' : 'arrow-counterclockwise'))
                                                            ?> me-1"></i>
                                                        <?= $payment['payment_status'] ?>
                                                    </span>
                                                </td>
                                                <td data-label="วันที่ชำระ">
                                                    <?php if ($payment['paid_at']): ?>
                                                            <small class="text-dark"><?= date('d/m/Y', strtotime($payment['paid_at'])) ?></small>
                                                            <br>
                                                            <small class="text-muted"><?= date('H:i', strtotime($payment['paid_at'])) ?></small>
                                                    <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="การดำเนินการ">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-outline-primary" data-bs-toggle="modal"
                                                            data-bs-target="#statusModal<?= $payment['payment_id'] ?>"
                                                            title="เปลี่ยนสถานะ">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>

                                                        <?php
                                                        $canCreateReceipt = ($payment['payment_status'] == 'completed');
                                                        $hasReceipt = !empty($payment['receipt_file']);
                                                        ?>

                                                        <?php if ($canCreateReceipt): ?>
                                                        <a href="receipt.php?payment_id=<?= $payment['payment_id'] ?>"
                                                            class="btn btn-outline-success" target="_blank"
                                                            title="สร้างใบเสร็จ">
                                                            <i class="bi bi-file-earmark-text"></i>
                                                        </a>
                                                        <?php endif; ?>

                                                        <?php if ($canCreateReceipt && !$hasReceipt): ?>
                                                        <button class="btn btn-outline-info" data-bs-toggle="modal"
                                                            data-bs-target="#receiptUploadModal<?= $payment['payment_id'] ?>"
                                                            title="แนบใบเสร็จ">
                                                            <i class="bi bi-paperclip"></i>
                                                        </button>
                                                        <?php endif; ?>

                                                        <?php if ($hasReceipt): ?>
                                                        <button class="btn btn-outline-secondary" data-bs-toggle="modal"
                                                            data-bs-target="#receiptManagementModal<?= $payment['payment_id'] ?>"
                                                            title="จัดการใบเสร็จ">
                                                            <i class="bi bi-folder"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Status Modal -->
                                                    <div class="modal fade" id="statusModal<?= $payment['payment_id'] ?>"
                                                        tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="bi bi-pencil-square me-2"></i>
                                                                        เปลี่ยนสถานะการชำระเงิน #<?= $payment['payment_id'] ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="update_payment" value="1">
                                                                    <input type="hidden" name="payment_id"
                                                                        value="<?= $payment['payment_id'] ?>">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">สถานะการชำระเงิน <span class="text-danger">*</span></label>
                                                                            <select name="payment_status" class="form-select"
                                                                                required>
                                                                                <option value="pending"
                                                                                    <?= $payment['payment_status'] == 'pending' ? 'selected' : '' ?>>รอชำระ</option>
                                                                                <option value="completed"
                                                                                    <?= $payment['payment_status'] == 'completed' ? 'selected' : '' ?>>ชำระแล้ว</option>
                                                                                <option value="failed"
                                                                                    <?= $payment['payment_status'] == 'failed' ? 'selected' : '' ?>>ล้มเหลว</option>
                                                                                <option value="refunded"
                                                                                    <?= $payment['payment_status'] == 'refunded' ? 'selected' : '' ?>>คืนเงินแล้ว</option>
                                                                            </select>
                                                                            <div class="invalid-feedback">กรุณาเลือกสถานะการชำระเงิน</div>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">อัพโหลดสลิปการชำระเงิน (ถ้ามี)</label>
                                                                            <input type="file" name="payment_slip"
                                                                                class="form-control" accept="image/*,.pdf">
                                                                            <div class="form-text">อนุญาตไฟล์ JPEG, JPG, PNG, GIF, WebP, PDF ขนาดไม่เกิน 5MB</div>
                                                                        </div>

                                                                        <?php if ($payment['payment_slip']): ?>
                                                                        <div class="mt-3">
                                                                            <p class="mb-2 fw-bold">สลิปปัจจุบัน:</p>
                                                                            <?php
                                                                            $is_pdf = pathinfo($payment['payment_slip'], PATHINFO_EXTENSION) === 'pdf';
                                                                            ?>
                                                                            <?php if ($is_pdf): ?>
                                                                            <a href="<?= $upload_dir . $payment['payment_slip'] ?>"
                                                                                target="_blank" class="btn btn-sm btn-outline-danger">
                                                                                <i class="bi bi-file-pdf me-1"></i>ดูไฟล์ PDF
                                                                            </a>
                                                                            <?php else: ?>
                                                                            <img src="<?= $upload_dir . $payment['payment_slip'] ?>"
                                                                                alt="สลิปปัจจุบัน"
                                                                                class="img-thumbnail" style="max-width: 100px;">
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?php endif; ?>

                                                                        <div class="alert alert-info">
                                                                            <strong>รายละเอียด:</strong><br>
                                                                            การจอง: #<?= $payment['booking_id'] ?><br>
                                                                            ลูกค้า: <?= $payment['customer_name'] ?><br>
                                                                            จำนวนเงิน: ฿<?= number_format($payment['amount'], 2) ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">
                                                                            <i class="bi bi-x-circle me-1"></i>ปิด
                                                                        </button>
                                                                        <button type="submit" class="btn btn-primary">
                                                                            <i class="bi bi-check-circle me-1"></i>อัพเดทสถานะ
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Receipt Upload Modal -->
                                                    <div class="modal fade" id="receiptUploadModal<?= $payment['payment_id'] ?>"
                                                        tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-success text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="bi bi-paperclip me-2"></i>แนบใบเสร็จรับเงิน
                                                                        #<?= $payment['payment_id'] ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="upload_receipt" value="1">
                                                                    <input type="hidden" name="payment_id"
                                                                        value="<?= $payment['payment_id'] ?>">
                                                                    <div class="modal-body">
                                                                        <div class="alert alert-info">
                                                                            <i class="bi bi-info-circle me-2"></i>
                                                                            <strong>เงื่อนไข:</strong>
                                                                            สามารถแนบใบเสร็จรับเงินได้เฉพาะเมื่อ
                                                                            <ul class="mb-0 mt-2">
                                                                                <li>สถานะการชำระเงิน: <span class="badge bg-success">completed</span></li>
                                                                            </ul>
                                                                        </div>

                                                                        <div class="mb-3">
                                                                            <label class="form-label">
                                                                                <i class="bi bi-file-earmark-arrow-up me-1"></i>เลือกไฟล์ใบเสร็จรับเงิน <span class="text-danger">*</span>
                                                                            </label>
                                                                            <input type="file" name="receipt_file"
                                                                                class="form-control form-control-lg"
                                                                                accept="image/*,.pdf" required>
                                                                            <div class="form-text">
                                                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                                                อนุญาตไฟล์ JPEG, JPG, PNG, GIF, WebP, PDF ขนาดไม่เกิน 5MB
                                                                            </div>
                                                                            <div class="invalid-feedback">กรุณาเลือกไฟล์ใบเสร็จรับเงิน</div>
                                                                        </div>

                                                                        <div class="card bg-light">
                                                                            <div class="card-body">
                                                                                <h6 class="card-title fw-bold">
                                                                                    <i class="bi bi-clipboard-check me-2"></i>รายละเอียดการจอง
                                                                                </h6>
                                                                                <table class="table table-sm table-borderless mb-0">
                                                                                    <tr>
                                                                                        <td class="text-muted">การจอง:</td>
                                                                                        <td class="fw-bold">#<?= $payment['booking_id'] ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td class="text-muted">ลูกค้า:</td>
                                                                                        <td class="fw-bold">
                                                                                            <?= $payment['customer_name'] ?>
                                                                                            <small class="text-muted">(<?= $payment['customer_phone'] ?>)</small>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td class="text-muted">บริการ:</td>
                                                                                        <td class="fw-bold"><?= $payment['service_name'] ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td class="text-muted">หมอนวด:</td>
                                                                                        <td class="fw-bold"><?= $payment['therapist_name'] ?></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td class="text-muted">วันที่:</td>
                                                                                        <td class="fw-bold">
                                                                                            <?= date('d/m/Y', strtotime($payment['booking_date'])) ?>
                                                                                            เวลา
                                                                                            <?= date('H:i', strtotime($payment['start_time'])) ?>-<?= date('H:i', strtotime($payment['end_time'])) ?> น.
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td class="text-muted">จำนวนเงิน:</td>
                                                                                        <td class="fw-bold text-primary fs-5">
                                                                                            ฿<?= number_format($payment['amount'], 2) ?>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">
                                                                            <i class="bi bi-x-circle me-1"></i>ยกเลิก
                                                                        </button>
                                                                        <button type="submit" class="btn btn-success">
                                                                            <i class="bi bi-upload me-1"></i>อัพโหลดใบเสร็จ
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Receipt Management Modal -->
                                                    <?php if ($hasReceipt): ?>
                                                    <div class="modal fade"
                                                        id="receiptManagementModal<?= $payment['payment_id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-info text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="bi bi-folder me-2"></i>จัดการใบเสร็จรับเงิน
                                                                        #<?= $payment['payment_id'] ?>
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal"></button>
                                                                </div>
                                                                        <div class="modal-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="mb-3">
                                                                                        <label class="form-label fw-bold">
                                                                                            <i class="bi bi-file-earmark-text me-1"></i>ใบเสร็จรับเงินปัจจุบัน
                                                                                        </label>
                                                                                        <div class="text-center p-3 border rounded">
                                                                                            <?php
                                                                                            $is_receipt_pdf = pathinfo($payment['receipt_file'], PATHINFO_EXTENSION) === 'pdf';
                                                                                            ?>
                                                                                            <?php if ($is_receipt_pdf): ?>
                                                                                                    <a href="<?= $receipt_dir . $payment['receipt_file'] ?>"
                                                                                                        target="_blank"
                                                                                                        class="text-decoration-none">
                                                                                                        <div class="d-flex flex-column align-items-center justify-content-center border rounded p-4 mb-2">
                                                                                                            <i class="bi bi-file-pdf fs-1 text-danger"></i>
                                                                                                        </div>
                                                                                                        <div class="btn btn-sm btn-outline-success">
                                                                                                            <i class="bi bi-eye me-1"></i>ดูไฟล์ PDF
                                                                                                        </div>
                                                                                                    </a>
                                                                                            <?php else: ?>
                                                                                                    <img src="<?= $receipt_dir . $payment['receipt_file'] ?>"
                                                                                                        alt="ใบเสร็จรับเงิน"
                                                                                                        class="img-fluid img-thumbnail">
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="card bg-light mb-3">
                                                                                        <div class="card-body">
                                                                                            <h6 class="card-title fw-bold">
                                                                                                <i class="bi bi-info-circle me-2"></i>รายละเอียดการจอง
                                                                                            </h6>
                                                                                            <table
                                                                                                class="table table-sm table-borderless mb-0">
                                                                                                <tr>
                                                                                                    <td class="text-muted">การจอง:</td>
                                                                                                    <td class="fw-bold">
                                                                                                        #<?= $payment['booking_id'] ?>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <tr>
                                                                                                    <td class="text-muted">ลูกค้า:</td>
                                                                                                    <td class="fw-bold">
                                                                                                        <?= $payment['customer_name'] ?><br>
                                                                                                        <small
                                                                                                            class="text-muted"><?= $payment['customer_phone'] ?></small>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <tr>
                                                                                                    <td class="text-muted">บริการ:</td>
                                                                                                    <td class="fw-bold">
                                                                                                        <?= $payment['service_name'] ?>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <tr>
                                                                                                    <td class="text-muted">จำนวนเงิน:
                                                                                                    </td>
                                                                                                    <td class="fw-bold text-primary">
                                                                                                        ฿<?= number_format($payment['amount'], 2) ?>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <tr>
                                                                                                    <td class="text-muted">
                                                                                                        วันที่ชำระเงิน:</td>
                                                                                                    <td class="fw-bold">
                                                                                                        <?= $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-' ?>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="alert alert-warning">
                                                                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                                                                        <strong>คำเตือน:</strong>
                                                                                        การลบใบเสร็จจะไม่สามารถกู้คืนได้
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <form method="POST" class="d-inline">
                                                                                <?= csrfField() ?>
                                                                                <input type="hidden" name="delete_receipt" value="1">
                                                                                <input type="hidden" name="payment_id"
                                                                                    value="<?= $payment['payment_id'] ?>">
                                                                                <button type="submit" class="btn btn-danger"
                                                                                    onclick="return confirm('คุณแน่ใจว่าต้องการลบใบเสร็จรับเงินนี้?')">
                                                                                    <i class="bi bi-trash me-1"></i>ลบใบเสร็จ
                                                                                </button>
                                                                            </form>
                                                                            <a href="<?= $receipt_dir . $payment['receipt_file'] ?>"
                                                                                download class="btn btn-success">
                                                                                <i class="bi bi-download me-1"></i>ดาวน์โหลด
                                                                            </a>
                                                                            <button type="button" class="btn btn-secondary"
                                                                                data-bs-dismiss="modal">
                                                                                <i class="bi bi-x-circle me-1"></i>ปิด
                                                                            </button>
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
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <h5 class="mt-3">ไม่พบข้อมูลการชำระเงิน</h5>
                    <?php if (!empty($where_conditions)): ?>
                    <p class="text-muted">ลองเปลี่ยนเงื่อนไขการค้นหาหรือ <a href="<?= $_SERVER['PHP_SELF'] ?>">ล้างตัวกรอง</a></p>
                    <?php else: ?>
                    <p class="text-muted">ยังไม่มีข้อมูลการชำระเงินในระบบ</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
    
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>
<?php $conn->close(); ?>