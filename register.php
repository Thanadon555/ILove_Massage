<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // เก็บรหัสผ่านแบบ plain text
    $confirm_password = $_POST['confirm_password'];
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone']);

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($phone)) {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านไม่ตรงกัน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        // Check if username or email exists
        $check_sql = "SELECT user_id FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $error = 'ชื่อผู้ใช้หรืออีเมลนี้มีอยู่ในระบบแล้ว';
        } else {
            // เก็บรหัสผ่านแบบ plain text โดยตรง
            $password_hash = $password; // ไม่ต้องเข้ารหัส

            // Insert new user
            $sql = "INSERT INTO users (username, email, password_hash, full_name, phone, role) 
                    VALUES ('$username', '$email', '$password_hash', '$full_name', '$phone', 'customer')";

            if ($conn->query($sql)) {
                $success = 'สมัครสมาชิกสำเร็จ!';
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Global Styles */
        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #f0f9f7 0%, #e6f7f3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }

        /* Container */
        .container {
            max-width: 500px;
        }

        /* Logo/Brand */
        .brand-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo {
            font-size: 2.5rem;
            color: #4fc3a1;
            margin-bottom: 0.5rem;
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2d5a5a;
            margin-bottom: 0.25rem;
        }

        .brand-subtitle {
            font-size: 1.2rem;
            color: #718096;
            margin-bottom: 0.5rem;
        }

        .brand-description {
            font-size: 0.9rem;
            color: #718096;
        }

        /* Card Styles */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e6f7f3;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(79, 195, 161, 0.1);
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(79, 195, 161, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%) !important;
            color: white;
            border-bottom: none;
            padding: 1.5rem;
            text-align: center;
        }

        .card-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Styles */
        .form-control {
            border: 2px solid #e6f7f3;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
            background: #ffffff;
            font-size: 0.95rem;
            height: 48px;
        }

        .form-control:focus {
            border-color: #4fc3a1;
            box-shadow: 0 0 0 0.2rem rgba(79, 195, 161, 0.15);
            background: #f8fdfb;
        }

        .form-label {
            color: #2d5a5a;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-text {
            color: #718096;
            font-size: 0.8rem;
            margin-top: 5px;
            font-style: italic;
        }

        /* Button Styles */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            padding: 12px 25px;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #38b2ac, #2c9c8a);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(79, 195, 161, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid #4fc3a1;
            color: #4fc3a1;
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: #4fc3a1;
            color: white;
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #c53030;
            border-left: 4px solid #fc8181;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #2d5a5a;
            border-left: 4px solid #48bb78;
        }

        /* Link Styles */
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .footer-links a {
            color: #718096;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #4fc3a1;
        }

        /* Text Colors */
        .text-muted {
            color: #718096 !important;
        }

        /* Form Row Spacing */
        .mb-3 {
            margin-bottom: 1.25rem !important;
        }

        .mt-3 {
            margin-top: 1.25rem !important;
        }

        /* Password field styling */
        input[type="password"] {
            letter-spacing: 1px;
        }

        /* Required field indicator */
        .form-label:after {
            content: " *";
            color: #fc8181;
        }

        /* Icon spacing */
        .form-label i {
            width: 16px;
            margin-right: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }

            .brand-title {
                font-size: 1.8rem;
            }

            .brand-subtitle {
                font-size: 1.1rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .card-header {
                padding: 1.25rem;
            }

            .card-header h4 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 15px 0;
            }

            .container {
                padding: 0 15px;
            }

            .brand-title {
                font-size: 1.6rem;
            }

            .brand-subtitle {
                font-size: 1rem;
            }

            .card-body {
                padding: 1.25rem;
            }

            .card-header {
                padding: 1rem;
            }

            .card-header h4 {
                font-size: 1.2rem;
            }

            .form-control {
                padding: 10px 12px;
                height: 44px;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f9f7;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            border-radius: 8px;
        }

        /* Focus states */
        .btn:focus,
        .form-control:focus,
        a:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 195, 161, 0.2);
        }

        /* Success message animation */
        .alert-success {
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Brand Section -->
        <div class="brand-section">
            <div class="brand-logo">
                <i class="fas fa-spa"></i>
            </div>
            <h1 class="brand-title">I Love Massage</h1>
            <!-- <h2 class="brand-subtitle">นวดแผนไทย</h2> -->
            <p class="brand-description">เริ่มต้นใช้งานระบบจองคิวของคุณ</p>
        </div>

        <!-- Registration Card -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-user-plus me-2"></i>สมัครสมาชิก</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm">
                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user text-primary"></i>
                            ชื่อผู้ใช้
                        </label>
                        <input type="text" name="username" class="form-control" placeholder="กรอกชื่อผู้ใช้" required
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-envelope text-primary"></i>
                            อีเมล
                        </label>
                        <input type="email" name="email" class="form-control" placeholder="กรอกอีเมล" required
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-id-card text-primary"></i>
                            ชื่อ-นามสกุล
                        </label>
                        <input type="text" name="full_name" class="form-control" placeholder="กรอกชื่อ-นามสกุล" required
                            value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                    </div>

                    <!-- Phone -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-phone text-primary"></i>
                            เบอร์โทรศัพท์
                        </label>
                        <input type="tel" name="phone" class="form-control" placeholder="กรอกเบอร์โทรศัพท์" required
                            value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock text-primary"></i>
                            รหัสผ่าน
                        </label>
                        <input type="password" name="password" class="form-control" placeholder="กรอกรหัสผ่าน" required
                            minlength="6">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock text-primary"></i>
                            ยืนยันรหัสผ่าน
                        </label>
                        <input type="password" name="confirm_password" class="form-control"
                            placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>
                            สมัครสมาชิก
                        </button>
                    </div>
                </form>

                <!-- Login Link -->
                <div class="text-center mt-3">
                    <p class="text-muted mb-2">มีบัญชีอยู่แล้ว?</p>
                    <a href="login.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        เข้าสู่ระบบ
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer Links -->
        <div class="footer-links">
            <a href="index.php">
                <i class="fas fa-home me-1"></i>หน้าแรก
            </a>
            <a href="contact.php">
                <i class="fas fa-phone me-1"></i>ติดต่อเรา
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');

            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
                confirmPassword.focus();
                return;
            }

            if (password.value.length < 6) {
                e.preventDefault();
                alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                password.focus();
            }
        });

        // Real-time password confirmation check
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(input => {
            input.addEventListener('input', function () {
                const password = document.querySelector('input[name="password"]');
                const confirmPassword = document.querySelector('input[name="confirm_password"]');

                if (password.value && confirmPassword.value) {
                    if (password.value === confirmPassword.value) {
                        confirmPassword.style.borderColor = '#68d391';
                    } else {
                        confirmPassword.style.borderColor = '#fc8181';
                    }
                }
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>