<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';

// ตรวจสอบการล็อกอินและสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ดึงข้อมูลผู้ดูแลระบบ
$user_sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = $conn->query($user_sql);
$user = $user_result->fetch_assoc();

// อัพเดทข้อมูลผู้ดูแลระบบ
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
    <title>โปรไฟล์ผู้ดูแลระบบ</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><i class="bi bi-person-gear me-2"></i>จัดการโปรไฟล์ผู้ดูแลระบบ</h1>
        </div>

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

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-shield-fill-check text-primary bg-primary bg-opacity-10 p-4 rounded-circle fs-1"></i>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <span class="badge bg-warning text-dark mb-3">
                            <i class="bi bi-star-fill me-1"></i>ผู้ดูแลระบบ
                        </span>
                        <p class="text-muted mb-1"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user['email']) ?></p>
                        <p class="text-muted"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($user['phone']) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="row g-4">

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>ข้อมูลส่วนตัว</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อผู้ใช้</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                            <div class="form-text">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">บทบาท</label>
                                            <input type="text" class="form-control" value="ผู้ดูแลระบบ" readonly>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                            <div class="invalid-feedback">กรุณากรอกชื่อ-นามสกุล</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">อีเมล <span class="text-danger">*</span></label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                            <div class="invalid-feedback">กรุณากรอกอีเมลที่ถูกต้อง</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required pattern="[0-9]{10}">
                                            <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i>อัพเดทข้อมูล
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>เปลี่ยนรหัสผ่าน</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="change_password" value="1">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">รหัสผ่านปัจจุบัน <span class="text-danger">*</span></label>
                                            <input type="password" name="current_password" class="form-control" required>
                                            <div class="invalid-feedback">กรุณากรอกรหัสผ่านปัจจุบัน</div>
                                        </div>

                                        <div class="col-md-6"></div>

                                        <div class="col-md-6">
                                            <label class="form-label">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                                            <div class="form-text">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>
                                            <div class="invalid-feedback">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                            <div class="invalid-feedback">กรุณายืนยันรหัสผ่านใหม่</div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-key me-1"></i>เปลี่ยนรหัสผ่าน
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>ข้อมูลระบบ</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <td class="fw-bold"><i class="bi bi-calendar-plus me-2 text-primary"></i>วันที่สมัคร:</td>
                                                <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold"><i class="bi bi-clock-history me-2 text-success"></i>อัพเดทล่าสุด:</td>
                                                <td><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert alert-info mb-0">
                                            <h6 class="alert-heading"><i class="bi bi-shield-check me-2"></i>ความปลอดภัย</h6>
                                            <p class="mb-0 small">
                                                สำหรับความปลอดภัยของบัญชีผู้ดูแลระบบ แนะนำให้เปลี่ยนรหัสผ่านเป็นประจำ
                                                และไม่เปิดเผยข้อมูลการล็อกอินแก่ผู้อื่น
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
    
    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>

</html>
<?php $conn->close(); ?>