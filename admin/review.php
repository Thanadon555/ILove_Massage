<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/validation.php';
require_once 'includes/db_helper.php';
require_once 'includes/error_logger.php';

// ตรวจสอบการล็อกอินและสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// สร้าง DatabaseHelper instance
$dbHelper = new DatabaseHelper($conn);

// ตัวแปรสำหรับเก็บข้อความ
$success = '';
$error = '';

// ตั้งค่าการกรอง
$filter_rating = $_GET['rating'] ?? '';
$filter_search = $_GET['search'] ?? '';

// ดึงข้อมูลรีวิวจากลูกค้า
try {
    $where_conditions = [];
    $params = [];
    $types = "";

    if ($filter_rating) {
        $where_conditions[] = "r.rating = ?";
        $params[] = $filter_rating;
        $types .= "i";
    }

    if ($filter_search) {
        $filter_search = strip_tags($filter_search);
        $where_conditions[] = "(u.full_name LIKE ? OR r.comment LIKE ? OR t.full_name LIKE ?)";
        $search_term = "%{$filter_search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }

    $where_sql = "";
    if (!empty($where_conditions)) {
        $where_sql = "WHERE " . implode(" AND ", $where_conditions);
    }

    $sql = "SELECT r.*, 
               u.full_name as customer_name, 
               u.email as customer_email, 
               u.phone as customer_phone,
               t.full_name as therapist_name,
               b.booking_date,
               DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as formatted_date
        FROM reviews r
        LEFT JOIN users u ON r.customer_id = u.user_id
        LEFT JOIN therapists t ON r.therapist_id = t.therapist_id
        LEFT JOIN bookings b ON r.booking_id = b.booking_id
        $where_sql
        ORDER BY r.created_at DESC";

    if (!empty($params)) {
        $reviews = $dbHelper->fetchAll($sql, $params, $types);
    } else {
        $reviews = $dbHelper->fetchAll($sql);
    }
} catch (Exception $e) {
    logError('Database error fetching reviews: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    $error = 'เกิดข้อผิดพลาดในการดึงข้อมูลรีวิว';
    $reviews = [];
}

// นับสถิติรีวิว
try {
    $review_stats = $dbHelper->fetchOne("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as low_rating
        FROM reviews
    ");
    
    if (!$review_stats) {
        $review_stats = ['total_reviews' => 0, 'avg_rating' => 0, 'five_star' => 0, 'four_star' => 0, 'three_star' => 0, 'low_rating' => 0];
    }
} catch (Exception $e) {
    logError('Database error fetching review stats: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    $review_stats = ['total_reviews' => 0, 'avg_rating' => 0, 'five_star' => 0, 'four_star' => 0, 'three_star' => 0, 'low_rating' => 0];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรีวิวลูกค้า - ระบบจัดการนวด</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-star-fill text-warning me-2"></i>
                <i class="bi bi-chat-heart text-danger me-2"></i>
                จัดการรีวิวลูกค้า
            </h1>
        </div>

        <!-- แสดงข้อความแจ้งเตือน -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>ข้อผิดพลาด!</strong> <?= $error ?>
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

        <!-- สถิติรีวิว -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #ffd166 0%, #ffb84d 100%);">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= number_format($review_stats['avg_rating'], 1) ?></h2>
                        <p class="card-text mb-0">คะแนนเฉลี่ย</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-chat-heart"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $review_stats['total_reviews'] ?></h2>
                        <p class="card-text mb-0">รีวิวทั้งหมด</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-emoji-smile"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $review_stats['five_star'] ?></h2>
                        <p class="card-text mb-0">5 ดาว</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-emoji-frown"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $review_stats['low_rating'] ?></h2>
                        <p class="card-text mb-0">ต่ำกว่า 3 ดาว</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองรีวิว</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">คะแนน</label>
                        <select name="rating" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="5" <?= $filter_rating == '5' ? 'selected' : '' ?>>5 ดาว</option>
                            <option value="4" <?= $filter_rating == '4' ? 'selected' : '' ?>>4 ดาว</option>
                            <option value="3" <?= $filter_rating == '3' ? 'selected' : '' ?>>3 ดาว</option>
                            <option value="2" <?= $filter_rating == '2' ? 'selected' : '' ?>>2 ดาว</option>
                            <option value="1" <?= $filter_rating == '1' ? 'selected' : '' ?>>1 ดาว</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-select"
                            placeholder="ค้นหาชื่อลูกค้าหรือความคิดเห็น"
                            value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-warning me-2">
                            <i class="bi bi-search me-1"></i>กรอง
                        </button>
                        <a href="review.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>ล้าง
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- รายการรีวิว -->
        <div class="card">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-stars me-2"></i>รายการรีวิวจากลูกค้า</h5>
                <span class="badge bg-dark">แสดง <?= count($reviews) ?> รายการ</span>
            </div>
            <div class="card-body">
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-star fs-1 text-muted mb-3"></i>
                        <h5>ยังไม่มีรีวิวจากลูกค้า</h5>
                        <p class="text-muted">รอลูกค้าส่งรีวิวเข้ามาในระบบ</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($reviews as $review): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-0 fw-bold">
                                                    <i class="bi bi-person-circle me-1"></i>
                                                    <?= htmlspecialchars($review['customer_name'] ?? 'ลูกค้า') ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i><?= $review['formatted_date'] ?>
                                                </small>
                                            </div>
                                            <div class="text-warning fs-5">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($review['therapist_name']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="bi bi-person-badge me-1"></i>
                                                    <strong>นักบำบัด:</strong> <?= htmlspecialchars($review['therapist_name']) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <p class="card-text mb-0"><?= nl2br(htmlspecialchars($review['comment'] ?? 'ไม่มีความคิดเห็น')) ?></p>
                                        </div>
                                        
                                        <div class="border-top pt-2">
                                            <?php if ($review['customer_email']): ?>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($review['customer_email']) ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($review['booking_date']): ?>
                                                <small class="text-muted d-block">
                                                    <i class="bi bi-calendar-check me-1"></i>
                                                    วันที่จอง: <?= date('d/m/Y', strtotime($review['booking_date'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
</body>

</html>
