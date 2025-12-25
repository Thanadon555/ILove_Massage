<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/db_helper.php';
require_once 'includes/validation.php';
require_once 'includes/error_logger.php';

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';
$db = new DatabaseHelper($conn);
$validator = new Validator();

// ตัวกรอง
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';

// ดึงข้อมูลวันหยุดทั้งหมด
$holidays = [];
$where_conditions = [];

if ($filter_status !== '') {
    if ($filter_status === 'closed') {
        $where_conditions[] = "is_closed = 1";
    } elseif ($filter_status === 'open') {
        $where_conditions[] = "is_closed = 0";
    }
}

if ($filter_search) {
    $search_term = $conn->real_escape_string($filter_search);
    $where_conditions[] = "description LIKE '%{$search_term}%'";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
$sql = "SELECT * FROM holidays $where_sql ORDER BY holiday_date DESC";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $holidays[] = $row;
    }
}

// เพิ่มวันหยุดใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_holiday'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token. กรุณา refresh หน้าและลองใหม่');
        }

        $holiday_date = trim($_POST['holiday_date']);
        $description = trim($_POST['description']);
        $is_closed = isset($_POST['is_closed']) ? 1 : 0;

        // Validate holiday_date
        if (!$validator->required('holiday_date', $holiday_date, 'วันที่หยุด')) {
            throw new Exception($validator->getError('holiday_date'));
        }

        // Validate date format and range
        $date = DateTime::createFromFormat('Y-m-d', $holiday_date);
        if (!$date || $date->format('Y-m-d') !== $holiday_date) {
            throw new Exception('รูปแบบวันที่ไม่ถูกต้อง');
        }

        // Check if date is not in the past
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        if ($date < $today) {
            throw new Exception('ไม่สามารถเพิ่มวันหยุดในอดีตได้');
        }

        // Validate description
        if (!$validator->required('description', $description, 'รายละเอียดวันหยุด')) {
            throw new Exception($validator->getError('description'));
        }

        if (strlen($description) > 255) {
            throw new Exception('รายละเอียดวันหยุดต้องไม่เกิน 255 ตัวอักษร');
        }

        // ตรวจสอบว่าวันหยุดซ้ำ
        $check_sql = "SELECT holiday_id FROM holidays WHERE holiday_date = ?";
        $existing = $db->fetchOne($check_sql, [$holiday_date], 's');

        if ($existing) {
            throw new Exception('มีวันหยุดนี้ในระบบแล้ว');
        }

        // Insert new holiday
        $insert_data = [
            'holiday_date' => $holiday_date,
            'description' => $description,
            'is_closed' => $is_closed
        ];

        $db->insert('holidays', $insert_data);
        $success = 'เพิ่มวันหยุดสำเร็จ';

        // โหลดข้อมูลใหม่
        $result = $conn->query($sql);
        $holidays = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $holidays[] = $row;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Add holiday error: ' . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'],
            'holiday_date' => $holiday_date ?? 'N/A'
        ]);
    }
}

// ลบวันหยุด
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_holiday'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token. กรุณา refresh หน้าและลองใหม่');
        }

        $holiday_id = intval($_POST['holiday_id']);

        // Validate holiday_id
        if ($holiday_id <= 0) {
            throw new Exception('รหัสวันหยุดไม่ถูกต้อง');
        }

        // Check if holiday exists
        $check_sql = "SELECT holiday_id FROM holidays WHERE holiday_id = ?";
        $existing = $db->fetchOne($check_sql, [$holiday_id], 'i');

        if (!$existing) {
            throw new Exception('ไม่พบวันหยุดที่ต้องการลบ');
        }

        // Delete holiday
        $db->delete('holidays', 'holiday_id = ?', [$holiday_id], 'i');
        $success = 'ลบวันหยุดสำเร็จ';

        // โหลดข้อมูลใหม่
        $result = $conn->query($sql);
        $holidays = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $holidays[] = $row;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError('Delete holiday error: ' . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'],
            'holiday_id' => $holiday_id ?? 'N/A'
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการวันหยุด - Admin Panel</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <!-- Navigation -->
    <?php include '../templates/navbar-admin.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-calendar-x"></i> จัดการวันหยุด
            </h1>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>ข้อผิดพลาด!</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>สำเร็จ!</strong> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองข้อมูล</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">สถานะ</label>
                        <select name="status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="closed" <?= $filter_status == 'closed' ? 'selected' : '' ?>>ปิดร้าน</option>
                            <option value="open" <?= $filter_status == 'open' ? 'selected' : '' ?>>เปิดร้าน</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="ค้นหาคำอธิบายวันหยุด"
                            value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2">
                            <i class="bi bi-search me-1"></i>กรอง
                        </button>
                        <a href="manage_holidays.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>ล้าง
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Holiday Form Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>เพิ่มวันหยุดใหม่</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addHolidayForm" class="needs-validation" novalidate>
                    <input type="hidden" name="add_holiday" value="1">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">วันที่หยุด <span class="text-danger">*</span></label>
                            <input type="date" name="holiday_date" class="form-control"
                                min="<?= date('Y-m-d') ?>" required>
                            <div class="invalid-feedback">
                                กรุณาเลือกวันที่หยุด
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">รายละเอียดวันหยุด <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control"
                                placeholder="เช่น วันขึ้นปีใหม่, วันหยุดชดเชย..." maxlength="255" required>
                            <div class="invalid-feedback">
                                กรุณากรอกรายละเอียดวันหยุด (ไม่เกิน 255 ตัวอักษร)
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">สถานะ</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_closed" value="1"
                                    id="is_closed" checked>
                                <label class="form-check-label" for="is_closed">
                                    ปิดทำการ
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>เพิ่มวันหยุด
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Holidays Table Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>วันหยุดทั้งหมด</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>วันที่</th>
                                <th>รายละเอียด</th>
                                <th>สถานะ</th>
                                <th>เพิ่มเมื่อ</th>
                                <th class="text-center">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($holidays)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                        ไม่มีวันหยุด
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($holidays as $holiday): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('d/m/Y', strtotime($holiday['holiday_date'])) ?></strong><br>
                                            <small class="text-muted"><?= date('l', strtotime($holiday['holiday_date'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($holiday['description']) ?></td>
                                        <td>
                                            <?php if ($holiday['is_closed']): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-x-circle me-1"></i>ปิดทำการ
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-clock me-1"></i>เปิดทำการ
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y H:i', strtotime($holiday['created_at'])) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="delete_holiday" value="1">
                                                <input type="hidden" name="holiday_id"
                                                    value="<?= $holiday['holiday_id'] ?>">
                                                <?= csrfField() ?>
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('คุณแน่ใจว่าต้องการลบวันหยุดนี้?')">
                                                    <i class="bi bi-trash me-1"></i>ลบ
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Holidays Card -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>วันหยุดที่จะมาถึง</h5>
            </div>
            <div class="card-body">
                <?php
                $upcoming_sql = "SELECT * FROM holidays 
                                WHERE holiday_date >= CURDATE() 
                                ORDER BY holiday_date ASC 
                                LIMIT 5";
                $upcoming_result = $conn->query($upcoming_sql);
                ?>

                <?php if ($upcoming_result->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($holiday = $upcoming_result->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-calendar3 me-2 text-primary"></i>
                                            <?= htmlspecialchars($holiday['description']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($holiday['holiday_date'])) ?>
                                            (<?= date('l', strtotime($holiday['holiday_date'])) ?>)
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($holiday['is_closed']): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>ปิดทำการ
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock me-1"></i>เปิดทำการ
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                        <p class="mb-0">ไม่มีวันหยุดที่จะมาถึง</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../templates/footer-admin.php'; ?>

    <?php include '../templates/admin-scripts.php'; ?>
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            const form = document.getElementById('addHolidayForm');
            
            if (form) {
                form.addEventListener('submit', function(event) {
                    // Additional custom validation
                    const holidayDate = document.querySelector('input[name="holiday_date"]');
                    const description = document.querySelector('input[name="description"]');
                    
                    let customError = false;
                    
                    // Check if date is not in the past
                    if (holidayDate.value) {
                        const selectedDate = new Date(holidayDate.value);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        
                        if (selectedDate < today) {
                            holidayDate.setCustomValidity('ไม่สามารถเพิ่มวันหยุดในอดีตได้');
                            customError = true;
                        } else {
                            holidayDate.setCustomValidity('');
                        }
                    }
                    
                    // Check description length
                    if (description.value && description.value.length > 255) {
                        description.setCustomValidity('รายละเอียดวันหยุดต้องไม่เกิน 255 ตัวอักษร');
                        customError = true;
                    } else {
                        description.setCustomValidity('');
                    }
                    
                    if (!form.checkValidity() || customError) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            }
        })();

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>
<?php $conn->close(); ?>