<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/validation.php';
require_once 'includes/db_helper.php';
require_once 'includes/error_logger.php';

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';
$validator = new Validator();
$db = new DatabaseHelper($conn);

// ดึงข้อมูลหมอนวดทั้งหมด
$therapists = [];
try {
    $therapists = $db->fetchAll("SELECT * FROM therapists ORDER BY full_name");
} catch (Exception $e) {
    logError('Failed to fetch therapists: ' . $e->getMessage());
    $error = 'ไม่สามารถโหลดข้อมูลหมอนวดได้';
}

// ดึงข้อมูลเวลาทำงาน
$working_hours = [];
if (isset($_GET['therapist_id'])) {
    $therapist_id = filter_var($_GET['therapist_id'], FILTER_VALIDATE_INT);
    
    if ($therapist_id) {
        try {
            $schedule_sql = "SELECT * FROM working_hours WHERE therapist_id = ? ORDER BY 
                             FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
            $working_hours = $db->fetchAll($schedule_sql, [$therapist_id], 'i');
        } catch (Exception $e) {
            logError('Failed to fetch working hours: ' . $e->getMessage(), ['therapist_id' => $therapist_id]);
            $error = 'ไม่สามารถโหลดข้อมูลตารางเวลาได้';
        }
    }
}

// เพิ่มหรืออัพเดทเวลาทำงาน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            logError('CSRF token validation failed', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            throw new Exception('CSRF token validation failed');
        }

        // Validate therapist_id
        $therapist_id = filter_var($_POST['therapist_id'], FILTER_VALIDATE_INT);
        if (!$therapist_id) {
            $validator->addError('therapist_id', 'รหัสหมอนวดไม่ถูกต้อง');
            logError('Invalid therapist_id format', [
                'therapist_id' => $_POST['therapist_id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
        }

        // Validate that therapist exists
        if ($therapist_id) {
            try {
                $therapist_check = $db->fetchOne("SELECT therapist_id FROM therapists WHERE therapist_id = ?", [$therapist_id], 'i');
                if (!$therapist_check) {
                    $validator->addError('therapist_id', 'ไม่พบข้อมูลหมอนวดนี้');
                    logError('Therapist not found', [
                        'therapist_id' => $therapist_id,
                        'user_id' => $_SESSION['user_id']
                    ]);
                }
            } catch (Exception $e) {
                logError('Database error checking therapist: ' . $e->getMessage(), [
                    'therapist_id' => $therapist_id,
                    'user_id' => $_SESSION['user_id']
                ]);
                throw new Exception('ไม่สามารถตรวจสอบข้อมูลหมอนวดได้');
            }
        }

        $days = $_POST['day_of_week'] ?? [];
        $start_times = $_POST['start_time'] ?? [];
        $end_times = $_POST['end_time'] ?? [];

        // Validate that arrays have data
        if (empty($days)) {
            $validator->addError('schedule', 'ไม่พบข้อมูลตารางเวลา');
            logError('Empty schedule data', [
                'therapist_id' => $therapist_id,
                'user_id' => $_SESSION['user_id']
            ]);
        }

        // Validate schedule data
        $valid_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $schedules_to_insert = [];
        $day_names = [
            'Monday' => 'จันทร์',
            'Tuesday' => 'อังคาร',
            'Wednesday' => 'พุธ',
            'Thursday' => 'พฤหัสบดี',
            'Friday' => 'ศุกร์',
            'Saturday' => 'เสาร์',
            'Sunday' => 'อาทิตย์'
        ];

        foreach ($days as $index => $day) {
            $start_time = isset($start_times[$index]) ? trim($start_times[$index]) : '';
            $end_time = isset($end_times[$index]) ? trim($end_times[$index]) : '';

            // Skip if no times selected (day off) - ข้ามวันที่ไม่ได้กรอกเวลาทั้งสองช่อง
            if ($start_time === '' && $end_time === '') {
                continue;
            }

            // Validate day
            if (!in_array($day, $valid_days)) {
                $validator->addError("day_$index", "วันที่ $day ไม่ถูกต้อง");
                logError('Invalid day of week', [
                    'day' => $day,
                    'index' => $index,
                    'therapist_id' => $therapist_id
                ]);
                continue;
            }

            // Validate both times are provided - ถ้ากรอกเวลาใดเวลาหนึ่ง ต้องกรอกทั้งคู่
            if (empty($start_time) || empty($end_time)) {
                $day_thai = $day_names[$day] ?? $day;
                $validator->addError("time_$index", "กรุณาระบุเวลาเริ่มและเวลาสิ้นสุดให้ครบถ้วนสำหรับวัน $day_thai (หรือเว้นว่างทั้งสองช่องถ้าไม่ต้องการเพิ่มวันนี้)");
                logError('Incomplete time data', [
                    'day' => $day,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'therapist_id' => $therapist_id
                ]);
                continue;
            }

            // Validate time format
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
                $day_thai = $day_names[$day] ?? $day;
                $validator->addError("time_$index", "รูปแบบเวลาไม่ถูกต้องสำหรับวัน $day_thai");
                logError('Invalid time format', [
                    'day' => $day,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'therapist_id' => $therapist_id
                ]);
                continue;
            }

            // Validate time range - เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม (ไม่รองรับข้ามวัน)
            if (strtotime($end_time) <= strtotime($start_time)) {
                $day_thai = $day_names[$day] ?? $day;
                $validator->addError("time_$index", "เวลาเลิกงานต้องมากกว่าเวลาเริ่มงานสำหรับวัน $day_thai");
                logError('Invalid time range', [
                    'day' => $day,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'therapist_id' => $therapist_id
                ]);
                continue;
            }

            // Check for overlapping schedules (same day)
            foreach ($schedules_to_insert as $existing) {
                if ($existing['day'] === $day) {
                    $day_thai = $day_names[$day] ?? $day;
                    $validator->addError("time_$index", "มีตารางเวลาซ้ำสำหรับวัน $day_thai");
                    logError('Duplicate schedule for day', [
                        'day' => $day,
                        'therapist_id' => $therapist_id
                    ]);
                    break;
                }
            }

            $schedules_to_insert[] = [
                'day' => $day,
                'start_time' => $start_time,
                'end_time' => $end_time
            ];
        }

        // If validation passed, update database
        if (!$validator->hasErrors()) {
            try {
                // Start transaction
                $conn->begin_transaction();

                try {
                    // Delete existing schedules
                    $db->delete('working_hours', 'therapist_id = ?', [$therapist_id], 'i');

                    // Insert new schedules
                    foreach ($schedules_to_insert as $schedule) {
                        $data = [
                            'therapist_id' => $therapist_id,
                            'day_of_week' => $schedule['day'],
                            'start_time' => $schedule['start_time'],
                            'end_time' => $schedule['end_time']
                        ];
                        $db->insert('working_hours', $data);
                    }

                    // Commit transaction
                    $conn->commit();

                    $success = 'อัพเดทตารางเวลาทำงานสำเร็จ';
                    
                    // Log success
                    logError('Schedule updated successfully', [
                        'therapist_id' => $therapist_id,
                        'schedule_count' => count($schedules_to_insert),
                        'user_id' => $_SESSION['user_id']
                    ]);

                    // Reload working hours
                    try {
                        $schedule_sql = "SELECT * FROM working_hours WHERE therapist_id = ? ORDER BY 
                                         FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
                        $working_hours = $db->fetchAll($schedule_sql, [$therapist_id], 'i');
                    } catch (Exception $e) {
                        logError('Failed to reload working hours after update: ' . $e->getMessage(), [
                            'therapist_id' => $therapist_id,
                            'user_id' => $_SESSION['user_id']
                        ]);
                        // Don't throw - update was successful, just reload failed
                    }

                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    logError('Database transaction failed: ' . $e->getMessage(), [
                        'therapist_id' => $therapist_id,
                        'schedule_count' => count($schedules_to_insert),
                        'user_id' => $_SESSION['user_id']
                    ]);
                    throw new Exception('ไม่สามารถบันทึกตารางเวลาได้ กรุณาลองใหม่อีกครั้ง');
                }
            } catch (Exception $e) {
                logError('Transaction error: ' . $e->getMessage(), [
                    'therapist_id' => $therapist_id,
                    'user_id' => $_SESSION['user_id']
                ]);
                throw $e;
            }
        } else {
            $error_list = $validator->getErrors();
            $error = 'กรุณาตรวจสอบข้อมูลที่กรอก: ' . implode(', ', $error_list);
            logError('Schedule validation failed', [
                'therapist_id' => $therapist_id,
                'errors' => $error_list,
                'user_id' => $_SESSION['user_id']
            ]);
        }

    } catch (Exception $e) {
        // Catch any unexpected errors
        $error_message = $e->getMessage();
        
        // Log the error with full context
        logError('Schedule update failed: ' . $error_message, [
            'therapist_id' => $_POST['therapist_id'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
            'post_data' => [
                'days_count' => count($_POST['day_of_week'] ?? []),
                'has_csrf' => isset($_POST['csrf_token'])
            ],
            'trace' => $e->getTraceAsString()
        ]);
        
        // Show user-friendly error message
        if (strpos($error_message, 'CSRF') !== false) {
            $error = 'เซสชันหมดอายุ กรุณารีเฟรชหน้าและลองใหม่อีกครั้ง';
        } elseif (strpos($error_message, 'ไม่สามารถ') !== false) {
            // Already a user-friendly message
            $error = $error_message;
        } else {
            $error = 'เกิดข้อผิดพลาดในการอัพเดทตารางเวลา กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบ';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการตารางเวลาทำงาน</title>
    <?php include '../templates/admin-head.php'; ?>
</head>
<body>
    <?php include '../templates/navbar-admin.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-calendar-week"></i> จัดการตารางเวลาทำงาน</h1>
                <p class="text-muted mb-0">ระบบจัดการตารางเวลาทำงานของพนักงานนวด</p>
            </div>
            <!-- <div>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i> พิมพ์ตาราง
                </button>
            </div> -->
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- เลือกหมอนวด -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> เลือกหมอนวด</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">เลือกหมอนวด</label>
                        <select name="therapist_id" class="form-select" onchange="this.form.submit()" required>
                            <option value="">-- เลือกหมอนวด --</option>
                            <?php foreach ($therapists as $therapist): ?>
                                <option value="<?= $therapist['therapist_id'] ?>"
                                    <?= isset($_GET['therapist_id']) && $_GET['therapist_id'] == $therapist['therapist_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($therapist['full_name']) ?>
                                    <?= !$therapist['is_available'] ? ' (ไม่ว่าง)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-eye"></i> แสดงตารางเวลา
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['therapist_id']) && !empty($therapists)):
            $selected_therapist = null;
            foreach ($therapists as $t) {
                if ($t['therapist_id'] == $_GET['therapist_id']) {
                    $selected_therapist = $t;
                    break;
                }
            }
            
            // ตรวจสอบว่าพบนักบำบัดหรือไม่
            if (!$selected_therapist):
        ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    ไม่พบข้อมูลนักบำบัดที่เลือก กรุณาเลือกนักบำบัดใหม่
                </div>
        <?php else: ?>
                <!-- ตารางสรุปวันทำงานปัจจุบัน -->
                <?php if (!empty($existing_schedules)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-week me-2"></i>ตารางวันทำงานปัจจุบันของ <?= htmlspecialchars($selected_therapist['full_name']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="20%"><i class="bi bi-calendar3 me-1"></i>วัน</th>
                                        <th width="25%"><i class="bi bi-clock me-1"></i>เวลาเริ่มงาน</th>
                                        <th width="25%"><i class="bi bi-clock-fill me-1"></i>เวลาเลิกงาน</th>
                                        <th width="30%"><i class="bi bi-info-circle me-1"></i>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $day_names_display = [
                                        'Monday' => 'จันทร์',
                                        'Tuesday' => 'อังคาร',
                                        'Wednesday' => 'พุธ',
                                        'Thursday' => 'พฤหัสบดี',
                                        'Friday' => 'ศุกร์',
                                        'Saturday' => 'เสาร์',
                                        'Sunday' => 'อาทิตย์'
                                    ];
                                    $all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    
                                    foreach ($all_days as $day):
                                        $schedule = null;
                                        foreach ($existing_schedules as $sched) {
                                            if ($sched['day'] === $day) {
                                                $schedule = $sched;
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr class="<?= $schedule ? 'table-success' : 'table-light' ?>">
                                        <td class="fw-bold">
                                            <div class="d-flex align-items-center">
                                                <div class="badge <?= $schedule ? 'bg-success' : 'bg-secondary' ?> rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <?= substr($day_names_display[$day], 0, 1) ?>
                                                </div>
                                                <?= $day_names_display[$day] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($schedule): ?>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($schedule['start_time'])) ?> น.
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($schedule): ?>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-clock-fill me-1"></i><?= date('H:i', strtotime($schedule['end_time'])) ?> น.
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($schedule): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>ทำงาน
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-x-circle me-1"></i>หยุด
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mb-0 mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>หมายเหตุ:</strong> ใช้ฟอร์มด้านล่างเพื่อแก้ไขตารางเวลาทำงาน
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <!-- แสดงตารางเวลาทำงาน -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-week"></i> ตารางเวลาทำงาน: <?= htmlspecialchars($selected_therapist['full_name']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>คำแนะนำ:</strong> สามารถเลือกวันทำงานได้อย่างอิสระ ไม่จำเป็นต้องเรียงติดกัน เช่น เลือกเฉพาะวันจันทร์ พุธ และศุกร์ได้
                    </div>
                    
                    <form method="POST" id="scheduleForm">
                        <input type="hidden" name="update_schedule" value="1">
                        <input type="hidden" name="therapist_id" value="<?= $_GET['therapist_id'] ?>">
                        <?= csrfField() ?>

                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="w-20">วัน</th>
                                        <th class="w-30">เวลาเริ่มงาน</th>
                                        <th class="w-30">เวลาเลิกงาน</th>
                                        <th class="w-20 text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $day_names = [
                                        'Monday' => 'จันทร์',
                                        'Tuesday' => 'อังคาร',
                                        'Wednesday' => 'พุธ',
                                        'Thursday' => 'พฤหัสบดี',
                                        'Friday' => 'ศุกร์',
                                        'Saturday' => 'เสาร์',
                                        'Sunday' => 'อาทิตย์'
                                    ];

                                    foreach ($days_of_week as $day):
                                        $existing_schedule = null;
                                        foreach ($working_hours as $schedule) {
                                            if ($schedule['day_of_week'] == $day) {
                                                $existing_schedule = $schedule;
                                                break;
                                            }
                                        }
                                        $is_active = $existing_schedule ? 'table-success' : '';
                                    ?>
                                        <tr class="<?= $is_active ?>">
                                            <td class="fw-semibold">
                                                <div class="d-flex align-items-center">
                                                    <div class="badge bg-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                        <?= substr($day_names[$day], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <?= $day_names[$day] ?>
                                                        <input type="hidden" name="day_of_week[]" value="<?= $day ?>">
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="start_time[]" class="form-select" data-day="<?= $day ?>">
                                                    <option value="">-- ไม่ทำงาน --</option>
                                                    <?php for ($hour = 9; $hour <= 21; $hour++): ?>
                                                        <?php
                                                        $time_value = sprintf('%02d:00:00', $hour);
                                                        $time_display = sprintf('%02d:00', $hour);
                                                        ?>
                                                        <option value="<?= $time_value ?>"
                                                            <?= $existing_schedule && $existing_schedule['start_time'] == $time_value ? 'selected' : '' ?>>
                                                            <?= $time_display ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="end_time[]" class="form-select" data-day="<?= $day ?>">
                                                    <option value="">-- ไม่ทำงาน --</option>
                                                    <?php for ($hour = 9; $hour <= 21; $hour++): ?>
                                                        <?php
                                                        $time_value = sprintf('%02d:00:00', $hour);
                                                        $time_display = sprintf('%02d:00', $hour);
                                                        ?>
                                                        <option value="<?= $time_value ?>"
                                                            <?= $existing_schedule && $existing_schedule['end_time'] == $time_value ? 'selected' : '' ?>>
                                                            <?= $time_display ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <span class="status-badge">
                                                    <?php if ($existing_schedule): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>ทำงาน
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-x-circle me-1"></i>หยุด
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg me-2">
                                <i class="bi bi-save"></i> บันทึกตารางเวลา
                            </button>
                            <a href="manage_schedule.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-circle"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- สรุปตารางเวลา -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> สรุปตารางเวลาทำงาน</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">วันทำงานในสัปดาห์:</h6>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php
                                $working_days = 0;
                                foreach ($days_of_week as $day) {
                                    $has_schedule = false;
                                    foreach ($working_hours as $schedule) {
                                        if ($schedule['day_of_week'] == $day) {
                                            $has_schedule = true;
                                            $working_days++;
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge <?= $has_schedule ? 'bg-success' : 'bg-secondary' ?> fs-6">
                                        <?= $day_names[$day] ?>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">สถิติ:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="bi bi-calendar-day text-primary me-2"></i>วันทำงาน: <span class="badge bg-primary"><?= $working_days ?> วัน</span></li>
                                <li class="mb-2"><i class="bi bi-calendar-x text-secondary me-2"></i>วันหยุด: <span class="badge bg-secondary"><?= 7 - $working_days ?> วัน</span></li>
                                <li class="mb-2"><i class="bi bi-person-check text-success me-2"></i>สถานะ: <span class="badge <?= isset($selected_therapist['is_available']) && $selected_therapist['is_available'] ? 'bg-success' : 'bg-danger' ?>"><?= isset($selected_therapist['is_available']) && $selected_therapist['is_available'] ? 'พร้อมทำงาน' : 'ไม่ว่าง' ?></span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php 
            endif; // end if $selected_therapist
        else: ?>
            <!-- Empty State -->
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-calendar-x text-muted display-1"></i>
                    <h5 class="mt-3">ยังไม่ได้เลือกหมอนวด</h5>
                    <p class="text-muted">กรุณาเลือกหมอนวดเพื่อดูและจัดการตารางเวลาทำงาน</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
    <script>
        // Schedule time validation and management
        document.addEventListener('DOMContentLoaded', function() {
            const startSelects = document.querySelectorAll('select[name="start_time[]"]');
            const endSelects = document.querySelectorAll('select[name="end_time[]"]');
            const scheduleForm = document.getElementById('scheduleForm');
            
            // อัพเดทสถานะเมื่อเปลี่ยนเวลา
            startSelects.forEach((startSelect, index) => {
                const endSelect = endSelects[index];
                const statusBadge = startSelect.closest('tr').querySelector('.status-badge');
                
                function updateStatus() {
                    const startValue = startSelect.value;
                    const endValue = endSelect.value;
                    
                    if (startValue && endValue) {
                        statusBadge.innerHTML = `
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle me-1"></i>ทำงาน
                            </span>
                        `;
                        startSelect.closest('tr').classList.add('table-success');
                    } else if (!startValue && !endValue) {
                        statusBadge.innerHTML = `
                            <span class="badge bg-secondary">
                                <i class="bi bi-x-circle me-1"></i>หยุด
                            </span>
                        `;
                        startSelect.closest('tr').classList.remove('table-success');
                    }
                }
                
                startSelect.addEventListener('change', function() {
                    const startValue = this.value;
                    
                    if (startValue) {
                        endSelect.disabled = false;
                        
                        // กรองเวลาใน end select ให้มากกว่า start time
                        const startHour = parseInt(startValue.split(':')[0]);
                        Array.from(endSelect.options).forEach(option => {
                            if (option.value) {
                                const endHour = parseInt(option.value.split(':')[0]);
                                option.disabled = endHour <= startHour;
                            }
                        });
                        
                        // Reset end time if it's now invalid
                        if (endSelect.value) {
                            const endHour = parseInt(endSelect.value.split(':')[0]);
                            if (endHour <= startHour) {
                                endSelect.value = '';
                            }
                        }
                    } else {
                        endSelect.value = '';
                        endSelect.disabled = false;
                        
                        // Enable all end time options
                        Array.from(endSelect.options).forEach(option => {
                            option.disabled = false;
                        });
                    }
                    updateStatus();
                });
                
                endSelect.addEventListener('change', function() {
                    if (this.value && !startSelect.value) {
                        startSelect.focus();
                    }
                    updateStatus();
                });
                
                // เรียกใช้ event ครั้งแรกเพื่อตั้งค่าเริ่มต้น
                if (startSelect.value) {
                    startSelect.dispatchEvent(new Event('change'));
                }
            });
            
            // Form validation before submit
            if (scheduleForm) {
                scheduleForm.addEventListener('submit', function(e) {
                    let hasError = false;
                    let errorMessages = [];
                    let hasAtLeastOneSchedule = false;
                    
                    // Clear previous errors
                    document.querySelectorAll('.is-invalid').forEach(el => {
                        el.classList.remove('is-invalid');
                    });
                    document.querySelectorAll('.invalid-feedback').forEach(el => {
                        el.remove();
                    });
                    
                    // Validate each day
                    startSelects.forEach((startSelect, index) => {
                        const endSelect = endSelects[index];
                        const startValue = startSelect.value;
                        const endValue = endSelect.value;
                        const dayName = startSelect.closest('tr').querySelector('td').textContent.trim();
                        
                        // ตรวจสอบว่ามีการกรอกเวลาอย่างน้อย 1 วัน
                        if (startValue && endValue) {
                            hasAtLeastOneSchedule = true;
                        }
                        
                        // If start time is selected, end time must also be selected
                        if (startValue && !endValue) {
                            hasError = true;
                            endSelect.classList.add('is-invalid');
                            const feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            feedback.textContent = 'กรุณาเลือกเวลาเลิกงาน';
                            endSelect.parentNode.appendChild(feedback);
                            errorMessages.push(`${dayName}: กรุณาเลือกเวลาเลิกงาน`);
                        }
                        
                        // If end time is selected, start time must also be selected
                        if (endValue && !startValue) {
                            hasError = true;
                            startSelect.classList.add('is-invalid');
                            const feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            feedback.textContent = 'กรุณาเลือกเวลาเริ่มงาน';
                            startSelect.parentNode.appendChild(feedback);
                            errorMessages.push(`${dayName}: กรุณาเลือกเวลาเริ่มงาน`);
                        }
                        
                        // Validate time range - เวลาเลิกงานต้องมากกว่าเวลาเริ่มงาน
                        if (startValue && endValue) {
                            const startHour = parseInt(startValue.split(':')[0]);
                            const endHour = parseInt(endValue.split(':')[0]);
                            
                            if (endHour <= startHour) {
                                hasError = true;
                                endSelect.classList.add('is-invalid');
                                const feedback = document.createElement('div');
                                feedback.className = 'invalid-feedback';
                                feedback.textContent = 'เวลาเลิกงานต้องมากกว่าเวลาเริ่มงาน';
                                endSelect.parentNode.appendChild(feedback);
                                errorMessages.push(`${dayName}: เวลาเลิกงานต้องมากกว่าเวลาเริ่มงาน`);
                            }
                        }
                    });
                    
                    // ตรวจสอบว่าต้องมีอย่างน้อย 1 วันที่มีเวลาทำงาน
                    if (!hasAtLeastOneSchedule && !hasError) {
                        hasError = true;
                        errorMessages.push('กรุณากำหนดเวลาทำงานอย่างน้อย 1 วัน');
                    }
                    
                    if (hasError) {
                        e.preventDefault();
                        
                        // Show error alert
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>กรุณาแก้ไขข้อผิดพลาด:</strong>
                            <ul class="mb-0 mt-2">
                                ${errorMessages.map(msg => `<li>${msg}</li>`).join('')}
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        
                        const container = document.querySelector('.container-fluid');
                        if (container) {
                            const firstChild = container.firstElementChild;
                            container.insertBefore(alertDiv, firstChild.nextSibling);
                            
                            // Scroll to top
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                        
                        return false;
                    }
                    
                    // Confirm before submit
                    if (!confirm('คุณต้องการบันทึกตารางเวลาทำงานนี้หรือไม่?')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>