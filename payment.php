<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ตรวจสอบ parameter
if (!isset($_GET['booking_id'])) {
    header('Location: booking_history.php');
    exit();
}

$booking_id = $conn->real_escape_string($_GET['booking_id']);

// ตรวจสอบว่าผู้ใช้มีสิทธิ์ชำระเงินการจองนี้
$booking_sql = "SELECT b.*, mt.name as service_name, mt.price, 
                       t.full_name as therapist_name,
                       p.payment_id, p.payment_status, p.payment_method, p.payment_slip,
                       p.created_at as payment_created
                FROM bookings b
                JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
                JOIN therapists t ON b.therapist_id = t.therapist_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id
                WHERE b.booking_id = '$booking_id' AND b.customer_id = '$user_id'";

$booking_result = $conn->query($booking_sql);

if ($booking_result->num_rows == 0) {
    header('Location: booking_history.php');
    exit();
}

$booking = $booking_result->fetch_assoc();

// ตรวจสอบว่ามีการชำระเงินไปแล้วหรือยัง
if ($booking['payment_status'] == 'completed') {
    $error = 'การจองนี้ชำระเงินแล้ว';
}

// จัดการการชำระเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error) {
    $payment_method = $conn->real_escape_string($_POST['payment_method']);

    // ตรวจสอบว่ามีการอัพโหลดสลิป
    $payment_slip = null;
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/payment_slips/';

        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // ตรวจสอบประเภทไฟล์
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $file_type = $_FILES['payment_slip']['type'];

        if (!in_array($file_type, $allowed_types)) {
            $error = 'ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, JPG, PNG, GIF และ PDF';
        } else {
            // ตั้งชื่อไฟล์ใหม่
            $file_extension = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
            $new_filename = 'slip_' . $booking_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            // อัพโหลดไฟล์
            if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_path)) {
                $payment_slip = $new_filename;
            } else {
                $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
            }
        }
    } elseif (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์: ' . $_FILES['payment_slip']['error'];
    }

    // ถ้าไม่มีข้อผิดพลาด ให้บันทึกข้อมูล
    if (!$error) {
        // ตรวจสอบว่ามีการชำระเงินอยู่แล้วหรือไม่
        if ($booking['payment_id']) {
            // อัพเดทการชำระเงินที่มีอยู่
            if ($payment_slip) {
                $update_sql = "UPDATE payments SET 
                              payment_method = '$payment_method',
                              payment_slip = '$payment_slip',
                              payment_status = 'pending'
                              WHERE payment_id = '{$booking['payment_id']}'";
            } else {
                $update_sql = "UPDATE payments SET 
                              payment_method = '$payment_method',
                              payment_status = 'pending'
                              WHERE payment_id = '{$booking['payment_id']}'";
            }
        } else {
            // สร้างการชำระเงินใหม่
            if ($payment_slip) {
                $insert_sql = "INSERT INTO payments (booking_id, amount, payment_method, payment_status, payment_slip) 
                              VALUES ('$booking_id', '{$booking['total_price']}', '$payment_method', 'pending', '$payment_slip')";
            } else {
                $insert_sql = "INSERT INTO payments (booking_id, amount, payment_method, payment_status) 
                              VALUES ('$booking_id', '{$booking['total_price']}', '$payment_method', 'pending')";
            }
        }

        $sql = $booking['payment_id'] ? $update_sql : $insert_sql;

        if ($conn->query($sql)) {
            $success = 'บันทึกข้อมูลการชำระเงินสำเร็จ! กรุณาชำระเงินตามวิธีการที่เลือก';

            if ($payment_slip) {
                $success .= ' และได้อัพโหลดสลิปเรียบร้อยแล้ว';
            }

            // สำหรับการชำระเงินด้วย PromptPay (ตัวอย่าง)
            if ($payment_method == 'promptpay') {
                $success .= '<br><br><div class="alert alert-info">
                            <h6><i class="fas fa-qrcode"></i> ชำระผ่านพร้อมเพย์</h6>
                            <strong>เลขพร้อมเพย์:</strong> 000-000-0000<br>
                            <strong>จำนวนเงิน:</strong> ฿' . number_format($booking['total_price'], 2) . '<br>
                            <strong>ชื่อ:</strong> I Love Massage
                            </div>
                            <p class="text-muted">กรุณาส่งสลิปการโอนเงินผ่าน LINE Official: @massagespa</p>';
            } elseif ($payment_method == 'bank_transfer') {
                $success .= '<br><br><div class="alert alert-info">
                            <h6><i class="fas fa-university"></i> โอนผ่านธนาคาร</h6>
                            <strong>ธนาคาร:</strong> กรุงเทพ<br>
                            <strong>เลขบัญชี:</strong> 123-4-56789-0<br>
                            <strong>ชื่อบัญชี:</strong> I Love Massage<br>
                            <strong>จำนวนเงิน:</strong> ฿' . number_format($booking['total_price'], 2) . '
                            </div>
                            <p class="text-muted">กรุณาส่งสลิปการโอนเงินผ่าน LINE Official: @massagespa</p>';
            }

            // เพิ่มส่วนอัพโหลดสลิปหลังจากบันทึกสำเร็จ
            $success .= '
            <div class="mt-4">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-file-upload me-2"></i>อัพโหลดสลิปการชำระเงิน</h6>
                    <p class="mb-2">หากคุณยังไม่ได้อัพโหลดสลิป หรือต้องการอัพโหลดสลิปใหม่ กรุณาใช้ฟอร์มด้านล่าง:</p>
                    <form method="POST" enctype="multipart/form-data" class="mt-3">
                        <input type="hidden" name="payment_method" value="' . htmlspecialchars($payment_method) . '">
                        <div class="mb-3">
                            <label class="form-label fw-bold">เลือกไฟล์สลิป</label>
                            <input type="file" class="form-control" name="payment_slip"
                                accept=".jpg,.jpeg,.png,.gif,.pdf,.JPG,.JPEG,.PNG,.GIF,.PDF" required>
                            <div class="form-text">
                                อนุญาตเฉพาะไฟล์ภาพ (JPG, JPEG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>อัพโหลดสลิป
                        </button>
                    </form>
                </div>
            </div>';
        } else {
            $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงิน - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="CSS/customer-styles.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<style>
    /* Global Styles */
    body {
        font-family: 'Sarabun', sans-serif;
        line-height: 1.6;
        background-color: #f8fdfb;
    }

    /* Container */
    .container {
        max-width: 1000px;
    }

    /* Card Styles */
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 15px;
        overflow: hidden;
        border: 1px solid #e6f7f3;
        background: #ffffff;
        margin-bottom: 25px;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(79, 195, 161, 0.15) !important;
    }

    .card-header {
        background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%) !important;
        color: white;
        border-bottom: none;
        padding: 20px;
        font-weight: 600;
    }

    .card-header.bg-success {
        background: linear-gradient(135deg, #68d391, #48bb78) !important;
    }

    .card-header.bg-warning {
        background: linear-gradient(135deg, #f6e05e, #ecc94b) !important;
        color: #2d5a5a !important;
    }

    .card-body {
        padding: 25px;
    }

    /* Button Styles */
    .btn {
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        padding: 12px 25px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4fc3a1, #38b2ac);
        color: #ffffff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #38b2ac, #2c9c8a);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(79, 195, 161, 0.4);
    }

    .btn-success {
        background: linear-gradient(135deg, #68d391, #48bb78);
        color: #ffffff;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #48bb78, #38a169);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(104, 211, 145, 0.4);
    }

    .btn-outline-secondary {
        border: 2px solid #718096;
        color: #718096;
        background: transparent;
    }

    .btn-outline-secondary:hover {
        background: #718096;
        border-color: #718096;
        color: white;
        transform: translateY(-1px);
    }

    .btn-outline-primary {
        border: 2px solid #4fc3a1;
        color: #4fc3a1;
        background: transparent;
    }

    .btn-outline-primary:hover {
        background: #4fc3a1;
        border-color: #4fc3a1;
        color: white;
        transform: translateY(-1px);
    }

    /* Alert Styles */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 20px;
    }

    .alert-danger {
        background: linear-gradient(135deg, #fed7d7, #feb2b2);
        color: #c53030;
        border-left: 4px solid #fc8181;
    }

    .alert-success {
        background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
        color: #2d5a5a;
        border-left: 4px solid #48bb78;
    }

    .alert-info {
        background: linear-gradient(135deg, #bee3f8, #90cdf4);
        color: #2d5a5a;
        border-left: 4px solid #4299e1;
    }

    /* Badge Styles */
    .badge {
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .bg-success {
        background: linear-gradient(45deg, #68d391, #48bb78) !important;
    }

    .bg-warning {
        background: linear-gradient(45deg, #f6e05e, #ecc94b) !important;
        color: #2d5a5a !important;
    }

    /* Form Styles */
    .form-check {
        transition: all 0.3s ease;
    }

    .form-check-input {
        width: 1.2em;
        height: 1.2em;
        margin-top: 0.25em;
    }

    .form-check-input:checked {
        background-color: #4fc3a1;
        border-color: #4fc3a1;
    }

    .form-check-label {
        cursor: pointer;
        width: 100%;
    }

    .border.rounded {
        border: 2px solid #e6f7f3 !important;
        border-radius: 10px !important;
        transition: all 0.3s ease;
    }

    .form-check-input:checked+.form-check-label .border.rounded {
        border-color: #4fc3a1 !important;
        background: #f0f9f7;
    }

    .form-control {
        border: 2px solid #e6f7f3;
        border-radius: 10px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        border-color: #4fc3a1;
        box-shadow: 0 0 0 0.2rem rgba(79, 195, 161, 0.25);
    }

    .form-text {
        color: #718096;
        font-size: 0.85rem;
    }

    /* Payment Method Styles */
    .payment-method {
        padding: 20px;
        border-radius: 10px;
        border: 2px solid #e6f7f3;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .payment-method:hover {
        border-color: #4fc3a1;
        background: #f0f9f7;
        transform: translateY(-2px);
    }

    .payment-method.selected {
        border-color: #4fc3a1;
        background: #f0f9f7;
    }

    .payment-method .fas.fa-check-circle {
        color: #68d391;
    }

    .payment-method .fas.fa-circle {
        color: #cbd5e0;
    }

    /* Text Colors */
    .text-primary {
        color: #4fc3a1 !important;
    }

    .text-success {
        color: #68d391 !important;
    }

    .text-muted {
        color: #718096 !important;
    }

    /* Icon Styles */
    .fas,
    .fab {
        transition: transform 0.3s ease;
    }

    .btn:hover .fas,
    .btn:hover .fab {
        transform: scale(1.1);
    }

    /* Summary Styles */
    h4.text-success {
        color: #68d391 !important;
        font-weight: 700;
    }

    /* Grid Gap */
    .d-grid.gap-2 {
        gap: 15px !important;
    }

    /* Footer */
    footer {
        background: linear-gradient(135deg, #2d5a5a 0%, #234e52 100%) !important;
        margin-top: 3rem !important;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .card-body {
            padding: 20px;
        }

        .card-header {
            padding: 15px;
        }

        .btn {
            padding: 10px 20px;
            width: 100%;
            margin-bottom: 10px;
        }

        .d-grid.gap-2 .btn {
            width: 100%;
        }

        .row .col-md-6 {
            margin-bottom: 15px;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 0 15px;
        }

        .d-flex.justify-content-between {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .payment-method {
            padding: 15px;
        }

        .alert {
            padding: 15px;
        }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f9f7;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #4fc3a1, #38b2ac);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #38b2ac, #2c9c8a);
    }

    /* Animation for payment method selection */
    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
        }
    }

    .form-check-input:checked+.form-check-label .border.rounded {
        animation: pulse 0.3s ease;
    }

    /* Focus states for accessibility */
    .btn:focus,
    .form-control:focus,
    .form-check-input:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 195, 161, 0.3);
    }

    /* File input styling */
    .form-control[type="file"] {
        padding: 10px 15px;
    }

    /* Booking details styling */
    .card-body p {
        margin-bottom: 10px;
        color: #2d5a5a;
    }

    .card-body p strong {
        color: #2d5a5a;
    }

    /* Price summary styling */
    .text-end h4 {
        margin-bottom: 5px;
    }
</style>

<body>
    <?php include 'templates\navbar-user.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0"><i class="fas fa-credit-card me-2"></i>ชำระเงิน</h2>
                    <a href="booking_history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>กลับ
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                    </div>
                    <div class="text-center">
                        <a href="booking_history.php" class="btn btn-primary">กลับไปยังประวัติการจอง</a>
                    </div>
                <?php else: ?>

                    <!-- แสดงสลิปการชำระเงิน (ถ้ามี) -->
                    <?php if (!empty($booking['payment_slip'])): ?>
                        <div class="alert alert-info mb-4">
                            <h5><i class="fas fa-receipt me-2"></i>สลิปการชำระเงินปัจจุบัน</h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="mb-1"><strong>ไฟล์สลิป:</strong> <?= htmlspecialchars($booking['payment_slip']) ?>
                                    </p>
                                    <?php if (!empty($booking['payment_created'])): ?>
                                        <p class="mb-0 text-muted">อัพโหลดเมื่อ:
                                            <?= date('d/m/Y H:i', strtotime($booking['payment_created'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <a href="uploads/payment_slips/<?= htmlspecialchars($booking['payment_slip']) ?>"
                                    target="_blank" class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>ดูสลิป
                                </a>
                            </div>
                            <hr>
                            <p class="mb-0 text-muted"><small>หากต้องการอัพเดทสลิป กรุณาอัพโหลดสลิปใหม่ด้านล่าง</small></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        </div>

                        <div class="text-center mt-4">
                            <a href="booking_history.php" class="btn btn-primary me-2">กลับไปยังประวัติการจอง</a>
                            <a href="index.php" class="btn btn-outline-primary">ไปหน้าแรก</a>
                        </div>
                    <?php else: ?>

                        <!-- รายละเอียดการจอง -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>รายละเอียดการจอง</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-tag me-2"></i>รหัสการจอง:</strong>
                                            #<?= $booking['booking_id'] ?></p>
                                        <p><strong><i class="fas fa-spa me-2"></i>บริการ:</strong>
                                            <?= htmlspecialchars($booking['service_name']) ?></p>
                                        <p><strong><i class="fas fa-user-md me-2"></i>หมอนวด:</strong>
                                            <?= htmlspecialchars($booking['therapist_name']) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong><i class="fas fa-calendar-day me-2"></i>วันที่:</strong>
                                            <?= date('d/m/Y', strtotime($booking['booking_date'])) ?></p>
                                        <p><strong><i class="fas fa-clock me-2"></i>เวลา:</strong>
                                            <?= substr($booking['start_time'], 0, 5) ?> -
                                            <?= substr($booking['end_time'], 0, 5) ?>
                                        </p>
                                        <p><strong><i class="fas fa-info-circle me-2"></i>สถานะ:</strong>
                                            <span
                                                class="badge bg-<?= $booking['status'] == 'confirmed' ? 'success' : 'warning' ?>">
                                                <?= $booking['status'] ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- สรุปเงินที่ต้องชำระ -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>สรุปเงินที่ต้องชำระ</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>บริการนวด:</strong> ฿<?= number_format($booking['price'], 2) ?></p>
                                        <p><strong>ภาษี:</strong> ฿0.00</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <h4 class="text-success">รวมทั้งสิ้น: ฿<?= number_format($booking['total_price'], 2) ?>
                                        </h4>
                                        <small class="text-muted">(ราคารวมภาษีแล้ว)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- วิธีการชำระเงิน -->
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>เลือกวิธีการชำระเงิน</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-4">
                                        <div class="form-check mb-3 p-3 border rounded">
                                            <input class="form-check-input" type="radio" name="payment_method" value="promptpay"
                                                id="promptpay" checked>
                                            <label class="form-check-label w-100" for="promptpay">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><i class="fas fa-mobile-alt me-2"></i>พร้อมเพย์
                                                            (PromptPay)</strong>
                                                        <p class="text-muted mb-0">โอนเงินผ่านพร้อมเพย์ - สะดวก รวดเร็ว</p>
                                                    </div>
                                                    <i class="fas fa-check-circle text-success fs-4"></i>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="form-check mb-3 p-3 border rounded">
                                            <input class="form-check-input" type="radio" name="payment_method"
                                                value="bank_transfer" id="bank_transfer">
                                            <label class="form-check-label w-100" for="bank_transfer">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><i class="fas fa-university me-2"></i>โอนผ่านธนาคาร</strong>
                                                        <p class="text-muted mb-0">โอนเงินผ่านบัญชีธนาคาร</p>
                                                    </div>
                                                    <i class="fas fa-circle text-muted fs-4"></i>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="form-check mb-3 p-3 border rounded">
                                            <input class="form-check-input" type="radio" name="payment_method"
                                                value="credit_card" id="credit_card">
                                            <label class="form-check-label w-100" for="credit_card">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><i class="fas fa-credit-card me-2"></i>บัตรเครดิต</strong>
                                                        <p class="text-muted mb-0">ชำระด้วยบัตรเครดิต (ต้องชำระที่หน้าร้าน)</p>
                                                    </div>
                                                    <i class="fas fa-circle text-muted fs-4"></i>
                                                </div>
                                            </label>
                                        </div>

                                        <!-- <div class="form-check mb-3 p-3 border rounded">
                                            <input class="form-check-input" type="radio" name="payment_method" value="cash"
                                                id="cash">
                                            <label class="form-check-label w-100" for="cash">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><i class="fas fa-money-bill-wave me-2"></i>เงินสด</strong>
                                                        <p class="text-muted mb-0">ชำระด้วยเงินสดที่หน้าร้าน</p>
                                                    </div>
                                                    <i class="fas fa-circle text-muted fs-4"></i>
                                                </div>
                                            </label>
                                        </div> -->
                                    </div>

                                    <!-- ส่วนอัพโหลดสลิป -->
                                    <!-- <div class="mb-4">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-file-upload me-2"></i>อัพโหลดสลิปการชำระเงิน
                                        </label>
                                        <input type="file" class="form-control" name="payment_slip"
                                            accept=".jpg,.jpeg,.png,.gif,.pdf,.JPG,.JPEG,.PNG,.GIF,.PDF">
                                        <div class="form-text">
                                            อนุญาตเฉพาะไฟล์ภาพ (JPG, JPEG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB
                                        </div>
                                    </div> -->

                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle me-2"></i>หมายเหตุ</h6>
                                        <?php if ($booking['status'] == 'pending'): ?>
                                            การจองจะได้รับการยืนยันหลังจากชำระเงินเสร็จสิ้นและตรวจสอบการชำระเงินแล้ว
                                        <?php else: ?>
                                            การจองนี้ได้รับการยืนยันแล้ว
                                        <?php endif; ?>
                                        <br>
                                        <strong>สำหรับการโอนเงิน:</strong> กรุณาอัพโหลดสลิปการโอนเงินเพื่อให้เราตรวจสอบ
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success btn-lg py-3">
                                            <i class="fas fa-lock me-2"></i>บันทึกข้อมูลการชำระเงิน
                                        </button>
                                        <a href="booking_history.php" class="btn btn-outline-secondary">ยกเลิก</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-spa me-2"></i>I Love Massage</h5>
                    <p class="mb-0"> </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">โทร: 000-0000000</p>
                </div>
            </div>
            <hr class="my-3">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 I Love Massage. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // เพิ่มเอฟเฟกต์เมื่อเลือกวิธีการชำระเงิน
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function () {
                // รีเซ็ตไอคอนทั้งหมด
                document.querySelectorAll('.form-check-label .fas').forEach(icon => {
                    if (icon.classList.contains('fa-check-circle')) {
                        icon.classList.remove('fa-check-circle', 'text-success');
                        icon.classList.add('fa-circle', 'text-muted');
                    }
                });

                // ตั้งค่าไอคอนสำหรับตัวที่เลือก
                const selectedLabel = this.closest('.form-check-label');
                const selectedIcon = selectedLabel.querySelector('.fas');
                selectedIcon.classList.remove('fa-circle', 'text-muted');
                selectedIcon.classList.add('fa-check-circle', 'text-success');
            });
        });

        // ตรวจสอบขนาดไฟล์ก่อนอัพโหลด
        const fileInput = document.querySelector('input[name="payment_slip"]');
        if (fileInput) {
            fileInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const fileSize = file.size / 1024 / 1024; // MB
                    if (fileSize > 5) {
                        alert('ไฟล์มีขนาดใหญ่เกินไป กรุณาเลือกไฟล์ขนาดไม่เกิน 5MB');
                        this.value = '';
                    }
                }
            });
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>