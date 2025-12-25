<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// ดึงข้อมูลสถิติ
$stats = [];

// สถิติการจองวันนี้
$sql = "SELECT COUNT(*) as today_bookings FROM bookings WHERE DATE(booking_date) = CURDATE()";
$result = $conn->query($sql);
$stats['today_bookings'] = $result->fetch_assoc()['today_bookings'];

// สถิติการจองรอการยืนยัน
$sql = "SELECT COUNT(*) as pending_bookings FROM bookings WHERE status = 'pending'";
$result = $conn->query($sql);
$stats['pending_bookings'] = $result->fetch_assoc()['pending_bookings'];

// สถิติผู้ใช้ทั้งหมด
$sql = "SELECT COUNT(*) as total_users FROM users WHERE role = 'customer'";
$result = $conn->query($sql);
$stats['total_users'] = $result->fetch_assoc()['total_users'];

// สถิติหมอนวด - แก้ไขจาก is_active เป็น is_available
$sql = "SELECT COUNT(*) as total_therapists FROM therapists WHERE is_available = TRUE";
$result = $conn->query($sql);
$stats['total_therapists'] = $result->fetch_assoc()['total_therapists'];

// สถิติบริการ
$sql = "SELECT COUNT(*) as total_services FROM massage_types WHERE is_active = TRUE";
$result = $conn->query($sql);
$stats['total_services'] = $result->fetch_assoc()['total_services'];

// สถิติรายได้วันนี้
$sql = "SELECT COALESCE(SUM(amount), 0) as today_revenue FROM payments WHERE DATE(created_at) = CURDATE() AND payment_status = 'completed'";
$result = $conn->query($sql);
$stats['today_revenue'] = $result->fetch_assoc()['today_revenue'];

// สถิติรายได้เดือนนี้
$sql = "SELECT COALESCE(SUM(amount), 0) as monthly_revenue FROM payments WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND payment_status = 'completed'";
$result = $conn->query($sql);
$stats['monthly_revenue'] = $result->fetch_assoc()['monthly_revenue'];

// การจองล่าสุด (5 รายการ)
$recent_bookings = [];
$sql = "SELECT b.*, u.full_name as customer_name, mt.name as service_name, t.full_name as therapist_name 
        FROM bookings b 
        LEFT JOIN users u ON b.customer_id = u.user_id 
        LEFT JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id 
        LEFT JOIN therapists t ON b.therapist_id = t.therapist_id 
        ORDER BY b.created_at DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
}

// หมอนวดที่พร้อมให้บริการ - แก้ไขจาก is_active เป็น is_available
$available_therapists = [];
$sql = "SELECT * FROM therapists WHERE is_available = TRUE LIMIT 4";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $available_therapists[] = $row;
    }
}

// กราฟข้อมูลการจอง 7 วันย้อนหลัง
$booking_stats = [];
$sql = "SELECT DATE(booking_date) as date, COUNT(*) as count 
        FROM bookings 
        WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(booking_date) 
        ORDER BY date";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $booking_stats[] = $row;
    }
}

// หากไม่มีข้อมูลสำหรับกราฟ ให้สร้างข้อมูลตัวอย่าง
if (empty($booking_stats)) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $booking_stats[] = [
            'date' => $date,
            'count' => 0
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดผู้ดูแลระบบ</title>
    <?php include '../templates/admin-head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <!-- <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-speedometer2 me-2"></i>แดชบอร์ด
            </h1>
            <div>
                <span class="text-muted">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['full_name'] ?? 'ผู้ดูแลระบบ') ?>
                </span>
            </div>
        </div> -->

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-white-50">การจองวันนี้</h6>
                                <h2 class="card-title mb-0"><?= $stats['today_bookings'] ?></h2>
                                <small class="text-white-50">รอการยืนยัน: <?= $stats['pending_bookings'] ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-calendar-check"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">ผู้ใช้ทั้งหมด</h6>
                                <h2 class="card-title mb-0"><?= $stats['total_users'] ?></h2>
                                <small class="text-white-50">ลูกค้าระบบ</small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-people"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">ทีมหมอนวด</h6>
                                <h2 class="card-title mb-0"><?= $stats['total_therapists'] ?></h2>
                                <small class="text-white-50">พร้อมบริการ: <?= count($available_therapists) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-person-badge"></i>
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
                                <h6 class="card-subtitle mb-2 text-white-50">รายได้วันนี้</h6>
                                <h2 class="card-title mb-0">฿<?= number_format($stats['today_revenue'], 0) ?></h2>
                                <small class="text-white-50">เดือนนี้: ฿<?= number_format($stats['monthly_revenue'], 0) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Recent Items -->
        <div class="row g-4">
            <!-- Left Column - Chart -->
            <div class="col-lg-8">
                <!-- Quick Actions -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 icon-circle">
                                    <i class="bi bi-plus-lg fs-4"></i>
                                </div>
                                <h6 class="card-title mb-2">เพิ่มบริการ</h6>
                                <a href="manage_services.php?action=add" class="btn btn-primary btn-sm">เพิ่ม</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 icon-circle">
                                    <i class="bi bi-person-plus fs-4"></i>
                                </div>
                                <h6 class="card-title mb-2">เพิ่มหมอนวด</h6>
                                <a href="manage_therapists.php?action=add" class="btn btn-success btn-sm">เพิ่ม</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 icon-circle">
                                    <i class="bi bi-calendar-plus fs-4"></i>
                                </div>
                                <h6 class="card-title mb-2">จัดการตาราง</h6>
                                <a href="manage_schedule.php" class="btn btn-warning btn-sm">จัดการ</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 icon-circle">
                                    <i class="bi bi-graph-up fs-4"></i>
                                </div>
                                <h6 class="card-title mb-2">ดูรายงาน</h6>
                                <a href="reports.php" class="btn btn-info btn-sm">ดูรายงาน</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Chart -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up me-2"></i>สถิติการจอง 7 วันย้อนหลัง
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="bookingChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Column - Recent Items -->
            <div class="col-lg-4">
                <!-- Recent Bookings -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>การจองล่าสุด
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_bookings)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($booking['customer_name']) ?></h6>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($booking['service_name']) ?><br>
                                                    <?= date('d/m/Y H:i', strtotime($booking['booking_date'] . ' ' . $booking['start_time'])) ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php
                                                $badge_colors = [
                                                    'pending' => 'warning',
                                                    'confirmed' => 'info',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    'no_show' => 'secondary'
                                                ];
                                                echo $badge_colors[$booking['status']] ?? 'secondary';
                                            ?>">
                                                <?php
                                                $status_labels = [
                                                    'pending' => 'รอดำเนินการ',
                                                    'confirmed' => 'ยืนยันแล้ว',
                                                    'completed' => 'เสร็จสิ้น',
                                                    'cancelled' => 'ยกเลิก',
                                                    'no_show' => 'ไม่มาตามนัด'
                                                ];
                                                echo $status_labels[$booking['status']] ?? $booking['status'];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">ไม่มีข้อมูลการจอง</p>
                        <?php endif; ?>
                        <div class="text-center mt-3">
                            <a href="manage_bookings.php" class="btn btn-primary btn-sm">ดูทั้งหมด</a>
                        </div>
                    </div>
                </div>

                <!-- Available Therapists -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-person-check me-2"></i>หมอนวดพร้อมบริการ
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_therapists)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($available_therapists as $therapist): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center avatar-sm">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0"><?= htmlspecialchars($therapist['full_name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($therapist['specialization']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">ไม่มีหมอนวดพร้อมบริการ</p>
                        <?php endif; ?>
                        <div class="text-center mt-3">
                            <a href="manage_therapists.php" class="btn btn-success btn-sm">จัดการหมอนวด</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>

    <?php include '../templates/admin-scripts.php'; ?>
    
    <script>
        // Booking Chart
        const bookingCtx = document.getElementById('bookingChart').getContext('2d');
        const bookingChart = new Chart(bookingCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function ($item) {
                            return date('d/m', strtotime($item['date']));
                        }, $booking_stats)) ?>,
                datasets: [{
                    label: 'จำนวนการจอง',
                    data: <?= json_encode(array_column($booking_stats, 'count')) ?>,
                    borderColor: 'rgb(67, 97, 238)',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Auto refresh dashboard every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>

</html>
<?php $conn->close(); ?>