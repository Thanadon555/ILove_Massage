<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

// ตั้งค่า path สำหรับรูปภาพหมอนวด - ลองหลายวิธี
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

$profile_image_path = 'uploads/profile/';


// ตั้งค่าตัวแปร
$search = '';
$service_filter = '';
$therapists = [];

// ดึงข้อมูลบริการทั้งหมด (สำหรับฟิลเตอร์)
$services_sql = "SELECT * FROM massage_types WHERE is_active = TRUE ORDER BY name";
$services_result = $conn->query($services_sql);
$services = [];
if ($services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }
}

// ดึงข้อมูลหมอนวด
$sql = "SELECT t.*, 
               GROUP_CONCAT(DISTINCT mt.name) as specializations,
               GROUP_CONCAT(DISTINCT mt.massage_type_id) as service_ids,
               AVG(r.rating) as avg_rating,
               COUNT(r.review_id) as review_count
        FROM therapists t 
        LEFT JOIN therapist_massage_types tmt ON t.therapist_id = tmt.therapist_id 
        LEFT JOIN massage_types mt ON tmt.massage_type_id = mt.massage_type_id 
        LEFT JOIN reviews r ON t.therapist_id = r.therapist_id
        WHERE t.is_available = TRUE";

// ตรวจสอบการค้นหา
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (t.full_name LIKE '%$search%' OR t.specialization LIKE '%$search%')";
}

// ตรวจสอบฟิลเตอร์บริการ
if (isset($_GET['service']) && !empty($_GET['service'])) {
    $service_filter = $conn->real_escape_string($_GET['service']);
    $sql .= " AND mt.massage_type_id = '$service_filter'";
}

$sql .= " GROUP BY t.therapist_id ORDER BY t.full_name";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $therapists[] = $row;
    }
}

// Debug information
echo "<!-- Debug Info:\n";
echo "Base URL: " . $base_url . "\n";
echo "Profile Image Path: " . $profile_image_path . "\n";
foreach ($therapists as $therapist) {
    if (!empty($therapist['image_url'])) {
        $test_path = $profile_image_path . $therapist['image_url'];
        $full_test_path = $_SERVER['DOCUMENT_ROOT'] . '/massage/' . $profile_image_path . $therapist['image_url'];
        echo "Therapist: " . $therapist['full_name'] . "\n";
        echo "Image URL: " . $therapist['image_url'] . "\n";
        echo "Test Path: " . $test_path . "\n";
        echo "Full Path: " . $full_test_path . "\n";
        echo "File Exists: " . (file_exists($full_test_path) ? 'Yes' : 'No') . "\n";
        echo "---\n";
    }
}
echo "-->";
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทีมหมอนวด - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/customer-styles.css">
    <style>
        .therapist-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .therapist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .therapist-image {
            height: 250px;
            object-fit: cover;
            width: 100%;
        }

        .placeholder-image {
            height: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .rating-stars {
            color: #ffc107;
        }

        .specialization-badge {
            background-color: #0d6efd;
            color: white;
            font-size: 0.75rem;
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

        .availability-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }

        .review-count {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
            display: none;
        }
    </style>
</head>

<body>
    <?php include 'templates/navbar-user.php'; ?>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold">ทีมหมอนวดมืออาชีพ</h1>
            <p class="lead">พบกับทีมงานผู้เชี่ยวชาญด้านการนวดบำบัดที่พร้อมให้บริการคุณ</p>
        </div>
    </section>

    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="therapists.php">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="search" class="form-label">ค้นหาหมอนวด</label>
                        <input type="text" class="form-control" id="search" name="search"
                            value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อหมอนวดหรือความเชี่ยวชาญ...">
                    </div>
                    <div class="col-md-4">
                        <label for="service" class="form-label">บริการที่เชี่ยวชาญ</label>
                        <select class="form-select" id="service" name="service">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['massage_type_id'] ?>"
                                    <?= $service_filter == $service['massage_type_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($service['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Therapists Count -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>ทีมหมอนวดทั้งหมด (<?= count($therapists) ?> คน)</h3>
            <?php if ($search || $service_filter): ?>
                <a href="therapists.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>ล้างตัวกรอง
                </a>
            <?php endif; ?>
        </div>

        <!-- Therapists Grid -->
        <?php if (empty($therapists)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">ไม่พบหมอนวดที่คุณค้นหา</h4>
                <p class="text-muted">ลองเปลี่ยนคำค้นหาหรือบริการที่เชี่ยวชาญดูนะคะ</p>
                <a href="therapists.php" class="btn btn-primary">ดูหมอนวดทั้งหมด</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($therapists as $therapist): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card therapist-card h-100 position-relative">
                            <!-- Availability Badge -->
                            <span class="badge availability-badge bg-<?= $therapist['is_available'] ? 'success' : 'danger' ?>">
                                <?= $therapist['is_available'] ? 'พร้อมให้บริการ' : 'ไม่ว่าง' ?>
                            </span>

                            <!-- Therapist Image -->
                            <?php
                            // ลองใช้หลายวิธี
                            $image_path1 = $profile_image_path . $therapist['image_url']; // วิธีที่ 1
                            $image_path2 = '/massage/uploads/profile/' . $therapist['image_url']; // วิธีที่ 2
                            $image_path3 = $base_url . '/massage/uploads/profile/' . $therapist['image_url']; // วิธีที่ 3
                            ?>

                            <?php if (!empty($therapist['image_url'])): ?>
                                <!-- ลองใช้หลาย path -->
                                <img src="<?= htmlspecialchars($image_path1) ?>" class="card-img-top therapist-image"
                                    alt="<?= htmlspecialchars($therapist['full_name']) ?>"
                                    data-path2="<?= htmlspecialchars($image_path2) ?>"
                                    data-path3="<?= htmlspecialchars($image_path3) ?>" onerror="tryNextPath(this)">
                                <div class="placeholder-image" style="display: none;">
                                    <i class="fas fa-user-md fa-4x"></i>
                                </div>
                            <?php else: ?>
                                <div class="placeholder-image">
                                    <i class="fas fa-user-md fa-4x"></i>
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <!-- Therapist Name and Rating -->
                                <h5 class="card-title"><?= htmlspecialchars($therapist['full_name']) ?></h5>

                                <!-- Rating -->
                                <div class="mb-2">
                                    <?php if ($therapist['avg_rating']): ?>
                                        <div class="rating-stars">
                                            <?php
                                            $rating = round($therapist['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++):
                                                if ($i <= $rating):
                                                    echo '<i class="fas fa-star"></i>';
                                                else:
                                                    echo '<i class="far fa-star"></i>';
                                                endif;
                                            endfor;
                                            ?>
                                        </div>
                                        <span class="review-count">
                                            (<?= number_format($therapist['avg_rating'], 1) ?> จาก <?= $therapist['review_count'] ?>
                                            รีวิว)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">ยังไม่มีรีวิว</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Specialization -->
                                <?php if (!empty($therapist['specializations'])): ?>
                                    <div class="mb-3">
                                        <h6 class="fw-bold">บริการที่เชี่ยวชาญ:</h6>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php
                                            $specializations = explode(',', $therapist['specializations']);
                                            foreach ($specializations as $spec):
                                                ?>
                                                <span class="badge specialization-badge"><?= htmlspecialchars(trim($spec)) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Description/Specialization Text -->
                                <?php if (!empty($therapist['specialization'])): ?>
                                    <p class="card-text flex-grow-1">
                                        <?= nl2br(htmlspecialchars($therapist['specialization'])) ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <div class="mt-auto">
                                    <?php if ($therapist['is_available']): ?>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <a href="booking.php?therapist_id=<?= $therapist['therapist_id'] ?>"
                                                class="btn btn-success w-100">
                                                <i class="fas fa-calendar-plus me-2"></i>จองกับหมอนวดคนนี้
                                            </a>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบเพื่อจอง
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-clock me-2"></i>ไม่ว่างในขณะนี้
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Why Choose Us Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body text-center py-5">
                        <h2 class="mb-4">ทำไมต้องเลือกบริการกับเรา?</h2>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-certificate text-primary fs-1 mb-3"></i>
                                    <h5>หมอนวดมีใบรับรอง</h5>
                                    <p class="text-muted">ทีมงานทุกคนผ่านการฝึกอบรมและมีใบรับรองวิชาชีพ</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-heart text-primary fs-1 mb-3"></i>
                                    <h5>ใส่ใจในรายละเอียด</h5>
                                    <p class="text-muted">เราใส่ใจในทุกขั้นตอนการบริการเพื่อความพึงพอใจสูงสุด</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-shield-alt text-primary fs-1 mb-3"></i>
                                    <h5>ปลอดภัยและสะอาด</h5>
                                    <p class="text-muted">มาตรการทำความสะอาดและความปลอดภัยตามมาตรฐานสากล</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        let currentAttempt = 1;

        function tryNextPath(img) {
            console.log('Image failed to load with path:', img.src);

            if (currentAttempt === 1) {
                // ลองใช้ path 2
                img.src = img.getAttribute('data-path2');
                currentAttempt = 2;
                console.log('Trying path 2:', img.src);
            } else if (currentAttempt === 2) {
                // ลองใช้ path 3
                img.src = img.getAttribute('data-path3');
                currentAttempt = 3;
                console.log('Trying path 3:', img.src);
            } else {
                // ทั้งหมดล้มเหลว, แสดง placeholder
                console.log('All paths failed, showing placeholder');
                img.style.display = 'none';
                const placeholder = img.nextElementSibling;
                if (placeholder && placeholder.classList.contains('placeholder-image')) {
                    placeholder.style.display = 'flex';
                }
            }
        }

        function toggleDebug() {
            const debugPanel = document.getElementById('debugPanel');
            debugPanel.style.display = debugPanel.style.display === 'none' ? 'block' : 'none';

            // รวบรวม debug information
            const images = document.querySelectorAll('img.therapist-image');
            let debugHTML = '<strong>Image Debug Info:</strong><br>';

            images.forEach((img, index) => {
                debugHTML += `Image ${index + 1}:<br>`;
                debugHTML += `- Current Src: ${img.src}<br>`;
                debugHTML += `- Path 2: ${img.getAttribute('data-path2')}<br>`;
                debugHTML += `- Path 3: ${img.getAttribute('data-path3')}<br>`;
                debugHTML += `- Complete: ${img.complete}<br>`;
                debugHTML += `- Natural Width: ${img.naturalWidth}<br>`;
                debugHTML += '---<br>';
            });

            document.getElementById('debugContent').innerHTML = debugHTML;
        }

        // Debug function to check image loading
        document.addEventListener('DOMContentLoaded', function () {
            const images = document.querySelectorAll('img.therapist-image');
            images.forEach(img => {
                img.onload = function () {
                    console.log('Image loaded successfully:', this.src);
                };
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>