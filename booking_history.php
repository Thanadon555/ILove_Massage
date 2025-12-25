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

$payment_success = '';
if (isset($_SESSION['payment_success'])) {
    $payment_success = $_SESSION['payment_success'];
    unset($_SESSION['payment_success']);
}

$cancel_success = '';
if (isset($_SESSION['cancel_success'])) {
    $cancel_success = $_SESSION['cancel_success'];
    unset($_SESSION['cancel_success']);
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ดึงประวัติการจอง
$bookings = [];
$sql = "SELECT b.*, t.full_name as therapist_name, mt.name as service_name, mt.duration_minutes,
               p.payment_status, p.amount as paid_amount, p.payment_slip, p.receipt_file,
               r.review_id
        FROM bookings b
        JOIN therapists t ON b.therapist_id = t.therapist_id
        JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
        LEFT JOIN payments p ON b.booking_id = p.booking_id
        LEFT JOIN reviews r ON b.booking_id = r.booking_id
        WHERE b.customer_id = '$user_id'
        ORDER BY b.booking_id DESC";  // เปลี่ยนการเรียงลำดับเป็น booking_id DESC

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// ยกเลิกการจอง
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = $conn->real_escape_string($_POST['booking_id']);
    $cancel_reason = $conn->real_escape_string($_POST['cancel_reason']);

    // ตรวจสอบว่าสามารถยกเลิกได้หรือไม่ (ต้องเป็นการจองที่ยังไม่เกิดขึ้นและสถานะเป็น pending หรือ confirmed)
    $check_sql = "SELECT * FROM bookings WHERE booking_id = '$booking_id' AND customer_id = '$user_id' 
                  AND (status = 'pending' OR status = 'confirmed') 
                  AND CONCAT(booking_date, ' ', start_time) > NOW()";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $update_sql = "UPDATE bookings SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), ' ยกเลิกเพราะ: $cancel_reason') 
                      WHERE booking_id = '$booking_id'";

        if ($conn->query($update_sql)) {
            $_SESSION['cancel_success'] = 'ยกเลิกการจองสำเร็จ';
            header('Location: booking_history.php');
            exit();
        } else {
            $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }
    } else {
        $error = 'ไม่สามารถยกเลิกการจองนี้ได้';
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการจอง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="CSS/customer-styles.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Global Styles */
        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            background-color: #f8fdfb;
        }

        /* Container */
        .container {
            max-width: 1200px;
        }

        /* Card and Table Styles */
        .table-responsive {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(79, 195, 161, 0.1);
            border: 1px solid #e6f7f3;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%);
            color: white;
            border-bottom: none;
            padding: 15px 12px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .table tbody tr {
            transition: background 0.3s ease;
            background: #ffffff;
        }

        .table tbody tr:hover {
            background: #f0f9f7;
        }

        .table tbody td {
            padding: 15px 12px;
            border-bottom: 1px solid #e6f7f3;
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8fdfb;
        }

        /* Button Styles */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #38b2ac, #2c9c8a);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 195, 161, 0.3);
        }

        .btn-info {
            background: linear-gradient(135deg, #63b3ed, #4299e1);
            color: #ffffff;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #68d391, #48bb78);
            color: #ffffff;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #48bb78, #38a169);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f6e05e, #ecc94b);
            color: #2d5a5a;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #ecc94b, #d69e2e);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #fc8181, #f56565);
            color: #ffffff;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            transform: translateY(-2px);
        }

        .btn-outline-success {
            border: 2px solid #68d391;
            color: #68d391;
            background: transparent;
        }

        .btn-outline-success:hover {
            background: #68d391;
            color: #ffffff;
        }

        .btn-outline-success:disabled {
            background: #cbd5e0;
            border-color: #cbd5e0;
            color: #718096;
        }

        /* Button Group */
        .btn-group-vertical .btn {
            margin-bottom: 5px;
            font-size: 0.8rem;
            padding: 6px 10px;
            border-radius: 8px;
        }

        /* Badge Styles */
        .badge {
            font-size: 0.75rem;
            padding: 6px 10px;
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

        .bg-info {
            background: linear-gradient(45deg, #63b3ed, #4299e1) !important;
        }

        .bg-danger {
            background: linear-gradient(45deg, #fc8181, #f56565) !important;
        }

        .bg-secondary {
            background: linear-gradient(45deg, #a0aec0, #718096) !important;
        }

        .action-badge {
            font-size: 0.7rem;
            margin-top: 2px;
            padding: 4px 8px;
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #2d5a5a;
            border-left: 4px solid #48bb78;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #c53030;
            border-left: 4px solid #fc8181;
        }

        .alert-info {
            background: linear-gradient(135deg, #bee3f8, #90cdf4);
            color: #2d5a5a;
            border-left: 4px solid #4299e1;
        }

        /* Text Colors */
        .text-primary {
            color: #4fc3a1 !important;
        }

        .text-muted {
            color: #718096 !important;
        }

        .booking-id {
            font-weight: bold;
            color: #2d5a5a;
            font-size: 0.9rem;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            border-top: 1px solid #e6f7f3;
            padding: 20px;
        }

        /* Form Styles */
        .form-control {
            border: 2px solid #e6f7f3;
            border-radius: 10px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4fc3a1;
            box-shadow: 0 0 0 0.2rem rgba(79, 195, 161, 0.25);
        }

        .form-label {
            color: #2d5a5a;
            font-weight: 600;
            margin-bottom: 8px;
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

        /* Empty State */
        .alert-info.text-center {
            background: linear-gradient(135deg, #f0f9f7, #e6f7f3);
            border: 2px solid #4fc3a1;
            color: #2d5a5a;
            padding: 40px;
        }

        /* Footer Text */
        .text-end small {
            color: #718096;
            font-size: 0.85rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 10px;
            }

            .table thead {
                display: none;
            }

            .table tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #e6f7f3;
                border-radius: 10px;
                padding: 15px;
            }

            .table tbody td {
                display: block;
                text-align: left;
                border-bottom: 1px solid #f1f9f7;
                padding: 10px 0;
            }

            .table tbody td:last-child {
                border-bottom: none;
            }

            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #4fc3a1;
                display: block;
                margin-bottom: 5px;
                font-size: 0.85rem;
            }

            .btn-group-vertical {
                width: 100%;
            }

            .btn-group-vertical .btn {
                width: 100%;
                margin-bottom: 8px;
            }
        }

        @media (max-width: 576px) {
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .alert {
                padding: 15px;
            }

            .modal-dialog {
                margin: 10px;
            }
        }
    </style>
</head>

<body>
    <?php include 'templates\navbar-user.php'; ?>
    <?php if ($payment_success): ?>
        <div class="container mt-4">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($payment_success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-history me-2"></i>ประวัติการจอง</h2>
            <a href="booking.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>จองคิวใหม่
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success || $cancel_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= $success ?: $cancel_success ?>
            </div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <div class="alert alert-info text-center">
                <h5><i class="fas fa-calendar-times me-2"></i>ยังไม่มีประวัติการจอง</h5>
                <p class="mb-3">เริ่มต้นการจองคิวบริการนวดครั้งแรกของคุณ</p>
                <a href="booking.php" class="btn btn-primary">
                    <i class="fas fa-calendar-plus me-2"></i>จองคิวครั้งแรก
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>รหัสการจอง</th>
                            <th>บริการ</th>
                            <th>หมอนวด</th>
                            <th>วันที่และเวลา</th>
                            <th>สถานะ</th>
                            <th>การชำระเงิน</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <span class="booking-id">#<?= $booking['booking_id'] ?></span>
                                </td>
                                <td>
                                    <strong><?= $booking['service_name'] ?></strong><br>
                                    <small class="text-muted"><?= $booking['duration_minutes'] ?> นาที</small>
                                </td>
                                <td><?= $booking['therapist_name'] ?></td>
                                <td>
                                    <i class="fas fa-calendar-day me-1 text-primary"></i>
                                    <?= date('d/m/Y', strtotime($booking['booking_date'])) ?><br>
                                    <i class="fas fa-clock me-1 text-primary"></i>
                                    <?= substr($booking['start_time'], 0, 5) ?> - <?= substr($booking['end_time'], 0, 5) ?>
                                </td>
                                <td>
                                    <span
                                        class="badge bg-<?=
                                            $booking['status'] == 'confirmed' ? 'success' : ($booking['status'] == 'pending' ? 'warning' : ($booking['status'] == 'completed' ? 'info' : ($booking['status'] == 'cancelled' ? 'danger' : 'secondary'))) ?>">
                                        <?php
                                        $status_th = [
                                            'pending' => 'รอดำเนินการ',
                                            'confirmed' => 'ยืนยันแล้ว',
                                            'completed' => 'เสร็จสิ้น',
                                            'cancelled' => 'ยกเลิก'
                                        ];
                                        echo $status_th[$booking['status']] ?? $booking['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['payment_status']): ?>
                                        <span
                                            class="badge bg-<?= $booking['payment_status'] == 'completed' ? 'success' : 'warning' ?>">
                                            <?php
                                            $payment_status_th = [
                                                'pending' => 'รอตรวจสอบ',
                                                'completed' => 'ชำระแล้ว',
                                                'failed' => 'ล้มเหลว',
                                                'refunded' => 'คืนเงินแล้ว'
                                            ];
                                            echo $payment_status_th[$booking['payment_status']] ?? $booking['payment_status'];
                                            ?>
                                        </span><br>
                                        <small class="text-muted">฿<?= number_format($booking['paid_amount'], 2) ?></small>

                                        <!-- แสดงสถานะใบเสร็จรับเงิน -->
                                        <?php if (!empty($booking['receipt_file'])): ?>
                                            <br>
                                            <span class="badge bg-success action-badge">
                                                <i class="fas fa-file-invoice me-1"></i>มีใบเสร็จรับเงิน
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ยังไม่ชำระ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking['status'] != 'cancelled'): ?>
                                        <div class="btn-group-vertical btn-group-sm" role="group">
                                            <?php
                                            $can_cancel = ($booking['status'] == 'pending' || $booking['status'] == 'confirmed') &&
                                                strtotime($booking['booking_date'] . ' ' . $booking['start_time']) > time();

                                            $has_receipt = !empty($booking['receipt_file']);
                                            $has_review = !empty($booking['review_id']);
                                            ?>

                                            <!-- ปุ่มดูสลิป (แสดงเมื่อมีสลิป) -->
                                            <?php if (!empty($booking['payment_slip'])): ?>
                                                <a href="uploads/payment_slips/<?= $booking['payment_slip'] ?>" target="_blank"
                                                    class="btn btn-info mb-1">
                                                    <i class="fas fa-receipt me-1"></i>ดูสลิป
                                                </a>
                                            <?php endif; ?>

                                            <!-- ปุ่มดูใบเสร็จรับเงิน (แสดงเมื่อมีใบเสร็จ) -->
                                            <?php if ($has_receipt): ?>
                                                <a href="uploads/receipts/<?= $booking['receipt_file'] ?>" target="_blank"
                                                    class="btn btn-success mb-1">
                                                    <i class="fas fa-file-invoice me-1"></i>ดูใบเสร็จ
                                                </a>
                                            <?php endif; ?>

                                            <!-- ปุ่มรีวิว (แสดงเมื่อมีใบเสร็จรับเงินและยังไม่ได้รีวิว) -->
                                            <?php if ($has_receipt && !$has_review && $booking['status'] == 'completed'): ?>
                                                <a href="review.php?booking_id=<?= $booking['booking_id'] ?>"
                                                    class="btn btn-warning mb-1">
                                                    <i class="fas fa-star me-1"></i>เขียนรีวิว
                                                </a>
                                            <?php elseif ($has_review): ?>
                                                <button class="btn btn-outline-success mb-1" disabled>
                                                    <i class="fas fa-check me-1"></i>รีวิวแล้ว
                                                </button>
                                            <?php endif; ?>

                                            <!-- ปุ่มชำระเงิน (แสดงเมื่อยังไม่ชำระเงิน) -->
                                            <?php if (empty($booking['payment_status']) || $booking['payment_status'] == 'pending'): ?>
                                                <a href="payment.php?booking_id=<?= $booking['booking_id'] ?>"
                                                    class="btn btn-primary mb-1">
                                                    <i class="fas fa-credit-card me-1"></i>ชำระเงิน
                                                </a>
                                            <?php endif; ?>

                                            <!-- ปุ่มยกเลิก -->
                                            <?php if ($can_cancel): ?>
                                                <button class="btn btn-danger mb-1" data-bs-toggle="modal"
                                                    data-bs-target="#cancelModal<?= $booking['booking_id'] ?>">
                                                    <i class="fas fa-times me-1"></i>ยกเลิก
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Cancel Modals - ย้ายออกมาจาก table -->
            <?php foreach ($bookings as $booking): ?>
                <?php
                $can_cancel = ($booking['status'] == 'pending' || $booking['status'] == 'confirmed') &&
                    strtotime($booking['booking_date'] . ' ' . $booking['start_time']) > time();
                ?>
                <?php if ($can_cancel): ?>
                    <div class="modal fade" id="cancelModal<?= $booking['booking_id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">ยกเลิกการจอง #<?= $booking['booking_id'] ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="cancel_booking" value="1">
                                    <input type="hidden" name="booking_id" value="<?= $booking['booking_id'] ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">เหตุผลที่ยกเลิก</label>
                                            <textarea name="cancel_reason" class="form-control" rows="3"
                                                placeholder="กรุณาระบุเหตุผลที่ยกเลิกการจอง"
                                                required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                        <button type="submit" class="btn btn-danger">ยืนยันการยกเลิก</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- แสดงจำนวนการจองทั้งหมด -->
            <div class="mt-3 text-end">
                <small class="text-muted">
                    แสดงทั้งหมด <?= count($bookings) ?> รายการ (เรียงลำดับจากล่าสุดไปเก่าสุด)
                </small>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'templates\footer-user.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>