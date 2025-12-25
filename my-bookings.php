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
$profile_image_path = 'uploads/profile/';

// จัดการการยกเลิกการจอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);

    // ตรวจสอบว่าเป็นการจองของผู้ใช้คนนี้
    $check_sql = "SELECT booking_id FROM bookings WHERE booking_id = ? AND customer_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $cancel_sql = "UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE booking_id = ?";
        $cancel_stmt = $conn->prepare($cancel_sql);
        $cancel_stmt->bind_param("i", $booking_id);

        if ($cancel_stmt->execute()) {
            $_SESSION['success_message'] = "ยกเลิกการจองเรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการยกเลิกการจอง";
        }
        $cancel_stmt->close();
    }
    $check_stmt->close();

    header('Location: my-bookings.php');
    exit();
}

// ดึงข้อมูลการจองทั้งหมดของผู้ใช้
$bookings_sql = "SELECT 
                    b.booking_id,
                    b.booking_date,
                    b.start_time,
                    b.end_time,
                    b.status,
                    b.total_price,
                    b.notes,
                    b.created_at,
                    mt.name as massage_type_name,
                    mt.duration_minutes,
                    t.full_name as therapist_name,
                    t.image_url as therapist_image,
                    t.specialization,
                    p.payment_status,
                    p.payment_method,
                    r.review_id,
                    r.rating,
                    r.comment as review_comment
                FROM bookings b
                INNER JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
                INNER JOIN therapists t ON b.therapist_id = t.therapist_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id
                LEFT JOIN reviews r ON b.booking_id = r.booking_id
                WHERE b.customer_id = ?
                ORDER BY b.booking_date DESC, b.start_time DESC";

$stmt = $conn->prepare($bookings_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$stmt->close();

// ฟังก์ชันแสดงสถานะ
function getStatusBadge($status)
{
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>รอยืนยัน</span>',
        'confirmed' => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>ยืนยันแล้ว</span>',
        'completed' => '<span class="badge bg-primary"><i class="fas fa-check-double me-1"></i>เสร็จสิ้น</span>',
        'cancelled' => '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>ยกเลิก</span>',
        'no_show' => '<span class="badge bg-secondary"><i class="fas fa-user-times me-1"></i>ไม่มาตามนัด</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">ไม่ทราบสถานะ</span>';
}

// ฟังก์ชันแสดงสถานะการชำระเงิน
function getPaymentStatusBadge($status)
{
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">รอชำระ</span>',
        'completed' => '<span class="badge bg-success">ชำระแล้ว</span>',
        'failed' => '<span class="badge bg-danger">ล้มเหลว</span>',
        'refunded' => '<span class="badge bg-info">คืนเงินแล้ว</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">-</span>';
}

// ฟังก์ชันแสดงดาว
function displayStars($rating)
{
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $stars .= '<i class="far fa-star text-warning"></i>';
        }
    }
    return $stars;
}

// คำนวณสถิติ
$total_bookings = count($bookings);
$completed_bookings = count(array_filter($bookings, function ($b) {
    return $b['status'] === 'completed'; }));
$pending_bookings = count(array_filter($bookings, function ($b) {
    return $b['status'] === 'pending' || $b['status'] === 'confirmed'; }));
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการจอง - ระบบจองคิวการนวด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .booking-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .therapist-avatar {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            border-left: 4px solid;
        }

        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px #0d6efd;
        }
    </style>
</head>

<body>
    <?php include 'templates/navbar-user.php'; ?>

    <!-- Header Section -->
    <section class="bg-primary text-white py-5 mt-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-history me-2"></i>ประวัติการจอง
                    </h1>
                    <p class="lead mb-0">จัดการและติดตามการจองของคุณ</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="booking.php" class="btn btn-warning btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>จองใหม่
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="container mt-4">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="container mt-4">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?>
                <button type-="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Statistics Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card stat-card border-primary shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check text-primary fs-1 mb-3"></i>
                            <h3 class="fw-bold text-primary mb-2"><?= $total_bookings ?></h3>
                            <p class="text-muted mb-0">การจองทั้งหมด</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-success shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-check-double text-success fs-1 mb-3"></i>
                            <h3 class="fw-bold text-success mb-2"><?= $completed_bookings ?></h3>
                            <p class="text-muted mb-0">เสร็จสิ้นแล้ว</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-warning shadow-sm h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clock text-warning fs-1 mb-3"></i>
                            <h3 class="fw-bold text-warning mb-2"><?= $pending_bookings ?></h3>
                            <p class="text-muted mb-0">กำลังดำเนินการ</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bookings List Section -->
    <section class="py-5">
        <div class="container">
            <?php if (count($bookings) > 0): ?>
                <div class="row g-4">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="col-12">
                            <div class="card booking-card shadow-sm">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Therapist Info -->
                                        <div class="col-md-3 text-center border-end">
                                            <?php if (!empty($booking['therapist_image']) && file_exists($profile_image_path . $booking['therapist_image'])): ?>
                                                <img src="<?= htmlspecialchars($profile_image_path . $booking['therapist_image']) ?>"
                                                    alt="<?= htmlspecialchars($booking['therapist_name']) ?>"
                                                    class="therapist-avatar mb-3">
                                            <?php else: ?>
                                                <div
                                                    class="therapist-avatar bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3">
                                                    <i class="fas fa-user-md text-primary fs-3"></i>
                                                </div>
                                            <?php endif; ?>
                                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($booking['therapist_name']) ?></h5>
                                            <p class="text-muted small mb-0"><?= htmlspecialchars($booking['specialization']) ?>
                                            </p>
                                        </div>

                                        <!-- Booking Details -->
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="card-title fw-bold mb-1">
                                                        <i class="fas fa-spa text-primary me-2"></i>
                                                        <?= htmlspecialchars($booking['massage_type_name']) ?>
                                                    </h5>
                                                    <span class="badge bg-info text-dark">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= $booking['duration_minutes'] ?> นาที
                                                    </span>
                                                </div>
                                                <div class="text-end">
                                                    <?= getStatusBadge($booking['status']) ?>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <i class="fas fa-calendar text-primary me-2"></i>
                                                <strong>วันที่:</strong>
                                                <?= date('d/m/Y', strtotime($booking['booking_date'])) ?>
                                                (<?= ['Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'][date('l', strtotime($booking['booking_date']))] ?>)
                                            </div>

                                            <div class="mb-2">
                                                <i class="fas fa-clock text-primary me-2"></i>
                                                <strong>เวลา:</strong>
                                                <?= date('H:i', strtotime($booking['start_time'])) ?> -
                                                <?= date('H:i', strtotime($booking['end_time'])) ?> น.
                                            </div>

                                            <div class="mb-2">
                                                <i class="fas fa-credit-card text-primary me-2"></i>
                                                <strong>การชำระเงิน:</strong>
                                                <?= getPaymentStatusBadge($booking['payment_status']) ?>
                                                <?php if ($booking['payment_method']): ?>
                                                    <span class="text-muted ms-2">
                                                        (<?= ['cash' => 'เงินสด', 'credit_card' => 'บัตรเครดิต', 'debit_card' => 'บัตรเดบิต', 'promptpay' => 'พร้อมเพย์', 'bank_transfer' => 'โอนธนาคาร'][$booking['payment_method']] ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($booking['notes']): ?>
                                                <div class="mb-2">
                                                    <i class="fas fa-sticky-note text-primary me-2"></i>
                                                    <strong>หมายเหตุ:</strong>
                                                    <span class="text-muted"><?= htmlspecialchars($booking['notes']) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Review Section -->
                                            <?php if ($booking['status'] === 'completed' && $booking['review_id']): ?>
                                                <div class="mt-3 p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-star text-warning me-2"></i>
                                                        <strong>รีวิวของคุณ:</strong>
                                                    </div>
                                                    <div class="mb-2">
                                                        <?= displayStars($booking['rating']) ?>
                                                    </div>
                                                    <p class="mb-0 text-muted"><?= htmlspecialchars($booking['review_comment']) ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Price & Actions -->
                                        <div class="col-md-3 border-start">
                                            <div class="text-center mb-3">
                                                <div class="text-muted small mb-1">ราคารวม</div>
                                                <div class="display-6 fw-bold text-primary">
                                                    ฿<?= number_format($booking['total_price'], 2) ?>
                                                </div>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <form method="POST"
                                                        onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกการจองนี้?');">
                                                        <input type="hidden" name="booking_id"
                                                            value="<?= $booking['booking_id'] ?>">
                                                        <button type="submit" name="cancel_booking"
                                                            class="btn btn-danger btn-sm w-100">
                                                            <i class="fas fa-times-circle me-1"></i>ยกเลิกการจอง
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($booking['status'] === 'completed' && !$booking['review_id']): ?>
                                                    <a href="write-review.php?booking_id=<?= $booking['booking_id'] ?>"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-star me-1"></i>เขียนรีวิว
                                                    </a>
                                                <?php endif; ?>

                                                <a href="booking-detail.php?id=<?= $booking['booking_id'] ?>"
                                                    class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>ดูรายละเอียด
                                                </a>
                                            </div>

                                            <div class="text-muted small text-center mt-3">
                                                <i class="far fa-calendar-plus me-1"></i>
                                                จองเมื่อ: <?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times text-muted" style="font-size: 80px;"></i>
                    <h3 class="mt-4 mb-3">ยังไม่มีประวัติการจอง</h3>
                    <p class="text-muted mb-4">คุณยังไม่เคยจองบริการกับเรา เริ่มจองเลยวันนี้!</p>
                    <a href="booking.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-plus me-2"></i>จองบริการ
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>