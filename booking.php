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

// ตั้งค่า path สำหรับรูปภาพ
$services_image_path = 'uploads/services/';
$profile_image_path = 'uploads/profile/';

$error = '';
$success = '';

// ดึงข้อมูลบริการนวด
$massageTypes = [];
$sql = "SELECT * FROM massage_types WHERE is_active = TRUE";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $massageTypes[] = $row;
    }
}

// ดึงข้อมูลหมอนวด
$therapists = [];
$sql = "SELECT t.*, GROUP_CONCAT(mt.name) as specializations 
        FROM therapists t 
        LEFT JOIN therapist_massage_types tmt ON t.therapist_id = tmt.therapist_id 
        LEFT JOIN massage_types mt ON tmt.massage_type_id = mt.massage_type_id 
        WHERE t.is_available = TRUE 
        GROUP BY t.therapist_id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $therapists[] = $row;
    }
}

// API สำหรับดึงเวลาที่ว่าง
if (isset($_GET['action']) && $_GET['action'] == 'get_available_slots') {
    $therapist_id = $conn->real_escape_string($_GET['therapist_id']);
    $booking_date = $conn->real_escape_string($_GET['booking_date']);
    $duration = $conn->real_escape_string($_GET['duration']);
    $massage_type_id = isset($_GET['massage_type_id']) ? $conn->real_escape_string($_GET['massage_type_id']) : null;

    header('Content-Type: application/json');

    // ตรวจสอบว่าหมอนวดสามารถทำบริการนี้ได้หรือไม่
    if ($massage_type_id) {
        $check_specialty_sql = "SELECT * FROM therapist_massage_types 
                               WHERE therapist_id = '$therapist_id' 
                               AND massage_type_id = '$massage_type_id'";
        $check_specialty_result = $conn->query($check_specialty_sql);

        if ($check_specialty_result->num_rows === 0) {
            echo json_encode([
                'error' => true,
                'message' => 'หมอนวดท่านนี้ไม่สามารถทำบริการที่เลือกได้',
                'slots' => []
            ]);
            exit();
        }
    }

    // ตรวจสอบวันในสัปดาห์
    $day_of_week = date('l', strtotime($booking_date)); // Monday, Tuesday, etc.

    // ดึงเวลาทำงานของหมอนวดจากตาราง working_hours
    $working_hours_sql = "SELECT start_time, end_time 
                         FROM working_hours 
                         WHERE therapist_id = '$therapist_id' 
                         AND day_of_week = '$day_of_week'";
    $working_hours_result = $conn->query($working_hours_sql);

    if ($working_hours_result->num_rows === 0) {
        // หมอนวดไม่ทำงานในวันนี้
        echo json_encode([
            'error' => true,
            'message' => 'หมอนวดไม่ทำงานในวันที่เลือก',
            'day' => $day_of_week,
            'slots' => []
        ]);
        exit();
    }

    $working_hours = $working_hours_result->fetch_assoc();

    // ตรวจสอบวันหยุด
    $holiday_sql = "SELECT * FROM holidays 
                   WHERE holiday_date = '$booking_date' 
                   AND is_closed = TRUE";
    $holiday_result = $conn->query($holiday_sql);

    if ($holiday_result->num_rows > 0) {
        $holiday = $holiday_result->fetch_assoc();
        echo json_encode([
            'error' => true,
            'message' => 'วันที่เลือกเป็นวันหยุด: ' . $holiday['description'],
            'slots' => []
        ]);
        exit();
    }

    // ดึงการจองที่มีอยู่
    $bookings_sql = "SELECT start_time, end_time FROM bookings 
                    WHERE therapist_id = '$therapist_id' 
                    AND booking_date = '$booking_date' 
                    AND status IN ('pending', 'confirmed', 'completed')";
    $bookings_result = $conn->query($bookings_sql);

    $booked_slots = [];
    if ($bookings_result->num_rows > 0) {
        while ($row = $bookings_result->fetch_assoc()) {
            $booked_slots[] = [
                'start' => $row['start_time'],
                'end' => $row['end_time']
            ];
        }
    }

    // สร้างเวลาที่ว่าง
    $available_slots = [];
    $current_time = strtotime($working_hours['start_time']);
    $end_time = strtotime($working_hours['end_time']);
    $slot_duration = $duration * 60; // แปลงเป็นวินาที

    // ถ้าเป็นวันนี้ ต้องเช็คว่าเวลาผ่านไปแล้วหรือยัง
    $today = date('Y-m-d');
    $now = time();

    while ($current_time < $end_time) {
        $slot_start = date('H:i:s', $current_time);
        $slot_end = date('H:i:s', $current_time + $slot_duration);
        $slot_end_time = $current_time + $slot_duration;

        // ตรวจสอบว่าเวลาสิ้นสุดไม่เกินเวลาทำงาน
        if ($slot_end_time > $end_time) {
            break;
        }

        // ถ้าเป็นวันนี้ ต้องเช็คว่าเวลาผ่านไปแล้วหรือยัง (เพิ่ม buffer 1 ชั่วโมง)
        if ($booking_date === $today) {
            $slot_datetime = strtotime($booking_date . ' ' . $slot_start);
            if ($slot_datetime <= ($now + 3600)) { // ต้องจองล่วงหน้าอย่างน้อย 1 ชั่วโมง
                $current_time += 1800; // เพิ่มทีละ 30 นาที
                continue;
            }
        }

        // ตรวจสอบว่าช่วงเวลานี้ว่างหรือไม่
        $is_available = true;
        foreach ($booked_slots as $booked) {
            $booked_start = strtotime($booked['start']);
            $booked_end = strtotime($booked['end']);

            // ตรวจสอบการทับซ้อน
            if (
                ($current_time >= $booked_start && $current_time < $booked_end) ||
                ($slot_end_time > $booked_start && $slot_end_time <= $booked_end) ||
                ($current_time <= $booked_start && $slot_end_time >= $booked_end)
            ) {
                $is_available = false;
                break;
            }
        }

        if ($is_available) {
            $available_slots[] = [
                'start' => $slot_start,
                'end' => $slot_end,
                'display' => date('H:i', $current_time) . ' - ' . date('H:i', $slot_end_time)
            ];
        }

        $current_time += 1800; // เพิ่มทีละ 30 นาที
    }

    echo json_encode([
        'error' => false,
        'working_hours' => [
            'start' => $working_hours['start_time'],
            'end' => $working_hours['end_time'],
            'day' => $day_of_week
        ],
        'slots' => $available_slots,
        'total_slots' => count($available_slots)
    ]);
    exit();
}

// จัดการการจอง
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $massage_type_id = $conn->real_escape_string($_POST['massage_type_id']);
    $therapist_id = $conn->real_escape_string($_POST['therapist_id']);
    $booking_date = $conn->real_escape_string($_POST['booking_date']);
    $start_time = $conn->real_escape_string($_POST['start_time']);

    // ดึงข้อมูลบริการเพื่อคำนวณราคาและเวลา
    $service_sql = "SELECT * FROM massage_types WHERE massage_type_id = '$massage_type_id'";
    $service_result = $conn->query($service_sql);

    if ($service_result->num_rows > 0) {
        $service = $service_result->fetch_assoc();
        $duration = $service['duration_minutes'];
        $total_price = $service['price'];

        // คำนวณเวลาสิ้นสุด
        $start_datetime = strtotime($booking_date . ' ' . $start_time);
        $end_datetime = $start_datetime + ($duration * 60);
        $end_time = date('H:i:s', $end_datetime);

        // ตรวจสอบความพร้อมของหมอนวด (ตรวจสอบอีกครั้งเพื่อความปลอดภัย)
        $check_sql = "SELECT * FROM bookings 
                     WHERE therapist_id = '$therapist_id' 
                     AND booking_date = '$booking_date' 
                     AND status IN ('pending', 'confirmed')
                     AND ((start_time <= '$start_time' AND end_time > '$start_time') 
                         OR (start_time < '$end_time' AND end_time >= '$end_time')
                         OR (start_time >= '$start_time' AND end_time <= '$end_time'))";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $error = 'หมอนวดไม่ว่างในเวลาที่เลือก กรุณาเลือกเวลาใหม่';
        } else {
            // สร้างการจอง
            $customer_id = $_SESSION['user_id'];
            $notes = $conn->real_escape_string($_POST['notes']);

            $insert_sql = "INSERT INTO bookings (customer_id, therapist_id, massage_type_id, booking_date, start_time, end_time, total_price, notes) 
                          VALUES ('$customer_id', '$therapist_id', '$massage_type_id', '$booking_date', '$start_time', '$end_time', '$total_price', '$notes')";

            if ($conn->query($insert_sql)) {
                $booking_id = $conn->insert_id;

                // Redirect ไปยังหน้า payment.php พร้อม booking_id
                header('Location: payment.php?booking_id=' . $booking_id);
                exit();
            } else {
                $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
            }
        }
    } else {
        $error = 'ไม่พบบริการที่เลือก';
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองคิวนวด - I Love Massage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="CSS/customer-styles.css"> -->
    <style>
        /* Global Styles */
        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            background-color: #f8fdfb;
        }

        /* Card Styles */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #e6f7f3;
            background: #ffffff;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 195, 161, 0.15) !important;
        }

        .card-header.bg-primary {
            background: linear-gradient(135deg, #4fc3a1 0%, #38b2ac 100%) !important;
            border-bottom: none;
        }

        /* Button Styles */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            padding: 10px 20px;
        }

        .btn-outline-secondary {
            border: 2px solid #718096;
            color: #718096;
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: #718096;
            border-color: #718096;
            transform: translateY(-1px);
            color: white;
        }

        .btn-success {
            background: linear-gradient(45deg, #68d391, #48bb78);
            color: #ffffff;
        }

        .btn-success:hover {
            background: linear-gradient(45deg, #48bb78, #38a169);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(104, 211, 145, 0.4);
        }

        .btn-success:disabled {
            background: #cbd5e0;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        /* Available Slots */
        .available-slot {
            background: linear-gradient(135deg, #f0f9f7, #e6f7f3);
            border: 2px solid #4fc3a1;
            border-radius: 10px;
            padding: 12px 15px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #2d5a5a;
            font-weight: 500;
            text-align: center;
        }

        .available-slot:hover {
            background: linear-gradient(135deg, #e6f7f3, #d1f2eb);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 195, 161, 0.2);
        }

        .available-slot.selected {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            color: white;
            border-color: #2c9c8a;
            box-shadow: 0 4px 15px rgba(79, 195, 161, 0.4);
        }

        .slot-disabled {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            border: 2px solid #fc8181;
            color: #c53030;
            border-radius: 10px;
            padding: 12px 15px;
            margin: 5px;
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 30px;
        }

        #availableSlotsContainer {
            min-height: 150px;
            border: 2px solid #e6f7f3;
            border-radius: 10px;
            padding: 20px;
            margin-top: 10px;
            background: #ffffff;
        }

        /* Service Options */
        .service-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f1f9f7;
            transition: all 0.3s ease;
        }

        .service-option:last-child {
            border-bottom: none;
        }

        .service-option:hover {
            background: #f0f9f7;
        }

        .service-option img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 15px;
            border: 2px solid #e6f7f3;
        }

        .service-option .service-info {
            flex: 1;
        }

        .service-option .service-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: #2d5a5a;
        }

        .service-option .service-details {
            font-size: 0.9rem;
            color: #718096;
        }

        /* Therapist Options */
        .therapist-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f1f9f7;
            transition: all 0.3s ease;
        }

        .therapist-option:last-child {
            border-bottom: none;
        }

        .therapist-option:hover {
            background: #f0f9f7;
        }

        .therapist-option img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 15px;
            border: 2px solid #e6f7f3;
        }

        .therapist-option .therapist-info {
            flex: 1;
        }

        .therapist-option .therapist-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: #2d5a5a;
        }

        .therapist-option .therapist-specialties {
            font-size: 0.9rem;
            color: #718096;
        }

        /* Summary Styles */
        .summary-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 15px;
            border: 2px solid #e6f7f3;
        }

        .summary-therapist-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 15px;
            border: 2px solid #e6f7f3;
        }

        .summary-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8fdfb;
            border-radius: 10px;
            border: 1px solid #e6f7f3;
            transition: all 0.3s ease;
        }

        .summary-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 195, 161, 0.1);
        }

        /* Custom Select Container */
        .custom-select-container {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #e6f7f3;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(79, 195, 161, 0.08);
        }

        .custom-option {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .custom-option:hover {
            background: #f0f9f7;
        }

        .custom-option.selected {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            border-left: 4px solid #2c9c8a;
        }

        .custom-option.selected .service-name,
        .custom-option.selected .therapist-name {
            color: #ffffff;
        }

        .custom-option.selected .service-details,
        .custom-option.selected .therapist-specialties {
            color: #e6f7f3;
        }

        .custom-option.selected img {
            border-color: #ffffff;
        }

        /* Form Styles */
        .form-control {
            border: 2px solid #e6f7f3;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4fc3a1;
            box-shadow: 0 0 0 0.2rem rgba(79, 195, 161, 0.25);
        }

        .form-label {
            color: #2d5a5a;
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #c53030;
        }

        .alert-info {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #2d5a5a;
            border-left: 4px solid #48bb78;
        }

        .alert-warning {
            background: linear-gradient(135deg, #feebc8, #fbd38d);
            color: #dd6b20;
        }

        /* Info Cards */
        .bg-light {
            background-color: #f0f9f7 !important;
        }

        .card.border-0.bg-light {
            background: #ffffff !important;
            border: 1px solid #e6f7f3 !important;
            transition: all 0.3s ease;
        }

        .card.border-0.bg-light:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(79, 195, 161, 0.1);
        }

        /* Text Colors */
        .text-primary {
            color: #4fc3a1 !important;
        }

        .text-dark {
            color: #2d5a5a !important;
        }

        .text-muted {
            color: #718096 !important;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #2d5a5a 0%, #234e52 100%) !important;
            margin-top: 3rem !important;
        }

        /* Custom Scrollbar */
        .custom-select-container::-webkit-scrollbar {
            width: 6px;
        }

        .custom-select-container::-webkit-scrollbar-track {
            background: #f1f9f7;
            border-radius: 3px;
        }

        .custom-select-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #4fc3a1, #38b2ac);
            border-radius: 3px;
        }

        .custom-select-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #38b2ac, #2c9c8a);
        }

        /* Loading Animation */
        .spinner-border.text-primary {
            color: #4fc3a1 !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .available-slot {
                padding: 10px 12px;
                margin: 3px;
                font-size: 0.9rem;
            }

            .service-option,
            .therapist-option {
                padding: 12px;
            }

            .service-option img,
            .therapist-option img {
                width: 50px;
                height: 50px;
                margin-right: 12px;
            }

            .summary-image,
            .summary-therapist-image {
                width: 60px;
                height: 60px;
                margin-right: 12px;
            }

            .summary-item {
                padding: 12px;
            }

            .custom-select-container {
                max-height: 300px;
            }
        }

        @media (max-width: 576px) {
            #availableSlotsContainer {
                padding: 15px;
            }

            .available-slot {
                padding: 8px 10px;
                font-size: 0.85rem;
            }

            .service-option,
            .therapist-option {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }

            .service-option img,
            .therapist-option img {
                margin-right: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'templates/navbar-user.php'; ?>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>จองคิวนวด</h2>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>กลับ
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>กรุณากรอกข้อมูลการจอง</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="bookingForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- เลือกบริการ -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">เลือกบริการ *</label>
                                        <div class="custom-select-container" id="serviceSelectContainer">
                                            <?php foreach ($massageTypes as $type): ?>
                                                <div class="custom-option service-option"
                                                    data-value="<?= $type['massage_type_id'] ?>"
                                                    data-duration="<?= $type['duration_minutes'] ?>"
                                                    data-price="<?= $type['price'] ?>">
                                                    <?php if (!empty($type['image_url'])): ?>
                                                        <img src="<?= $services_image_path . $type['image_url'] ?>"
                                                            alt="<?= htmlspecialchars($type['name']) ?>"
                                                            onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj7lm77lg488L3RleHQ+PC9zdmc+'">
                                                    <?php else: ?>
                                                        <div
                                                            style="width:50px;height:50px;background:#ddd;border-radius:5px;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                                                            <i class="fas fa-spa text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="service-info">
                                                        <div class="service-name"><?= htmlspecialchars($type['name']) ?>
                                                        </div>
                                                        <div class="service-details">
                                                            <?= $type['duration_minutes'] ?> นาที -
                                                            ฿<?= number_format($type['price'], 2) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="massage_type_id" id="massage_type_id" required>
                                    </div>

                                    <!-- เลือกหมอนวด -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">เลือกหมอนวด *</label>
                                        <div class="custom-select-container" id="therapistSelectContainer">
                                            <?php foreach ($therapists as $therapist): ?>
                                                <div class="custom-option therapist-option"
                                                    data-value="<?= $therapist['therapist_id'] ?>">
                                                    <?php if (!empty($therapist['image_url'])): ?>
                                                        <img src="<?= $profile_image_path . $therapist['image_url'] ?>"
                                                            alt="<?= htmlspecialchars($therapist['full_name']) ?>"
                                                            onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTAiIGhlaWdodD0iNTAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj7lm77lg488L3RleHQ+PC9zdmc+'">
                                                    <?php else: ?>
                                                        <div
                                                            style="width:50px;height:50px;background:#ddd;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-right:12px;">
                                                            <i class="fas fa-user-md text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="therapist-info">
                                                        <div class="therapist-name">
                                                            <?= htmlspecialchars($therapist['full_name']) ?>
                                                        </div>
                                                        <div class="therapist-specialties">
                                                            <?= htmlspecialchars($therapist['specializations'] ?? 'ไม่มีข้อมูลความเชี่ยวชาญ') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="therapist_id" id="therapist_id" required>
                                    </div>

                                    <!-- วันที่จอง -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">วันที่จอง *</label>
                                        <input type="date" name="booking_date" id="booking_date" class="form-control"
                                            min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                            required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <!-- เวลาที่ว่าง -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">เวลาเริ่มต้น *</label>
                                        <input type="hidden" name="start_time" id="selected_time" required>
                                        <div id="availableSlotsContainer">
                                            <div class="text-center text-muted">
                                                <i class="fas fa-clock fa-2x mb-2"></i>
                                                <p>กรุณาเลือกบริการ, หมอนวด และวันที่เพื่อดูเวลาที่ว่าง</p>
                                            </div>
                                        </div>
                                        <div class="loading-spinner" id="loadingSpinner">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">กำลังโหลด...</span>
                                            </div>
                                            <p class="mt-2">กำลังค้นหาช่วงเวลาที่ว่าง...</p>
                                        </div>
                                    </div>

                                    <!-- หมายเหตุ -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">หมายเหตุ</label>
                                        <textarea name="notes" class="form-control" rows="3"
                                            placeholder="ระบุข้อความเพิ่มเติม (ถ้ามี)"></textarea>
                                    </div>

                                    <!-- สรุปการจอง -->
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6><i class="fas fa-receipt me-2"></i>สรุปการจอง</h6>
                                            <div id="bookingSummary">
                                                <p class="text-muted mb-1">กรุณาเลือกบริการและเวลาก่อน</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <h6><i class="fas fa-lightbulb me-2"></i>คำแนะนำ</h6>
                                <ul class="mb-0">
                                    <li>ระบบจะแสดงเฉพาะเวลาที่หมอนวดว่างเท่านั้น</li>
                                    <li>กรุณาตรวจสอบวันที่และเวลาก่อนกดยืนยันการจอง</li>
                                    <li>หลังจากจองสำเร็จ จะนำท่านไปยังหน้าชำระเงินทันที</li>
                                </ul>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5 py-2" id="submitBtn" disabled>
                                    <i class="fas fa-check-circle me-2"></i>ยืนยันการจอง
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ข้อมูลเพิ่มเติม -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-clock text-primary fs-1 mb-3"></i>
                                <h6>จองล่วงหน้า</h6>
                                <p class="text-muted small">จองล่วงหน้าได้สูงสุด 30 วัน</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-user-md text-primary fs-1 mb-3"></i>
                                <h6>หมอนวดมืออาชีพ</h6>
                                <p class="text-muted small">ทีมงานผ่านการฝึกอบรมและมีประสบการณ์</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-credit-card text-primary fs-1 mb-3"></i>
                                <h6>ชำระเงินง่าย</h6>
                                <p class="text-muted small">ชำระเงินได้หลายช่องทาง</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'templates\footer-user.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedService = null;
        let selectedTherapist = null;
        let selectedDate = null;
        let selectedTime = null;
        let selectedServiceData = null;
        let selectedTherapistData = null;

        // ตั้งค่าวันเริ่มต้นเป็นวันพรุ่งนี้
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowFormatted = tomorrow.toISOString().split('T')[0];
        document.getElementById('booking_date').value = tomorrowFormatted;
        selectedDate = tomorrowFormatted;

        // ฟังก์ชันเลือกบริการ
        function setupServiceSelection() {
            const serviceOptions = document.querySelectorAll('#serviceSelectContainer .custom-option');
            serviceOptions.forEach(option => {
                option.addEventListener('click', function () {
                    // ล้างการเลือกเดิม
                    serviceOptions.forEach(opt => opt.classList.remove('selected'));

                    // เลือกบริการใหม่
                    this.classList.add('selected');
                    selectedService = this.getAttribute('data-value');
                    selectedServiceData = {
                        id: this.getAttribute('data-value'),
                        name: this.querySelector('.service-name').textContent,
                        duration: this.getAttribute('data-duration'),
                        price: this.getAttribute('data-price'),
                        image: this.querySelector('img') ? this.querySelector('img').src : null
                    };
                    document.getElementById('massage_type_id').value = selectedService;

                    fetchAvailableSlots();
                    updateBookingSummary();
                });
            });
        }

        // ฟังก์ชันเลือกหมอนวด
        function setupTherapistSelection() {
            const therapistOptions = document.querySelectorAll('#therapistSelectContainer .custom-option');
            therapistOptions.forEach(option => {
                option.addEventListener('click', function () {
                    // ล้างการเลือกเดิม
                    therapistOptions.forEach(opt => opt.classList.remove('selected'));

                    // เลือกหมอนวดใหม่
                    this.classList.add('selected');
                    selectedTherapist = this.getAttribute('data-value');
                    selectedTherapistData = {
                        id: this.getAttribute('data-value'),
                        name: this.querySelector('.therapist-name').textContent,
                        specialties: this.querySelector('.therapist-specialties').textContent,
                        image: this.querySelector('img') ? this.querySelector('img').src : null
                    };
                    document.getElementById('therapist_id').value = selectedTherapist;

                    fetchAvailableSlots();
                    updateBookingSummary();
                });
            });
        }

        // ฟังก์ชันอัพเดทสรุปการจอง
        function updateBookingSummary() {
            const summaryElement = document.getElementById('bookingSummary');
            const submitBtn = document.getElementById('submitBtn');

            if (selectedServiceData && selectedTherapistData && selectedDate && selectedTime) {
                summaryElement.innerHTML = `
                    <div class="summary-item">
                        ${selectedServiceData.image ?
                        `<img src="${selectedServiceData.image}" class="summary-image" alt="${selectedServiceData.name}">` :
                        `<div class="summary-image bg-primary bg-opacity-10 d-flex align-items-center justify-content-center">
                                <i class="fas fa-spa text-primary"></i>
                            </div>`
                    }
                        <div>
                            <strong>บริการ:</strong> ${selectedServiceData.name}<br>
                            <small class="text-muted">${selectedServiceData.duration} นาที - ฿${parseFloat(selectedServiceData.price).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</small>
                        </div>
                    </div>
                    <div class="summary-item">
                        ${selectedTherapistData.image ?
                        `<img src="${selectedTherapistData.image}" class="summary-therapist-image" alt="${selectedTherapistData.name}">` :
                        `<div class="summary-therapist-image bg-primary bg-opacity-10 d-flex align-items-center justify-content-center">
                                <i class="fas fa-user-md text-primary"></i>
                            </div>`
                    }
                        <div>
                            <strong>หมอนวด:</strong> ${selectedTherapistData.name}<br>
                            <small class="text-muted">${selectedTherapistData.specialties}</small>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <strong>วันที่:</strong><br>
                            <strong>เวลา:</strong>
                        </div>
                        <div class="col-6">
                            ${new Date(selectedDate).toLocaleDateString('th-TH')}<br>
                            ${selectedTime}
                        </div>
                    </div>
                    <hr>
                    <div class="text-end">
                        <strong>รวม: ฿${parseFloat(selectedServiceData.price).toLocaleString('th-TH', { minimumFractionDigits: 2 })}</strong>
                    </div>
                `;
                submitBtn.disabled = false;
            } else {
                summaryElement.innerHTML = '<p class="text-muted mb-1">กรุณาเลือกบริการและเวลาก่อน</p>';
                submitBtn.disabled = true;
            }
        }

        // ฟังก์ชันดึงเวลาที่ว่าง
        function fetchAvailableSlots() {
            if (!selectedService || !selectedTherapist || !selectedDate) {
                document.getElementById('availableSlotsContainer').innerHTML = `
                    <div class="text-center text-muted">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <p>กรุณาเลือกบริการ, หมอนวด และวันที่เพื่อดูเวลาที่ว่าง</p>
                    </div>
                `;
                selectedTime = null;
                document.getElementById('selected_time').value = '';
                updateBookingSummary();
                return;
            }

            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('availableSlotsContainer').innerHTML = '';

            // ส่ง request ไปยัง API
            fetch(`?action=get_available_slots&therapist_id=${selectedTherapist}&booking_date=${selectedDate}&duration=${selectedServiceData.duration}&massage_type_id=${selectedService}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingSpinner').style.display = 'none';

                    // ตรวจสอบ error
                    if (data.error) {
                        document.getElementById('availableSlotsContainer').innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>${data.message}</strong>
                    ${data.day ? `<br><small>วัน: ${data.day}</small>` : ''}
                </div>
            `;
                        selectedTime = null;
                        document.getElementById('selected_time').value = '';
                        updateBookingSummary();
                        return;
                    }

                    const slots = data.slots;

                    if (slots.length > 0) {
                        let slotsHTML = `
                <div class="alert alert-info py-2 mb-3">
                    <i class="fas fa-clock me-2"></i>
                    <small>เวลาทำงาน: ${data.working_hours.start.substring(0, 5)} - ${data.working_hours.end.substring(0, 5)} น. 
                    (พบ ${data.total_slots} ช่วงเวลาว่าง)</small>
                </div>
                <div class="row">
            `;
                        slots.forEach(slot => {
                            slotsHTML += `
                    <div class="col-md-6 mb-2">
                        <div class="available-slot" data-time="${slot.start}">
                            <i class="fas fa-clock me-2"></i>${slot.display}
                        </div>
                    </div>
                `;
                        });
                        slotsHTML += '</div>';
                        document.getElementById('availableSlotsContainer').innerHTML = slotsHTML;

                        // เพิ่ม event listener (เหมือนเดิม)
                        document.querySelectorAll('.available-slot').forEach(slot => {
                            slot.addEventListener('click', function () {
                                document.querySelectorAll('.available-slot').forEach(s => {
                                    s.classList.remove('selected');
                                });
                                this.classList.add('selected');
                                selectedTime = this.textContent.trim();
                                document.getElementById('selected_time').value = this.getAttribute('data-time');
                                updateBookingSummary();
                            });
                        });
                    } else {
                        document.getElementById('availableSlotsContainer').innerHTML = `
                <div class="text-center text-danger">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <p>ไม่มีเวลาว่างในวันที่เลือก<br>กรุณาเลือกวันที่อื่น</p>
                </div>
            `;
                        selectedTime = null;
                        document.getElementById('selected_time').value = '';
                        updateBookingSummary();
                    }
                })
                .catch(error => {
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('availableSlotsContainer').innerHTML = `
            <div class="text-center text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <p>เกิดข้อผิดพลาดในการโหลดข้อมูล</p>
            </div>
        `;
                    console.error('Error:', error);
                });
        }

        // Event listeners
        document.getElementById('booking_date').addEventListener('change', function () {
            selectedDate = this.value;
            fetchAvailableSlots();
            updateBookingSummary();
        });

        // ตรวจสอบฟอร์มก่อนส่ง
        document.getElementById('bookingForm').addEventListener('submit', function (e) {
            if (!selectedService || !selectedTherapist || !selectedDate || !selectedTime) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลให้ครบถ้วนก่อนยืนยันการจอง');
            }
        });

        // โหลด slots เมื่อหน้าโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', function () {
            setupServiceSelection();
            setupTherapistSelection();
            updateBookingSummary();
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>