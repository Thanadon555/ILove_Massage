<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

// ตั้งค่าตัวแปร
$search = '';
$services = [];

// ตั้งค่า path สำหรับรูปภาพบริการ - ลองใช้หลายแบบ
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = '/massage/'; // เปลี่ยนตามโครงสร้างโปรเจคของคุณ

// ลองใช้ path ต่างๆ
$services_image_path = $base_url . $project_path . 'uploads/services/';
// หรือลองแบบนี้ถ้ายังไม่ขึ้น:
// $services_image_path = 'http://localhost/massage/uploads/services/';
// $services_image_path = '/massage/uploads/services/';

// ดึงข้อมูลบริการ
$sql = "SELECT * FROM massage_types WHERE is_active = TRUE";

// ตรวจสอบการค้นหา
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
}

// เรียงลำดับตามชื่อบริการ
$sql .= " ORDER BY name";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Debug information
$debug_info = [];
foreach ($services as $service) {
    $image_path = 'uploads/services/' . $service['image_url'];
    $full_image_path = $_SERVER['DOCUMENT_ROOT'] . $project_path . 'uploads/services/' . $service['image_url'];

    $debug_info[] = [
        'service_name' => $service['name'],
        'image_url' => $service['image_url'],
        'relative_path' => $image_path,
        'full_path' => $full_image_path,
        'file_exists' => file_exists($full_image_path)
    ];
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บริการนวด - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/customer-styles.css">
    <style>
        .service-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .service-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }

        .placeholder-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #198754;
        }

        .duration-badge {
            background-color: #6c757d;
            color: white;
        }

        .hero-section {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 40px;
        }

        .filter-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
    </style>
</head>

<body>
    <?php include 'templates/navbar-user.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold">บริการนวดของเรา</h1>
            <p class="lead">ค้นพบบริการนวดหลากหลายรูปแบบเพื่อการผ่อนคลายอย่างแท้จริง</p>
        </div>
    </section>

    <div class="container">
        <!-- Debug Section (สามารถลบออกได้หลังจากแก้ปัญหาแล้ว) -->
        <div class="alert alert-info d-none" id="debugInfo">
            <h5>Debug Information:</h5>
            <?php foreach ($debug_info as $debug): ?>
                <div class="mb-2">
                    <strong><?= $debug['service_name'] ?>:</strong><br>
                    Image URL: <?= $debug['image_url'] ?><br>
                    Relative Path: <?= $debug['relative_path'] ?><br>
                    Full Path: <?= $debug['full_path'] ?><br>
                    File Exists: <span class="<?= $debug['file_exists'] ? 'text-success' : 'text-danger' ?>">
                        <?= $debug['file_exists'] ? 'Yes' : 'No' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="services.php">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="search" class="form-label">ค้นหาบริการ</label>
                        <input type="text" class="form-control" id="search" name="search"
                            value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อบริการหรือรายละเอียด...">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Services Count -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>บริการทั้งหมด (<?= count($services) ?> รายการ)</h3>
            <!-- <div>
                <button class="btn btn-outline-info btn-sm me-2" onclick="toggleDebug()">Debug</button>
                <?php if ($search): ?>
                    <a href="services.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>ล้างตัวกรอง
                    </a>
                <?php endif; ?>
            </div> -->
        </div>

        <!-- Services Grid -->
        <?php if (empty($services)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">ไม่พบบริการที่คุณค้นหา</h4>
                <p class="text-muted">ลองเปลี่ยนคำค้นหาดูนะคะ</p>
                <a href="services.php" class="btn btn-primary">ดูบริการทั้งหมด</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($services as $service): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card service-card h-100 position-relative">
                            <!-- Status Badge -->
                            <?php if (!$service['is_active']): ?>
                                <span class="badge bg-danger status-badge">ไม่พร้อมให้บริการ</span>
                            <?php endif; ?>

                            <!-- Service Image -->
                            <?php
                            // ลองใช้ path ต่างๆ
                            $image_url = $services_image_path . $service['image_url'];
                            $relative_path = 'uploads/services/' . $service['image_url'];
                            $absolute_path = $base_url . $project_path . 'uploads/services/' . $service['image_url'];
                            ?>

                            <?php if (!empty($service['image_url'])): ?>
                                <!-- ลองใช้ทั้ง relative และ absolute path -->
                                <img src="<?= htmlspecialchars($relative_path) ?>" class="card-img-top service-image"
                                    alt="<?= htmlspecialchars($service['name']) ?>"
                                    onerror="this.onerror=null; this.src='<?= htmlspecialchars($absolute_path) ?>';">
                                <div class="placeholder-image" style="display: none;">
                                    <i class="fas fa-spa fa-3x"></i>
                                </div>
                            <?php else: ?>
                                <div class="placeholder-image">
                                    <i class="fas fa-spa fa-3x"></i>
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <!-- Service Name -->
                                <h5 class="card-title"><?= htmlspecialchars($service['name']) ?></h5>

                                <!-- Service Description -->
                                <p class="card-text flex-grow-1 text-muted">
                                    <?= nl2br(htmlspecialchars($service['description'] ?? 'ไม่มีคำอธิบาย')) ?>
                                </p>

                                <!-- Price and Duration -->
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <div class="price-tag">
                                        ฿<?= number_format($service['price'], 2) ?>
                                    </div>
                                    <span class="badge duration-badge">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= $service['duration_minutes'] ?> นาที
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-3">
                                    <?php if (isset($_SESSION['user_id']) && $service['is_active']): ?>
                                        <a href="booking.php?service_id=<?= $service['massage_type_id'] ?>"
                                            class="btn btn-success w-100">
                                            <i class="fas fa-calendar-plus me-2"></i>จองบริการนี้
                                        </a>
                                    <?php elseif (!$service['is_active']): ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-times me-2"></i>ไม่พร้อมให้บริการ
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบเพื่อจอง
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-spa me-2"></i>I Love Massage</h5>
                    <p class="mb-0">ช้างเผือก 30 ซอย สุขเกษม 1 ตำบลช้างเผือก เมือง เชียงใหม่ 50200</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">โทร: 082-6843254</p>
                </div>
            </div>
            <hr class="my-3">
            <div class="text-center">
                <p class="mb-0">&copy; 2024 I Love Massage. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debug function to check image loading
        document.addEventListener('DOMContentLoaded', function () {
            const images = document.querySelectorAll('img.service-image');
            images.forEach(img => {
                img.onerror = function () {
                    console.log('Image failed to load:', this.src);
                    this.style.display = 'none';
                    const placeholder = this.nextElementSibling;
                    if (placeholder && placeholder.classList.contains('placeholder-image')) {
                        placeholder.style.display = 'flex';
                    }
                };
                img.onload = function () {
                    console.log('Image loaded successfully:', this.src);
                };
            });
        });

        function toggleDebug() {
            const debugInfo = document.getElementById('debugInfo');
            debugInfo.classList.toggle('d-none');
        }

        // Test image URLs
        function testImageUrls() {
            const images = document.querySelectorAll('img.service-image');
            images.forEach((img, index) => {
                const testImg = new Image();
                testImg.onload = function () {
                    console.log(`Image ${index + 1} loaded: ${img.src}`);
                };
                testImg.onerror = function () {
                    console.log(`Image ${index + 1} failed: ${img.src}`);
                };
                testImg.src = img.src;
            });
        }

        // Run test on load
        testImageUrls();
    </script>
</body>

</html>
<?php $conn->close(); ?>