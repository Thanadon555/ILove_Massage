<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';

// ตรวจสอบการล็อกอิน
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'customer';

// ตั้งค่า path สำหรับรูปภาพ (relative to admin folder)
$services_image_path = '../uploads/services/';
$profile_image_path = '../uploads/profile/';

// ดึงข้อมูลบริการนวด
$massageTypes = [];
$sql = "SELECT * FROM massage_types WHERE is_active = TRUE LIMIT 6";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $massageTypes[] = $row;
    }
}

// ดึงข้อมูลหมอนวด
$therapists = [];
$sql = "SELECT * FROM therapists WHERE is_available = TRUE LIMIT 4";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $therapists[] = $row;
    }
}

// ดึงข้อมูลวันหยุดที่ยังไม่หมดอายุ (เฉพาะเมื่อยังไม่ได้ล็อกอิน)
$holidays = [];
if (!$isLoggedIn) {
    $today = date('Y-m-d');
    $sql = "SELECT * FROM holidays WHERE holiday_date >= ? AND is_closed = 1 ORDER BY holiday_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $holidays[] = $row;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจองคิวการนวด - I Love Massage</title>
    
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Font Awesome (for additional icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS/customer-styles.css - โทนสีเย็นสดชื่น */

        /* Global Styles */
        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            background-color: #f8fdfb;
        }

        /* Hero Section */
        .bg-primary {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%) !important;
        }

        .hero-section {
            margin-top: 76px;
        }

        /* Card Styles */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #e6f7f3;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(79, 195, 161, 0.15) !important;
        }

        .card.border-0 {
            background: #ffffff;
        }

        /* Service Images */
        .service-image {
            height: 200px;
            width: 100%;
            object-fit: cover;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }

        .placeholder-image {
            background: linear-gradient(45deg, #4fc3a1, #38b2ac);
            display: flex;
            align-items: center;
            justify-content: center;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }

        /* Therapist Avatars */
        .therapist-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e6f7f3;
        }

        .therapist-avatar.bg-primary {
            border-radius: 50%;
            background: #e6f7f3 !important;
        }

        /* Icon Styles */
        .fas,
        .fab {
            transition: transform 0.3s ease;
        }

        .card:hover .fas,
        .card:hover .fab {
            transform: scale(1.1);
        }

        .fas.text-primary {
            color: #4fc3a1 !important;
        }

        /* Button Styles */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-warning {
            background: linear-gradient(45deg, #68d391, #48bb78);
            color: #ffffff;
        }

        .btn-warning:hover {
            background: linear-gradient(45deg, #48bb78, #38a169);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(104, 211, 145, 0.4);
            color: #ffffff;
        }

        .btn-outline-primary {
            border: 2px solid #4fc3a1;
            color: #4fc3a1;
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            border-color: #4fc3a1;
            transform: translateY(-2px);
            color: #ffffff;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #38b2ac, #2c9c8a);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 195, 161, 0.3);
        }

        /* Badge Styles */
        .badge {
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 0.85em;
        }

        .badge.bg-success {
            background: linear-gradient(45deg, #68d391, #48bb78) !important;
        }

        /* Footer Styles */
        footer {
            background: linear-gradient(135deg, #2d5a5a 0%, #234e52 100%) !important;
        }

        footer a {
            transition: color 0.3s ease;
        }

        footer a:hover {
            color: #68d391 !important;
        }

        /* Section Backgrounds */
        .bg-light {
            background-color: #f0f9f7 !important;
        }

        /* Text Colors */
        .text-primary {
            color: #4fc3a1 !important;
        }

        .text-warning {
            color: #68d391 !important;
        }

        /* Section Headers */
        h2.text-dark {
            color: #2d5a5a !important;
        }

        /* Card Titles */
        .card-title {
            color: #2d5a5a;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .display-4 {
                font-size: 2.5rem;
            }

            .lead.fs-4 {
                font-size: 1.1rem !important;
            }

            .therapist-avatar {
                width: 100px;
                height: 100px;
            }

            .service-image {
                height: 180px;
            }
        }

        @media (max-width: 576px) {
            .hero-section .container {
                padding-top: 2rem !important;
                padding-bottom: 2rem !important;
            }

            .btn-lg {
                padding: 12px 24px;
                font-size: 1rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Hover Effects */
        .shadow-sm {
            box-shadow: 0 2px 10px rgba(79, 195, 161, 0.08) !important;
        }

        /* Social Media Icons */
        .fab {
            transition: all 0.3s ease;
        }

        .fab:hover {
            transform: scale(1.2);
            color: #68d391 !important;
        }

        /* Service Card Price */
        .text-primary.fw-bold {
            font-size: 1.2em;
            color: #4fc3a1 !important;
        }

        /* Duration Text */
        .text-muted .fas {
            color: #718096;
        }

        /* Navbar Fix for Fixed Top */
        .navbar-fixed-top {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
        }

        /* Section Spacing */
        section {
            padding-top: 4rem;
            padding-bottom: 4rem;
        }

        /* Heading Styles */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: 700;
            color: #2d5a5a;
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

        /* Additional Fresh Elements */
        .hero-section h1 {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            background: #ffffff;
        }

        /* Price Styling */
        .text-primary.fw-bold {
            background: linear-gradient(45deg, #4fc3a1, #38b2ac);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Contact Icons */
        .fas.text-warning {
            color: #68d391 !important;
        }

        /* Footer Headings */
        footer h5 {
            color: #68d391 !important;
        }

        /* Service Card Border */
        .card.shadow-sm {
            border-left: 3px solid #4fc3a1;
        }
    </style>
</head>

<body>
   <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-spa me-1"></i><span class="d-none d-md-inline">I Love Massage</span><span class="d-md-none">ILM</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
            </ul>

            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <span class="d-none d-lg-inline ms-1"><?= htmlspecialchars($_SESSION['full_name'] ?? 'ผู้ดูแลระบบ') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person-circle me-2"></i>โปรไฟล์
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span class="d-none d-lg-inline ms-1">เข้าสู่ระบบ</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
    
    <!-- Hero Section -->
    <section class="bg-primary text-white py-5 mt-5">
        <div class="container text-center py-5">
            <h1 class="display-4 fw-bold mb-4">I Love Massage</h1>
            <p class="lead fs-4 mb-4">บริการนวดแผนไทยคุณภาพสูง โดยทีมหมอนวดมืออาชีพ</p>
            <a href="booking.php" class="btn btn-warning btn-lg fw-bold px-4 py-2">
                <i class="fas fa-calendar-plus me-2"></i>จองคิวนวดตอนนี้
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <i class="fas fa-clock text-primary fs-1 mb-3"></i>
                            <h4 class="card-title">จองคิวออนไลน์</h4>
                            <p class="card-text text-muted">จองคิวได้ตลอด 24 ชั่วโมง ไม่ต้องรอสาย</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <i class="fas fa-user-md text-primary fs-1 mb-3"></i>
                            <h4 class="card-title">ทีมหมอนวดมืออาชีพ</h4>
                            <p class="card-text text-muted">ทีมงานคุณภาพผ่านการฝึกอบรมและมีประสบการณ์</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <i class="fas fa-leaf text-primary fs-1 mb-3"></i>
                            <h4 class="card-title">บริการคุณภาพ</h4>
                            <p class="card-text text-muted">ใช้น้ำมันหอมระเหยและเทคนิคการนวดชั้นเยี่ยม</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold text-dark">บริการของเรา</h2>
            <div class="row g-4">
                <?php foreach ($massageTypes as $service): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 shadow-sm">
                            <?php if (!empty($service['image_url']) && file_exists($services_image_path . $service['image_url'])): ?>
                                <img src="<?= htmlspecialchars($services_image_path . $service['image_url']) ?>"
                                    alt="<?= htmlspecialchars($service['name']) ?>" class="service-image"
                                    onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'service-image placeholder-image\'><i class=\'fas fa-hands text-white fs-1\'></i></div>';">
                            <?php else: ?>
                                <div class="service-image placeholder-image">
                                    <i class="fas fa-hands text-white fs-1"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body text-center">
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($service['name']) ?></h5>
                                <p class="card-text text-muted"><?= htmlspecialchars($service['description']) ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= htmlspecialchars($service['duration_minutes']) ?> นาที
                                    </span>
                                    <span class="text-primary fw-bold">
                                        ฿<?= number_format($service['price'], 2) ?>
                                    </span>
                                </div>
                                <a href="manage_services.php" class="btn btn-outline-primary mt-3 w-100">
                                    <i class="fas fa-calendar-plus me-2"></i>จองบริการ
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="manage_services.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-list me-2"></i>ดูบริการทั้งหมด
                </a>
            </div>
        </div>
    </section>

    <!-- Therapists Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold text-dark">ทีมหมอนวดของเรา</h2>
            <div class="row g-4">
                <?php foreach ($therapists as $therapist): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 shadow-sm text-center">
                            <div class="card-body">
                                <div class="position-relative mb-3">
                                    <?php if (!empty($therapist['image_url']) && file_exists($profile_image_path . $therapist['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($profile_image_path . $therapist['image_url']) ?>"
                                            alt="<?= htmlspecialchars($therapist['full_name']) ?>" class="therapist-avatar"
                                            onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'therapist-avatar bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center\'><i class=\'fas fa-user-md text-primary fs-3\'></i></div>';">
                                    <?php else: ?>
                                        <div
                                            class="therapist-avatar bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mx-auto">
                                            <i class="fas fa-user-md text-primary fs-3"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($therapist['full_name']) ?></h5>
                                <p class="card-text text-muted"><?= htmlspecialchars($therapist['specialization']) ?></p>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>พร้อมให้บริการ
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="manage_therapists.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-users me-2"></i>ดูทีมงานทั้งหมด
                </a>
            </div>
        </div>
    </section>

    <!-- Holiday Notification Modal -->
    <?php if (!empty($holidays)): ?>
    <div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
                <!-- Header with gradient matching site theme -->
                <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #ef476f 0%, #f78c6b 100%); padding: 1.5rem;">
                    <h5 class="modal-title fw-bold" id="holidayModalLabel">
                        <i class="fas fa-calendar-times me-2"></i>ประกาศวันหยุดร้าน I Love Massage
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <!-- Body with styled content -->
                <div class="modal-body p-4" style="background: linear-gradient(to bottom, #fff9f5 0%, #ffffff 100%);">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" 
                             style="width: 80px; height: 80px; background: linear-gradient(135deg, #ffd166 0%, #ffb84d 100%); box-shadow: 0 4px 15px rgba(255, 209, 102, 0.3);">
                            <i class="fas fa-spa text-white" style="font-size: 2.5rem;"></i>
                        </div>
                        <h6 class="text-muted mb-0">
                            <i class="fas fa-info-circle me-1"></i>กรุณาทราบ: ร้านจะปิดให้บริการในวันดังต่อไปนี้
                        </h6>
                    </div>

                    <?php foreach ($holidays as $index => $holiday): ?>
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 15px; border-left: 4px solid #4fc3a1;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0 me-3">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px; background: linear-gradient(135deg, #4fc3a1, #38b2ac);">
                                        <i class="fas fa-calendar-day text-white fs-5"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge rounded-pill me-2" style="background: linear-gradient(45deg, #68d391, #48bb78); padding: 0.5rem 1rem;">
                                            วันที่ <?= $index + 1 ?>
                                        </span>
                                        <h6 class="mb-0 fw-bold" style="color: #2d5a5a;">
                                            <?= date('d/m/Y', strtotime($holiday['holiday_date'])) ?>
                                        </h6>
                                    </div>
                                    <p class="mb-2 text-muted">
                                        <i class="fas fa-clock me-1" style="color: #4fc3a1;"></i>
                                        <strong>วัน:</strong> 
                                        <?php
                                        $days_th = ['Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 
                                                   'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'];
                                        $day_en = date('l', strtotime($holiday['holiday_date']));
                                        echo $days_th[$day_en];
                                        ?>
                                    </p>
                                    <?php if (!empty($holiday['description'])): ?>
                                    <div class="alert alert-light border-0 mb-0 py-2 px-3" style="background-color: #f0f9f7; border-radius: 10px;">
                                        <i class="fas fa-comment-dots me-1" style="color: #4fc3a1;"></i>
                                        <small class="text-muted"><?= htmlspecialchars($holiday['description']) ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Info box -->
                    <div class="card border-0 mt-4" style="background: linear-gradient(135deg, #e6f7f3 0%, #f0f9f7 100%); border-radius: 15px;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-lightbulb text-warning fs-3 me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-2" style="color: #2d5a5a;">
                                        <i class="fas fa-hand-point-right me-1"></i>คำแนะนำ
                                    </h6>
                                    <p class="mb-0 text-muted small">
                                        เพื่อความสะดวกของท่าน กรุณาวางแผนการจองล่วงหน้า 
                                        หรือติดต่อสอบถามเพิ่มเติมได้ที่ <strong style="color: #4fc3a1;">082-6843254</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer with gradient button -->
                <div class="modal-footer border-0 bg-light" style="padding: 1.25rem;">
                    <button type="button" class="btn btn-lg w-100 text-white fw-bold" data-bs-dismiss="modal"
                            style="background: linear-gradient(135deg, #4fc3a1, #38b2ac); border: none; border-radius: 12px; padding: 0.75rem;">
                        <i class="fas fa-check-circle me-2"></i>รับทราบแล้ว
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="text-center text-light">
            <p class="mb-0">&copy; 2025 I Love Massage. All rights reserved.</p>
        </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3.2 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($holidays)): ?>
    <script>
        // แสดง modal วันหยุดเมื่อโหลดหน้าเว็บ
        document.addEventListener('DOMContentLoaded', function() {
            // ตรวจสอบว่าผู้ใช้เคยเห็น modal แล้วหรือยัง (ใน session นี้)
            if (!sessionStorage.getItem('holidayModalShown')) {
                var holidayModal = new bootstrap.Modal(document.getElementById('holidayModal'));
                holidayModal.show();
                sessionStorage.setItem('holidayModalShown', 'true');
            }
        });
    </script>
    <?php endif; ?>
</body>

</html>
<?php $conn->close(); ?>