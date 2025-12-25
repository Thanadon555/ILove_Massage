<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);

    // ตรวจสอบว่าอีเมลมีอยู่ในระบบหรือไม่
    $sql = "SELECT user_id, username, full_name FROM users WHERE email = '$email' AND is_active = TRUE";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // สร้างรหัสผ่านชั่วคราว (plain text)
        $temporary_password = substr(md5(uniqid()), 0, 8);

        // อัพเดทรหัสผ่านชั่วคราว (เก็บแบบ plain text)
        $update_sql = "UPDATE users SET password_hash = '$temporary_password', updated_at = NOW() WHERE user_id = '{$user['user_id']}'";

        if ($conn->query($update_sql)) {
            $success = "เราได้ส่งรหัสผ่านชั่วคราวให้คุณแล้ว!<br>
                       <strong>รหัสผ่านชั่วคราว:</strong> $temporary_password<br>
                       <small class='text-danger'>กรุณาเปลี่ยนรหัสผ่านหลังจากเข้าสู่ระบบ</small>";
        } else {
            $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }
    } else {
        $error = 'ไม่พบบัญชีผู้ใช้กับอีเมลนี้';
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .forgot-container {
            width: 100%;
            max-width: 500px;
        }

        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        /* Brand Section */
        .brand-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem 2rem;
            text-align: center;
            color: white;
        }

        .brand-logo {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .brand-subtitle {
            font-size: 1.2rem;
            font-weight: 300;
            opacity: 0.9;
        }

        .brand-description {
            margin-top: 1rem;
            font-size: 0.95rem;
            opacity: 0.85;
        }

        /* Form Section */
        .form-section {
            padding: 2.5rem;
        }

        .form-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-description {
            color: #718096;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            color: #c53030;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-label i {
            width: 16px;
            margin-right: 0.5rem;
            color: #667eea;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
            border-radius: 12px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        /* Link Styles */
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .footer-links a {
            color: #718096;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #667eea;
        }

        /* Help Card */
        .help-card {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .help-card h6 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .help-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .help-card li {
            color: #718096;
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .help-card li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #667eea;
            font-weight: bold;
        }

        /* Success Box */
        .success-box {
            text-align: center;
            padding: 2rem;
        }

        .success-icon {
            font-size: 4rem;
            color: #48bb78;
            margin-bottom: 1rem;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .temp-password {
            background: #f7fafc;
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            letter-spacing: 2px;
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .brand-logo {
                font-size: 3rem;
            }

            .brand-title {
                font-size: 1.5rem;
            }

            .form-section {
                padding: 1.5rem;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <!-- Brand Section -->
            <div class="brand-section">
                <div class="brand-logo">
                    <i class="fas fa-spa"></i>
                </div>
                <h1 class="brand-title">I Love Massage</h1>
                <h2 class="brand-subtitle">นวดแผนไทย</h2>
                <p class="brand-description">กู้คืนรหัสผ่านของคุณ</p>
            </div>

            <!-- Form Section -->
            <div class="form-section">
                <?php if ($success): ?>
                    <!-- Success Message -->
                    <div class="success-box">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="text-success mb-3">สำเร็จ!</h3>
                        <p class="text-muted mb-3">เราได้สร้างรหัสผ่านชั่วคราวให้คุณแล้ว</p>
                        
                        <div class="temp-password">
                            <?= htmlspecialchars($temporary_password) ?>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>สำคัญ:</strong> กรุณาเปลี่ยนรหัสผ่านหลังจากเข้าสู่ระบบ
                        </div>
                        
                        <a href="login.php" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                        </a>
                    </div>
                <?php else: ?>
                    <h2 class="form-title">ลืมรหัสผ่าน?</h2>
                    <p class="form-description">
                        กรอกอีเมลที่ใช้สมัครสมาชิก<br>
                        เราจะส่งรหัสผ่านชั่วคราวให้คุณทันที
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="forgotForm">
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                อีเมล
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="example@email.com" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-paper-plane me-2"></i>
                            ส่งรหัสผ่านชั่วคราว
                        </button>

                        <a href="login.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-arrow-left me-2"></i>
                            กลับไปหน้าเข้าสู่ระบบ
                        </a>
                    </form>

                    <!-- Help Card -->
                    <div class="help-card">
                        <h6><i class="fas fa-info-circle me-2"></i>คำแนะนำ</h6>
                        <ul>
                            <li>ใช้อีเมลที่ลงทะเบียนไว้กับระบบ</li>
                            <li>รหัสผ่านชั่วคราวจะแสดงทันทีบนหน้าจอ</li>
                            <li>กรุณาเปลี่ยนรหัสผ่านหลังเข้าสู่ระบบ</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Links -->
        <div class="footer-links">
            <a href="index.php">
                <i class="fas fa-home me-1"></i>หน้าแรก
            </a>
            <a href="register.php">
                <i class="fas fa-user-plus me-1"></i>สมัครสมาชิก
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>
