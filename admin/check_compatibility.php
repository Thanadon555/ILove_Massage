<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';
require_once 'includes/php_compatibility_checker.php';

// ตรวจสอบสิทธิ์การเข้าถึง - Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$scanResults = null;
$scanCompleted = false;
$errorMessage = null;

// Handle scan request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_scan'])) {
    try {
        $checker = new PHPCompatibilityChecker();
        $rootPath = dirname(__DIR__); // Get project root directory
        
        // Run the scan
        $scanResults = $checker->scanProject($rootPath);
        $scanCompleted = true;
        
        // Log the scan
        $logMessage = sprintf(
            "[%s] PHP Compatibility scan completed by user %s. Files scanned: %d, Incompatible: %d\n",
            date('Y-m-d H:i:s'),
            $_SESSION['full_name'] ?? 'Unknown',
            $checker->getStatistics()['scanned_files'],
            $checker->getStatistics()['incompatible_files']
        );
        error_log($logMessage, 3, '../logs/admin_errors.log');
        
    } catch (Exception $e) {
        $errorMessage = 'เกิดข้อผิดพลาดในการสแกน: ' . $e->getMessage();
        error_log("[" . date('Y-m-d H:i:s') . "] Compatibility scan error: " . $e->getMessage() . "\n", 3, '../logs/admin_errors.log');
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบความเข้ากันได้ PHP 7.1 - ระบบจัดการสปา</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-shield-check me-2"></i>ตรวจสอบความเข้ากันได้ PHP 7.1
            </h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>กลับ
            </a>
        </div>

        <!-- Information Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle me-2"></i>เกี่ยวกับเครื่องมือนี้
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    เครื่องมือนี้จะสแกนไฟล์ PHP ทั้งหมดในระบบเพื่อตรวจหา syntax และ features ที่ไม่รองรับ PHP 7.1
                </p>
                <p class="mb-2"><strong>ตรวจสอบ:</strong></p>
                <ul class="mb-0">
                    <li>Typed Properties (PHP 7.4+)</li>
                    <li>Arrow Functions (PHP 7.4+)</li>
                    <li>Null Coalescing Assignment (PHP 7.4+)</li>
                    <li>Nullsafe Operator (PHP 8.0+)</li>
                    <li>Match Expressions (PHP 8.0+)</li>
                    <li>Named Arguments (PHP 8.0+)</li>
                    <li>Union Types (PHP 8.0+)</li>
                </ul>
            </div>
        </div>

        <!-- Scan Form -->
        <?php if (!$scanCompleted): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-play-circle me-2"></i>เริ่มการสแกน
                </h5>
            </div>
            <div class="card-body">
                <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                </div>
                <?php endif; ?>
                
                <p class="mb-3">
                    คลิกปุ่มด้านล่างเพื่อเริ่มสแกนไฟล์ PHP ทั้งหมดในระบบ 
                    (การสแกนอาจใช้เวลาสักครู่ขึ้นอยู่กับขนาดของโปรเจค)
                </p>
                
                <form method="POST" id="scanForm">
                    <button type="submit" name="run_scan" class="btn btn-primary btn-lg" id="scanButton">
                        <i class="bi bi-search me-2"></i>เริ่มสแกนระบบ
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Scan Results -->
        <?php if ($scanCompleted && $scanResults !== null): ?>
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-check-circle me-2"></i>ผลการสแกน
                </h5>
            </div>
            <div class="card-body">
                <?php
                $checker = new PHPCompatibilityChecker();
                echo $checker->generateReport($scanResults, 'html');
                ?>
                
                <div class="mt-4">
                    <form method="GET">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise me-2"></i>สแกนใหม่
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
    
    <script>
        // Show loading state when scanning
        document.getElementById('scanForm')?.addEventListener('submit', function() {
            const button = document.getElementById('scanButton');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังสแกน...';
        });
    </script>
    
    <style>
        .compatibility-report h3 {
            color: #4361ee;
            margin-bottom: 1.5rem;
        }
        
        .report-summary {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .report-summary p {
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .compatibility-report .table {
            font-size: 0.9rem;
        }
        
        .compatibility-report .table code {
            background-color: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
        }
        
        .compatibility-report .badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</body>

</html>
<?php $conn->close(); ?>
