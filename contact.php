<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
require_once 'config/database.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

// ตัวแปรสำหรับแสดงข้อความ
$success = '';
$error = '';

// ดึงประวัติการส่งข้อความของลูกค้า (ใช้ฟิลด์ใหม่)
$contact_history = [];
try {
    $history_sql = "SELECT 
                    contact_id, 
                    subject, 
                    message, 
                    status, 
                    admin_notes,
                    admin_reply_subject,
                    admin_reply_message,
                    email_sent,
                    replied_at,
                    DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as formatted_date,
                    DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') as formatted_updated
                FROM contacts 
                WHERE customer_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param("i", $user_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    $contact_history = $history_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching contact history: " . $e->getMessage());
}

// ประมวลผลฟอร์มเมื่อส่ง
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } else {
        try {
            // บันทึกข้อมูลการติดต่อลงฐานข้อมูล
            $insert_sql = "INSERT INTO contacts (customer_id, customer_name, customer_email, customer_phone, subject, message, status) 
                          VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isssss", $user_id, $name, $email, $phone, $subject, $message);

            if ($insert_stmt->execute()) {
                $success = 'ขอบคุณสำหรับข้อความของคุณ! เราจะติดต่อกลับโดยเร็วที่สุด';

                // รีเซ็ตฟอร์ม
                $_POST = array();

                // ดึงประวัติใหม่
                $history_stmt->execute();
                $history_result = $history_stmt->get_result();
                $contact_history = $history_result->fetch_all(MYSQLI_ASSOC);
            } else {
                $error = 'เกิดข้อผิดพลาดในการส่งข้อความ กรุณาลองใหม่อีกครั้ง';
            }
        } catch (Exception $e) {
            $error = 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage();
        }
    }
}

// ฟังก์ชันแปลงชื่อเรื่องเป็นภาษาไทย
function getSubjectThai($subject)
{
    $subjects = [
        'booking' => '📅 ปัญหาการจองคิว',
        'payment' => '💳 ปัญหาการชำระเงิน',
        'service' => '💆 สอบถามเกี่ยวกับบริการ',
        'therapist' => '👨‍⚕️ สอบถามเกี่ยวกับหมอนวด',
        'other' => '📝 อื่นๆ'
    ];
    return $subjects[$subject] ?? '📝 อื่นๆ';
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status)
{
    $statuses = [
        'pending' => '⏳ รอดำเนินการ',
        'in_progress' => '🔄 กำลังดำเนินการ',
        'completed' => '✅ เสร็จสิ้น'
    ];
    return $statuses[$status] ?? '⏳ รอดำเนินการ';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อเรา - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-color: #4fc3a1;
            --primary-dark: #38b2ac;
            --secondary-color: #f8fdfb;
            --text-dark: #2d5a5a;
            --text-light: #718096;
            --accent-color: #ff6b6b;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #f8fdfb 0%, #e6f7f3 100%);
            min-height: 100vh;
        }

        /* Header Section */
        .contact-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .contact-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><path d="M500 50Q600 0 700 50T900 50T1000 0V100H0V0Q100 50 300 50T500 50Z"/></svg>');
            background-size: cover;
            background-position: bottom;
        }

        .contact-header h1 {
            font-weight: 800;
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .contact-header .lead {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* Contact Cards */
        .contact-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(79, 195, 161, 0.1);
            border: 1px solid rgba(79, 195, 161, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .contact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(79, 195, 161, 0.2);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            border: 2px solid #e6f7f3;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8fdfb;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 195, 161, 0.1);
            background: white;
            transform: translateY(-2px);
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 12px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(79, 195, 161, 0.4);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        /* Contact Info */
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8fdfb;
            border-radius: 15px;
            border: 1px solid #e6f7f3;
            transition: all 0.3s ease;
        }

        .contact-info-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(79, 195, 161, 0.1);
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .contact-details h5 {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .contact-details p {
            color: var(--text-light);
            margin-bottom: 0;
            line-height: 1.6;
        }

        /* History Section */
        .history-section {
            margin-top: 3rem;
        }

        .history-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .history-card:hover {
            transform: translateX(5px);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .history-subject {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            flex: 1;
            min-width: 200px;
        }

        .history-message {
            color: var(--text-light);
            margin-bottom: 1rem;
            line-height: 1.5;
            max-height: 4.5em;
            overflow: hidden;
            position: relative;
        }

        .history-message.expanded {
            max-height: none;
        }

        .read-more-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.875rem;
            padding: 0;
            margin-top: 0.5rem;
            font-weight: 600;
        }

        .history-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .history-date {
            color: var(--text-light);
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #cce7ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        /* Admin Reply Section */
        .admin-reply {
            background: linear-gradient(135deg, #e8f4fd 0%, #d1ecf1 100%);
            border-left: 4px solid var(--primary-color);
            padding: 1.5rem;
            margin-top: 1rem;
            border-radius: 0 12px 12px 0;
            position: relative;
        }

        .admin-reply::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 4px 0 0 4px;
        }

        .admin-reply-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .admin-reply-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            margin-right: 1rem;
        }

        .admin-reply-content {
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-reply-content strong {
            color: var(--primary-dark);
        }

        .reply-date {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        .empty-history {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-light);
        }

        .empty-history i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Alert Styles */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }

        /* Feature Highlights */
        .feature-highlight {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8fdfb;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .feature-highlight:hover {
            transform: translateX(5px);
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .feature-highlight h6 {
            color: var(--text-dark);
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .feature-highlight p {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .contact-header h1 {
                font-size: 2.5rem;
            }

            .contact-card {
                padding: 2rem;
                margin-bottom: 1.5rem;
            }

            .contact-info-item {
                flex-direction: column;
                text-align: center;
            }

            .contact-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .btn-primary {
                width: 100%;
            }

            .history-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .history-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .status-badge {
                align-self: flex-start;
            }

            .admin-reply-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .admin-reply-icon {
                margin-right: 0;
            }
        }

        @media (max-width: 576px) {
            .contact-header {
                padding: 3rem 0;
            }

            .contact-header h1 {
                font-size: 2rem;
            }

            .contact-card {
                padding: 1.5rem;
                border-radius: 15px;
            }

            .feature-highlight {
                flex-direction: column;
                text-align: center;
            }

            .feature-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Custom select styling */
        .form-control:invalid {
            color: #6c757d;
        }

        .form-control option[value=""] {
            color: #6c757d;
        }

        .form-control option:not([value=""]) {
            color: #212529;
        }
    </style>
</head>

<body>
    <!-- นำเข้า navbar -->
    <?php include 'templates/navbar-user.php'; ?>

    <!-- Header Section -->
    <section class="contact-header text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="animate-fade-in">ติดต่อเรา</h1>
                    <p class="lead animate-fade-in">เราพร้อมให้คำปรึกษาและตอบทุกคำถามด้วยความยินดี</p>
                    <div class="mt-4">
                        <i class="fas fa-comments fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <div class="row">
            <!-- Contact Form -->
            <div class="col-lg-8 mb-5">
                <div class="contact-card animate-fade-in">
                    <h3 class="mb-4" style="color: var(--text-dark);">
                        <i class="fas fa-paper-plane me-2"></i>ส่งข้อความถึงเรา
                    </h3>

                    <?php if ($success): ?>
                        <div class="alert alert-success animate-fade-in">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger animate-fade-in">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="contactForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">ชื่อ-นามสกุล *</label>
                                    <input type="text" name="name" class="form-control"
                                        value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($user_name) ?>"
                                        placeholder="กรุณากรอกชื่อ-นามสกุล" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">อีเมล *</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user_email) ?>"
                                        placeholder="example@email.com" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" name="phone" class="form-control"
                                        value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($user_phone) ?>"
                                        placeholder="กรุณากรอกเบอร์โทรศัพท์">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">เรื่อง *</label>
                                    <select name="subject" class="form-control" required>
                                        <option value="">-- เลือกเรื่องที่ต้องการติดต่อ --</option>
                                        <option value="booking" <?= (isset($_POST['subject']) && $_POST['subject'] == 'booking') ? 'selected' : '' ?>>ปัญหาการจองคิว</option>
                                        <option value="payment" <?= (isset($_POST['subject']) && $_POST['subject'] == 'payment') ? 'selected' : '' ?>>ปัญหาการชำระเงิน</option>
                                        <option value="service" <?= (isset($_POST['subject']) && $_POST['subject'] == 'service') ? 'selected' : '' ?>>สอบถามเกี่ยวกับบริการ
                                        </option>
                                        <option value="therapist" <?= (isset($_POST['subject']) && $_POST['subject'] == 'therapist') ? 'selected' : '' ?>>สอบถามเกี่ยวกับหมอนวด
                                        </option>
                                        <option value="other" <?= (isset($_POST['subject']) && $_POST['subject'] == 'other') ? 'selected' : '' ?>>อื่นๆ</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ข้อความ *</label>
                            <textarea name="message" class="form-control"
                                placeholder="กรุณาระบุรายละเอียดข้อความที่ต้องการติดต่อ..." rows="6"
                                required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                            <div class="form-text">
                                กรุณาระบุรายละเอียดให้ชัดเจนเพื่อให้เราสามารถช่วยเหลือคุณได้ดียิ่งขึ้น
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>ส่งข้อความ
                        </button>
                    </form>
                </div>

                <!-- ประวัติการส่งข้อความ -->
                <div class="history-section">
                    <div class="contact-card">
                        <h4 class="mb-4" style="color: var(--text-dark);">
                            <i class="fas fa-history me-2"></i>ประวัติการส่งข้อความ
                        </h4>

                        <?php if (!empty($contact_history)): ?>
                            <?php foreach ($contact_history as $history): ?>
                                <div class="history-card animate-fade-in">
                                    <div class="history-header">
                                        <h6 class="history-subject">
                                            <?= getSubjectThai($history['subject']) ?>
                                        </h6>
                                        <span class="status-badge status-<?= $history['status'] ?>">
                                            <?= getStatusThai($history['status']) ?>
                                        </span>
                                    </div>

                                    <div class="history-message" id="message-<?= $history['contact_id'] ?>">
                                        <strong>ข้อความของคุณ:</strong><br>
                                        <?= nl2br(htmlspecialchars($history['message'])) ?>
                                    </div>

                                    <?php if (strlen($history['message']) > 200): ?>
                                        <button class="read-more-btn" onclick="toggleMessage(<?= $history['contact_id'] ?>)">
                                            อ่านเพิ่มเติม
                                        </button>
                                    <?php endif; ?>

                                    <!-- แสดงข้อความตอบกลับจากผู้ดูแลระบบ -->
                                    <?php if (!empty($history['admin_reply_message']) || !empty($history['admin_notes'])): ?>
                                        <div class="admin-reply">
                                            <div class="admin-reply-header">
                                                <div class="admin-reply-icon">
                                                    <i class="fas fa-user-shield"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1" style="color: var(--primary-dark);">
                                                        <i class="fas fa-reply me-1"></i>ตอบกลับจากผู้ดูแลระบบ
                                                    </h6>
                                                    <?php if (!empty($history['admin_reply_subject'])): ?>
                                                        <small class="text-muted">เรื่อง:
                                                            <?= htmlspecialchars($history['admin_reply_subject']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="admin-reply-content">
                                                <?php if (!empty($history['admin_reply_message'])): ?>
                                                    <?= nl2br(htmlspecialchars($history['admin_reply_message'])) ?>
                                                <?php else: ?>
                                                    <?= nl2br(htmlspecialchars($history['admin_notes'])) ?>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($history['email_sent']): ?>
                                                <div class="mt-2">
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>ส่งอีเมลตอบกลับให้คุณแล้ว
                                                    </small>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($history['replied_at'])): ?>
                                                <div class="reply-date">
                                                    <i class="fas fa-clock me-1"></i>
                                                    ตอบกลับเมื่อ: <?= date('d/m/Y H:i', strtotime($history['replied_at'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($history['status'] == 'completed'): ?>
                                        <div class="admin-reply">
                                            <div class="admin-reply-header">
                                                <div class="admin-reply-icon">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0" style="color: var(--primary-dark);">
                                                        ดำเนินการเสร็จสิ้น
                                                    </h6>
                                                </div>
                                            </div>
                                            <div class="admin-reply-content">
                                                การติดต่อของคุณได้รับการดำเนินการเสร็จสิ้นแล้ว
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="history-footer">
                                        <span class="history-date">
                                            <i class="fas fa-clock me-1"></i>
                                            ส่งเมื่อ: <?= $history['formatted_date'] ?>
                                        </span>
                                        <?php if ($history['formatted_date'] != $history['formatted_updated']): ?>
                                            <span class="history-date">
                                                <i class="fas fa-sync-alt me-1"></i>
                                                อัพเดท: <?= $history['formatted_updated'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-history animate-fade-in">
                                <i class="fas fa-inbox"></i>
                                <h5>ยังไม่มีประวัติการส่งข้อความ</h5>
                                <p class="text-muted">เมื่อคุณส่งข้อความติดต่อเรา ประวัติจะปรากฏที่นี่</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="col-lg-4">
                <!-- Contact Details -->
                <div class="contact-card animate-fade-in">
                    <h4 class="mb-4" style="color: var(--text-dark);">
                        <i class="fas fa-info-circle me-2"></i>ข้อมูลติดต่อ
                    </h4>

                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="contact-details">
                            <h5>ชื่อร้าน</h5>
                            <p>I Love Massage</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-details">
                            <h5>ที่อยู่</h5>
                            <p>ช้างเผือก 30 ซอย สุขเกษม 1 ตำบลช้างเผือก เมือง เชียงใหม่ 50200</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-details">
                            <h5>โทรศัพท์</h5>
                            <p>0826843254</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-details">
                            <h5>เวลาทำการ</h5>
                            <p>จันทร์ - อาทิตย์: 09:00 - 21:00 น.</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Features -->
                <div class="contact-card animate-fade-in">
                    <h5 class="mb-4" style="color: var(--text-dark);">
                        <i class="fas fa-star me-2"></i>บริการของเรา
                    </h5>

                    <div class="feature-highlight">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h6>ปลอดภัย มาตรฐานสูง</h6>
                            <p class="text-muted">บริการที่มีมาตรฐานและความปลอดภัย</p>
                        </div>
                    </div>

                    <div class="feature-highlight">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h6>บริการรวดเร็ว</h6>
                            <p class="text-muted">ตอบคำถามภายใน 24 ชั่วโมง</p>
                        </div>
                    </div>

                    <div class="feature-highlight">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div>
                            <h6>บริการลูกค้า</h6>
                            <p class="text-muted">ทีมงานพร้อมให้บริการทุกวัน</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animation on scroll
        document.addEventListener('DOMContentLoaded', function () {
            const animateElements = document.querySelectorAll('.animate-fade-in');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                        observer.unobserve(entry.target);
                    }
                });
            });

            animateElements.forEach(el => {
                el.style.animationPlayState = 'paused';
                observer.observe(el);
            });

            // Auto-fill form with user data if available
            const userData = {
                name: '<?= addslashes($user_name) ?>',
                email: '<?= addslashes($user_email) ?>',
                phone: '<?= addslashes($user_phone) ?>'
            };

            if (userData.name && !document.querySelector('input[name="name"]').value) {
                document.querySelector('input[name="name"]').value = userData.name;
            }
            if (userData.email && !document.querySelector('input[name="email"]').value) {
                document.querySelector('input[name="email"]').value = userData.email;
            }
            if (userData.phone && !document.querySelector('input[name="phone"]').value) {
                document.querySelector('input[name="phone"]').value = userData.phone;
            }

            // Form validation
            const contactForm = document.getElementById('contactForm');
            contactForm.addEventListener('submit', function (e) {
                const requiredFields = contactForm.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#e53e3e';
                    } else {
                        field.style.borderColor = '#e6f7f3';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
                }
            });
        });

        // Toggle message expansion
        function toggleMessage(contactId) {
            const messageElement = document.getElementById('message-' + contactId);
            const button = messageElement.nextElementSibling;

            if (messageElement.classList.contains('expanded')) {
                messageElement.classList.remove('expanded');
                button.textContent = 'อ่านเพิ่มเติม';
            } else {
                messageElement.classList.add('expanded');
                button.textContent = 'ย่อข้อความ';
            }
        }
    </script>
</body>

</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
if (isset($conn)) {
    $conn->close();
}
?>