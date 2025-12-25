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

// ดึงข้อมูลผู้ใช้
$user_sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = $conn->query($user_sql);
$user = $user_result->fetch_assoc();

// ดึงสถิติการใช้งาน
$stats_sql = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN status = 'pending' OR status = 'confirmed' THEN 1 ELSE 0 END) as upcoming_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM bookings WHERE customer_id = '$user_id'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// ดึงการจองล่าสุด
$recent_booking_sql = "SELECT b.*, t.full_name as therapist_name, mt.name as service_name 
    FROM bookings b
    JOIN therapists t ON b.therapist_id = t.therapist_id
    JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
    WHERE b.customer_id = '$user_id' AND b.status != 'cancelled'
    ORDER BY b.booking_date DESC, b.start_time DESC LIMIT 1";
$recent_booking_result = $conn->query($recent_booking_sql);
$recent_booking = $recent_booking_result->num_rows > 0 ? $recent_booking_result->fetch_assoc() : null;

// อัพเดทข้อมูลผู้ใช้
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);

    // ตรวจสอบอีเมลซ้ำ
    $check_sql = "SELECT user_id FROM users WHERE email = '$email' AND user_id != '$user_id'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $error = 'อีเมลนี้มีผู้ใช้แล้ว';
    } else {
        $update_sql = "UPDATE users SET full_name = '$full_name', email = '$email', phone = '$phone', updated_at = NOW() WHERE user_id = '$user_id'";

        if ($conn->query($update_sql)) {
            $_SESSION['full_name'] = $full_name;
            $success = 'อัพเดทข้อมูลสำเร็จ';
            // โหลดข้อมูลใหม่
            $user_result = $conn->query($user_sql);
            $user = $user_result->fetch_assoc();
        } else {
            $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }
    }
}

// เปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // ตรวจสอบรหัสผ่านปัจจุบัน (plain text comparison)
    if ($current_password !== $user['password_hash']) {
        $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่ไม่ตรงกัน';
    } elseif (strlen($new_password) < 6) {
        $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        // เก็บรหัสผ่านใหม่แบบ plain text
        $new_password_hash = $new_password;
        $update_sql = "UPDATE users SET password_hash = '$new_password_hash', updated_at = NOW() WHERE user_id = '$user_id'";

        if ($conn->query($update_sql)) {
            $success = 'เปลี่ยนรหัสผ่านสำเร็จ';
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
    <title>โปรไฟล์ของฉัน - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/customer-styles.css">
    <style>
        /* Global Styles from index.php */
        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            background-color: #f8fdfb;
        }

        /* Profile Hero Section */
        .profile-hero {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%);
            padding: 3rem 0;
            margin-top: 76px;
            margin-bottom: 3rem;
            color: white;
        }

        /* Card Styles */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #e6f7f3;
            background: #ffffff;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(79, 195, 161, 0.15) !important;
        }

        .card-header {
            border-radius: 15px 15px 0 0 !important;
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%) !important;
            color: white !important;
            border: none;
        }

        /* Icon Styles */
        .fas, .fab {
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

        .btn-primary {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #38b2ac, #2c9c8a);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 195, 161, 0.3);
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

        .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }

        .btn-outline-danger:hover {
            background: #dc3545;
            transform: translateY(-2px);
            color: #ffffff;
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

        /* Badge Styles */
        .badge {
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 0.85em;
        }

        .badge.bg-success {
            background: linear-gradient(45deg, #68d391, #48bb78) !important;
        }

        /* Text Colors */
        .text-primary {
            color: #4fc3a1 !important;
        }

        .text-success {
            color: #48bb78 !important;
        }

        .text-warning {
            color: #f6ad55 !important;
        }

        .text-danger {
            color: #fc8181 !important;
        }

        /* Form Styles */
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

        .form-label {
            color: #2d5a5a;
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
        }

        .alert-info {
            background: linear-gradient(135deg, #bee3f8, #90cdf4);
            color: #2c5282;
        }

        /* Shadow Styles */
        .shadow-sm {
            box-shadow: 0 2px 10px rgba(79, 195, 161, 0.08) !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-hero {
                padding: 2rem 0;
            }

            .display-4 {
                font-size: 2.5rem;
            }
        }

        /* Animation */
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
    </style>
</head>

<body>
    <?php include 'templates/navbar-user.php'; ?>
    <!-- Hero Section -->
    <section class="profile-hero">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-2">
                <i class="fas fa-user-circle me-2"></i>โปรไฟล์ของฉัน
            </h1>
            <p class="lead mb-0">จัดการข้อมูลส่วนตัวและความปลอดภัยของบัญชี</p>
        </div>
    </section>

    <div class="container mb-5">
        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Info Card -->
            <div class="col-lg-4 mb-4">
                <!-- User Info Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
                        <p class="text-muted mb-2">
                            <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
                        </p>
                        <p class="text-muted mb-3">
                            <i class="fas fa-phone me-1"></i><?= htmlspecialchars($user['phone']) ?>
                        </p>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>บัญชีใช้งานได้
                        </span>
                        <hr class="my-3">
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-1"></i>จองคิวใหม่
                            </a>
                            <a href="booking_history.php" class="btn btn-outline-primary">
                                <i class="fas fa-history me-1"></i>ประวัติการจอง
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt me-1"></i>ออกจากระบบ
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>สถิติการใช้งาน</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="fas fa-calendar-check text-primary me-2"></i>การจองทั้งหมด</span>
                                <strong class="text-primary"><?= $stats['total_bookings'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="fas fa-check-circle text-success me-2"></i>เสร็จสมบูรณ์</span>
                                <strong class="text-success"><?= $stats['completed_bookings'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="fas fa-clock text-warning me-2"></i>กำลังจะมาถึง</span>
                                <strong class="text-warning"><?= $stats['upcoming_bookings'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-times-circle text-danger me-2"></i>ยกเลิก</span>
                                <strong class="text-danger"><?= $stats['cancelled_bookings'] ?></strong>
                            </div>
                        </div>
                        
                        <?php if ($recent_booking): ?>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted d-block mb-2">การจองล่าสุด</small>
                            <div class="alert alert-info mb-0 py-2">
                                <small>
                                    <strong><?= htmlspecialchars($recent_booking['service_name']) ?></strong><br>
                                    <?= date('d/m/Y', strtotime($recent_booking['booking_date'])) ?> 
                                    เวลา <?= date('H:i', strtotime($recent_booking['start_time'])) ?> น.
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Forms Section -->
            <div class="col-lg-8">
                <!-- Update Profile Form -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>แก้ไขข้อมูลส่วนตัว
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user me-1"></i>ชื่อ-นามสกุล
                                </label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-1"></i>อีเมล
                                </label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-phone me-1"></i>เบอร์โทรศัพท์
                                </label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($user['phone']) ?>" required>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>บันทึกการเปลี่ยนแปลง
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock me-1"></i>รหัสผ่านปัจจุบัน
                                </label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock me-1"></i>รหัสผ่านใหม่
                                </label>
                                <input type="password" name="new_password" class="form-control" 
                                       minlength="6" required>
                                <small class="text-muted">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock me-1"></i>ยืนยันรหัสผ่านใหม่
                                </label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       minlength="6" required>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-1"></i>เปลี่ยนรหัสผ่าน
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>