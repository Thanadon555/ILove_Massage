<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

// ตรวจสอบการล็อกอิน
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'customer';

// ดึงข้อมูลร้านจาก system_settings
$shop_info = [];
$sql = "SELECT * FROM system_settings WHERE setting_key IN ('shop_name', 'shop_phone', 'shop_address')";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shop_info[$row['setting_key']] = $row['setting_value'];
    }
}

// ดึงข้อมูลหมอนวด
$therapists = [];
$sql = "SELECT * FROM therapists WHERE is_available = TRUE LIMIT 6";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $therapists[] = $row;
    }
}

// ดึงข้อมูลบริการ
$services = [];
$sql = "SELECT * FROM massage_types WHERE is_active = TRUE LIMIT 4";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกี่ยวกับเรา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/customer-styles.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
        }

        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .therapist-card {
            transition: transform 0.3s;
        }

        .therapist-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>

<body>
    <?php include 'templates\navbar-user.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 mb-4">เกี่ยวกับ I Love Massage</h1>
            <p class="lead">เราให้บริการนวดแผนไทยคุณภาพสูง ด้วยทีมหมอนวดมืออาชีพที่มีประสบการณ์</p>
        </div>
    </section>

    <!-- เกี่ยวกับเรา -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-4">เรื่องราวของเรา</h2>
                    <p class="lead">
                        I Love Massage ก่อตั้งขึ้นด้วยความตั้งใจที่จะนำเสนอการนวดแผนไทยแท้
                        ที่ผสมผสานระหว่างภูมิปัญญาดั้งเดิมและเทคนิคสมัยใหม่
                    </p>
                    <p>
                        เราคัดสรรหมอนวดที่มีความเชี่ยวชาญและผ่านการฝึกอบรมมาอย่างดี
                        เพื่อให้บริการลูกค้าอย่างเต็มที่ ด้วยมาตรฐานการบริการระดับสูง
                        และสภาพแวดล้อมที่สะอาด สบาย ผ่อนคลาย
                    </p>
                    <p>
                        นอกจากการนวดเพื่อผ่อนคลายแล้ว เรายังให้คำแนะนำเกี่ยวกับการดูแลสุขภาพ
                        และการใช้ชีวิตอย่างสมดุล เพื่อสุขภาพที่ดีอย่างยั่งยืน
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <i class="fas fa-spa feature-icon"></i>
                            <h4>ความแตกต่างของเรา</h4>
                            <p class="text-muted">
                                เรามุ่งมั่นให้บริการด้วยใจ รักษามาตรฐานคุณภาพ
                                และใส่ใจในทุกรายละเอียดเพื่อความพึงพอใจของลูกค้า
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- วิสัยทัศน์และพันธกิจ -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-eye feature-icon"></i>
                    <h4>วิสัยทัศน์</h4>
                    <p class="text-muted">
                        เป็นสปานวดแผนไทยชั้นนำที่ได้รับการยอมรับในเรื่องคุณภาพบริการ
                        และความพึงพอใจของลูกค้า
                    </p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-bullseye feature-icon"></i>
                    <h4>พันธกิจ</h4>
                    <p class="text-muted">
                        ให้บริการนวดแผนไทยคุณภาพสูง ด้วยทีมงานมืออาชีพ
                        ในสภาพแวดล้อมที่สะอาด ปลอดภัย และผ่อนคลาย
                    </p>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <i class="fas fa-heart feature-icon"></i>
                    <h4>ค่านิยม</h4>
                    <p class="text-muted">
                        ใส่ใจบริการ มีความรับผิดชอบ รักษามาตรฐาน
                        และพัฒนาอย่างต่อเนื่อง
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ทีมหมอนวด -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">ทีมหมอนวดของเรา</h2>
            <div class="row">
                <?php foreach ($therapists as $therapist): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card therapist-card text-center h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="fas fa-user-md fa-3x text-primary"></i>
                                </div>
                                <h5 class="card-title"><?= $therapist['full_name'] ?></h5>
                                <p class="card-text text-muted"><?= $therapist['specialization'] ?></p>
                                <span class="badge bg-success">พร้อมให้บริการ</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- บริการของเรา -->
    <section class="bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5">บริการของเรา</h2>
            <div class="row">
                <?php foreach ($services as $service): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-hands fa-2x text-primary mb-3"></i>
                                <h6 class="card-title"><?= $service['name'] ?></h6>
                                <p class="card-text small text-muted">
                                    <?= $service['duration_minutes'] ?> นาที<br>
                                    ฿<?= number_format($service['price'], 2) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="booking.php" class="btn btn-primary btn-lg">จองคิวเลย</a>
            </div>
        </div>
    </section>

    <!-- ข้อมูลร้าน -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="text-center mb-0">ข้อมูลร้าน</h4>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <i class="fas fa-store fa-2x text-primary mb-2"></i>
                                    <h6>ชื่อร้าน</h6>
                                    <p class="text-muted">
                                        <?= isset($shop_info['shop_name']) ? $shop_info['shop_name'] : 'I Love Massage' ?>
                                    </p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <i class="fas fa-map-marker-alt fa-2x text-danger mb-2"></i>
                                    <h6>ที่อยู่</h6>
                                    <p class="text-muted">
                                        <?= isset($shop_info['shop_address']) ? $shop_info['shop_address'] : 'ช้างเผือก 30 ซอย สุขเกษม 1 ตำบลช้างเผือก เมือง เชียงใหม่ 50200' ?>
                                    </p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <i class="fas fa-phone fa-2x text-success mb-2"></i>
                                    <h6>โทรศัพท์</h6>
                                    <p class="text-muted">
                                        <?= isset($shop_info['shop_phone']) ? $shop_info['shop_phone'] : '082-6843254' ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col-md-6 mb-3">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <h6>เวลาทำการ</h6>
                                    <p class="text-muted">จันทร์ - อาทิตย์<br>09:00 - 21:00 น.</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <i class="fas fa-calendar-alt fa-2x text-warning mb-2"></i>
                                    <h6>วันหยุด</h6>
                                    <p class="text-muted">เปิดให้บริการทุกวัน<br>(ยกเว้นวันสำคัญ)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?= isset($shop_info['shop_name']) ? $shop_info['shop_name'] : 'I Love Massage' ?></h5>
                    <p class="mb-0">
                        <?= isset($shop_info['shop_address']) ? $shop_info['shop_address'] : 'ช้างเผือก 30 ซอย สุขเกษม 1 ตำบลช้างเผือก เมือง เชียงใหม่ 50200' ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-0">โทร:
                        <?= isset($shop_info['shop_phone']) ? $shop_info['shop_phone'] : '082-6843254' ?>
                    </p>
                </div>
            </div>
            <hr class="my-3">
            <div class="text-center">
                <p class="mb-0">&copy; 2024 I Love Massage. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>