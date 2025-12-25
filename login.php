<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; // รหัสผ่านที่ผู้ใช้ป้อน

    $sql = "SELECT user_id, username, password_hash, full_name, role FROM users WHERE username = '$username' AND is_active = TRUE";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // ตรวจสอบรหัสผ่านโดยตรง (plain text comparison)
        if ($password === $user['password_hash']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Redirect ตามบทบาท
            if ($user['role'] == 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            $error = 'รหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'ไม่พบชื่อผู้ใช้';
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<style>
    /* Global Styles */
    body {
        font-family: 'Sarabun', sans-serif;
        line-height: 1.6;
        background: linear-gradient(135deg, #f0f9f7 0%, #e6f7f3 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        padding: 15px;
    }

    /* Container - ขยายขนาดให้ใหญ่ขึ้น */
    .container {
        max-width: 550px;
        /* เพิ่มจาก 500px เป็น 550px */
    }

    /* Card Styles - เพิ่มขนาดการ์ด */
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid #e6f7f3;
        background: #ffffff;
        box-shadow: 0 15px 35px rgba(79, 195, 161, 0.1);
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(79, 195, 161, 0.2);
    }

    .card-header {
        background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%) !important;
        color: white;
        border-bottom: none;
        padding: 35px 25px;
        /* เพิ่ม padding ให้มากขึ้น */
        text-align: center;
    }

    .card-header h4 {
        font-weight: 700;
        margin: 0;
        font-size: 2rem;
        /* เพิ่มขนาดฟอนต์ */
    }

    .card-body {
        padding: 45px;
        /* เพิ่ม padding ให้มากขึ้น */
    }

    /* Form Styles - เพิ่มขนาดฟิลด์ */
    .form-control {
        border: 2px solid #e6f7f3;
        border-radius: 12px;
        padding: 18px 22px;
        /* เพิ่ม padding ให้มากขึ้น */
        transition: all 0.3s ease;
        background: #ffffff;
        font-size: 1.1rem;
        /* เพิ่มขนาดฟอนต์ */
        height: auto;
        /* กำหนดความสูงอัตโนมัติ */
    }

    .form-control:focus {
        border-color: #4fc3a1;
        box-shadow: 0 0 0 0.3rem rgba(79, 195, 161, 0.15);
        background: #f8fdfb;
    }

    .form-label {
        color: #2d5a5a;
        font-weight: 600;
        margin-bottom: 12px;
        /* เพิ่มระยะห่าง */
        font-size: 1.1rem;
        /* เพิ่มขนาดฟอนต์ */
    }

    /* Button Styles - เพิ่มขนาดปุ่ม */
    .btn {
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        padding: 18px 35px;
        /* เพิ่ม padding ให้มากขึ้น */
        font-size: 1.2rem;
        /* เพิ่มขนาดฟอนต์ */
        height: auto;
        /* กำหนดความสูงอัตโนมัติ */
    }

    .btn-primary {
        background: linear-gradient(135deg, #4fc3a1, #38b2ac);
        color: #ffffff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #38b2ac, #2c9c8a);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(79, 195, 161, 0.4);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    /* Alert Styles */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 18px 22px;
        /* เพิ่ม padding ให้มากขึ้น */
        margin-bottom: 25px;
        font-size: 1.05rem;
        /* เพิ่มขนาดฟอนต์เล็กน้อย */
    }

    .alert-danger {
        background: linear-gradient(135deg, #fed7d7, #feb2b2);
        color: #c53030;
        border-left: 4px solid #fc8181;
    }

    /* Link Styles - เพิ่มขนาดลิงก์ */
    .text-center a {
        color: #4fc3a1;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        padding: 8px 12px;
        /* เพิ่ม padding ให้มากขึ้น */
        border-radius: 6px;
        font-size: 1.05rem;
        /* เพิ่มขนาดฟอนต์ */
    }

    .text-center a:hover {
        color: #2c9c8a;
        background: #f0f9f7;
        text-decoration: none;
    }

    .text-center a:first-child {
        margin-right: 15px;
    }

    .text-center a:last-child {
        margin-left: 15px;
    }

    /* Text Colors */
    .text-muted {
        color: #718096 !important;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 0 20px;
            max-width: 90%;
            /* ปรับให้กว้างขึ้นบนมือถือ */
        }

        .card-body {
            padding: 35px 30px;
            /* ปรับ padding สำหรับมือถือ */
        }

        .card-header {
            padding: 30px 25px;
            /* ปรับ padding สำหรับมือถือ */
        }

        .card-header h4 {
            font-size: 1.8rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือ */
        }

        .btn {
            padding: 16px 30px;
            /* ปรับ padding สำหรับมือถือ */
            font-size: 1.1rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือ */
        }

        .form-control {
            padding: 16px 20px;
            /* ปรับ padding สำหรับมือถือ */
            font-size: 1.05rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือ */
        }
    }

    @media (max-width: 576px) {
        body {
            padding: 15px 0;
        }

        .container {
            padding: 0 15px;
            max-width: 95%;
            /* ปรับให้กว้างขึ้นบนมือถือขนาดเล็ก */
        }

        .card-body {
            padding: 30px 25px;
            /* ปรับ padding สำหรับมือถือขนาดเล็ก */
        }

        .card-header {
            padding: 25px 20px;
            /* ปรับ padding สำหรับมือถือขนาดเล็ก */
        }

        .card-header h4 {
            font-size: 1.6rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือขนาดเล็ก */
        }

        .form-control {
            padding: 14px 18px;
            /* ปรับ padding สำหรับมือถือขนาดเล็ก */
            font-size: 1rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือขนาดเล็ก */
        }

        .btn {
            padding: 14px 25px;
            /* ปรับ padding สำหรับมือถือขนาดเล็ก */
            font-size: 1.05rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือขนาดเล็ก */
        }

        .text-center a {
            display: block;
            margin: 8px 0 !important;
            /* เพิ่มระยะห่างระหว่างลิงก์ */
            padding: 10px 0;
            /* เพิ่ม padding สำหรับลิงก์ */
            font-size: 1rem;
            /* ปรับขนาดฟอนต์สำหรับมือถือขนาดเล็ก */
        }

        .text-center a:first-child,
        .text-center a:last-child {
            margin: 8px 0 !important;
            /* เพิ่มระยะห่างระหว่างลิงก์ */
        }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f9f7;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #4fc3a1, #38b2ac);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #38b2ac, #2c9c8a);
    }

    /* Focus states for accessibility */
    .btn:focus,
    .form-control:focus,
    a:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 195, 161, 0.3);
    }

    /* Animation for form elements */
    .form-control {
        transition: all 0.3s ease;
    }

    /* Loading state for button */
    .btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none !important;
    }

    /* Additional spacing */
    .mb-3 {
        margin-bottom: 1.8rem !important;
        /* เพิ่มระยะห่างระหว่างฟิลด์ */
    }

    .mt-3 {
        margin-top: 1.8rem !important;
        /* เพิ่มระยะห่างด้านบน */
    }

    /* Password field styling */
    input[type="password"] {
        letter-spacing: 1px;
    }

    /* Center alignment for better visual balance */
    .text-center {
        line-height: 1.8;
    }
</style>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-12"> <!-- เปลี่ยนจาก col-md-4 เป็น col-md-12 เพื่อให้การ์ดกว้างขึ้น -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="text-center">เข้าสู่ระบบ</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        // กำหนดค่าเริ่มต้นสำหรับตัวแปร error
                        $error = isset($error) ? $error : '';

                        if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">ชื่อผู้ใช้</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">รหัสผ่าน</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="register.php">สมัครสมาชิกใหม่</a> |
                            <a href="forgot_password.php">ลืมรหัสผ่าน</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>