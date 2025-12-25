<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// รับค่าพารามิเตอร์
$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : date('Y-m-01', strtotime('-2 months'));
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : date('Y-m-t');
// ตั้งค่าเริ่มต้นของวันที่ (3 เดือนย้อนหลัง)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01', strtotime('-2 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// ตรวจสอบและป้องกัน SQL Injection
$start_date = $conn->real_escape_string($start_date);
$end_date = $conn->real_escape_string($end_date);
$report_type = $conn->real_escape_string($report_type);

// ตรวจสอบว่าต้องการส่งออกเป็น Excel หรือไม่
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    exportToExcel($conn, $start_date, $end_date, $report_type);
    exit();
}

// สถิติรายได้หลัก
$revenue_stats = [];
$revenue_sql = "SELECT 
                SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                COUNT(DISTINCT b.booking_id) as total_bookings,
                COUNT(DISTINCT b.customer_id) as total_customers,
                AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_revenue_per_booking,
                COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.booking_id ELSE NULL END) as cancelled_bookings,
                COUNT(DISTINCT CASE WHEN b.status = 'no_show' THEN b.booking_id ELSE NULL END) as no_show_bookings
                FROM bookings b
                LEFT JOIN payments p ON b.booking_id = p.booking_id
                WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'";

$revenue_result = $conn->query($revenue_sql);
$revenue_stats = $revenue_result->fetch_assoc();

// คำนวณอัตราการยกเลิกและไม่มาใช้บริการ
$total_completed_bookings = $revenue_stats['total_bookings'] - $revenue_stats['cancelled_bookings'] - $revenue_stats['no_show_bookings'];
$cancellation_rate = $revenue_stats['total_bookings'] > 0 ?
    ($revenue_stats['cancelled_bookings'] / $revenue_stats['total_bookings']) * 100 : 0;
$no_show_rate = $revenue_stats['total_bookings'] > 0 ?
    ($revenue_stats['no_show_bookings'] / $revenue_stats['total_bookings']) * 100 : 0;

// สถิติตามประเภทบริการ
$service_stats = [];
$service_sql = "SELECT mt.name, 
                       COUNT(b.booking_id) as booking_count, 
                       SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                       AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_revenue,
                       COUNT(DISTINCT b.customer_id) as unique_customers,
                       COUNT(CASE WHEN b.status = 'cancelled' THEN 1 ELSE NULL END) as cancelled_count
                FROM bookings b
                JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
                LEFT JOIN payments p ON b.booking_id = p.booking_id
                WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                GROUP BY mt.massage_type_id, mt.name
                ORDER BY total_revenue DESC";

$service_result = $conn->query($service_sql);
if ($service_result->num_rows > 0) {
    while ($row = $service_result->fetch_assoc()) {
        $service_stats[] = $row;
    }
}

// สถิติหมอนวด
$therapist_stats = [];
$therapist_sql = "SELECT t.therapist_id, t.full_name, 
                         COUNT(b.booking_id) as booking_count,
                         SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                         AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_revenue,
                         AVG(r.rating) as avg_rating,
                         COUNT(r.review_id) as review_count,
                         COUNT(DISTINCT b.customer_id) as unique_customers
                  FROM bookings b
                  JOIN therapists t ON b.therapist_id = t.therapist_id
                  LEFT JOIN payments p ON b.booking_id = p.booking_id
                  LEFT JOIN reviews r ON b.booking_id = r.booking_id
                  WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                  GROUP BY t.therapist_id, t.full_name
                  ORDER BY total_revenue DESC";

$therapist_result = $conn->query($therapist_sql);
if ($therapist_result->num_rows > 0) {
    while ($row = $therapist_result->fetch_assoc()) {
        $therapist_stats[] = $row;
    }
}

// สถิติลูกค้า
$customer_stats = [];
$customer_sql = "SELECT u.user_id, u.full_name, u.email, u.phone,
                        COUNT(b.booking_id) as booking_count,
                        SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_spent,
                        AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_spent,
                        MIN(b.booking_date) as first_booking,
                        MAX(b.booking_date) as last_booking
                 FROM users u
                 JOIN bookings b ON u.user_id = b.customer_id
                 LEFT JOIN payments p ON b.booking_id = p.booking_id
                 WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                 GROUP BY u.user_id, u.full_name, u.email, u.phone
                 HAVING booking_count > 0
                 ORDER BY total_spent DESC
                 LIMIT 20";

$customer_result = $conn->query($customer_sql);
if ($customer_result->num_rows > 0) {
    while ($row = $customer_result->fetch_assoc()) {
        $customer_stats[] = $row;
    }
}

// รายได้รายวัน (สำหรับกราฟ)
$daily_revenue = [];
$daily_sql = "SELECT DATE(b.booking_date) as date, 
                     SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as daily_revenue,
                     COUNT(b.booking_id) as daily_bookings,
                     COUNT(DISTINCT b.customer_id) as daily_customers
              FROM bookings b
              LEFT JOIN payments p ON b.booking_id = p.booking_id
              WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
              GROUP BY DATE(b.booking_date)
              ORDER BY date";

$daily_result = $conn->query($daily_sql);
if ($daily_result->num_rows > 0) {
    while ($row = $daily_result->fetch_assoc()) {
        $daily_revenue[] = $row;
    }
}

// สถิติรายเดือนสำหรับเทรนด์
$monthly_trends = [];
$monthly_sql = "SELECT 
                DATE_FORMAT(b.booking_date, '%Y-%m') as month,
                SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as monthly_revenue,
                COUNT(b.booking_id) as monthly_bookings,
                COUNT(DISTINCT b.customer_id) as monthly_customers,
                AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_booking_value
                FROM bookings b
                LEFT JOIN payments p ON b.booking_id = p.booking_id
                WHERE b.booking_date >= DATE_SUB('$start_date', INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(b.booking_date, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12";

$monthly_result = $conn->query($monthly_sql);
if ($monthly_result->num_rows > 0) {
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_trends[] = $row;
    }
}
$monthly_trends = array_reverse($monthly_trends); // เรียงจากเก่าสุดไปใหม่สุด

// สถิติการชำระเงิน
$payment_stats = [];
$payment_sql = "SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
                FROM payments 
                WHERE payment_status = 'completed' 
                AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
                GROUP BY payment_method
                ORDER BY total_amount DESC";

$payment_result = $conn->query($payment_sql);
if ($payment_result->num_rows > 0) {
    while ($row = $payment_result->fetch_assoc()) {
        $payment_stats[] = $row;
    }
}

// แปลงชื่อวิธีการชำระเงิน
$payment_method_names = [
    'cash' => 'เงินสด',
    'credit_card' => 'บัตรเครดิต',
    'debit_card' => 'บัตรเดบิต',
    'promptpay' => 'พร้อมเพย์',
    'bank_transfer' => 'โอนเงิน'
];

// ฟังก์ชันส่งออกเป็น Excel
function exportToExcel($conn, $start_date, $end_date, $report_type)
{
    // ตั้งค่าหัวข้อสำหรับไฟล์ Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="รายงานสถิติ_' . $start_date . '_ถึง_' . $end_date . '.xls"');
    header('Cache-Control: max-age=0');

    // เริ่มต้น output
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
          <head>
          <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
          <!--[if gte mso 9]>
          <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>รายงานสถิติ</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
          </xml>
          <![endif]-->
          </head><body>';

    // สถิติรายได้หลักสำหรับ Excel
    $revenue_sql = "SELECT 
                    SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                    COUNT(DISTINCT b.booking_id) as total_bookings,
                    COUNT(DISTINCT b.customer_id) as total_customers,
                    AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_revenue_per_booking,
                    COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.booking_id ELSE NULL END) as cancelled_bookings,
                    COUNT(DISTINCT CASE WHEN b.status = 'no_show' THEN b.booking_id ELSE NULL END) as no_show_bookings
                    FROM bookings b
                    LEFT JOIN payments p ON b.booking_id = p.booking_id
                    WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'";

    $revenue_result = $conn->query($revenue_sql);
    $revenue_stats = $revenue_result->fetch_assoc();

    // HTML สำหรับ Excel
    echo '<table border="1" cellpadding="5" cellspacing="0">';

    // หัวข้อรายงาน
    echo '<tr><th colspan="6" style="background-color: #2c3e50; color: white; font-size: 16px;">รายงานสถิติธุรกิจนวดไทย</th></tr>';
    echo '<tr><td colspan="6"><strong>ช่วงเวลา:</strong> ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</td></tr>';
    echo '<tr><td colspan="6"><strong>สร้างเมื่อ:</strong> ' . date('d/m/Y H:i') . '</td></tr>';
    echo '<tr><td colspan="6">&nbsp;</td></tr>';

    // สถิติหลัก
    echo '<tr><th colspan="6" style="background-color: #34495e; color: white;">สถิติหลัก (KPIs)</th></tr>';
    echo '<tr>
            <th>รายได้รวม</th>
            <th>การจองทั้งหมด</th>
            <th>ลูกค้าทั้งหมด</th>
            <th>มูลค่าเฉลี่ยต่อการจอง</th>
            <th>อัตราการยกเลิก</th>
            <th>อัตราไม่มาใช้บริการ</th>
          </tr>';
    echo '<tr>
            <td>฿' . number_format($revenue_stats['total_revenue'] ?: 0, 2) . '</td>
            <td>' . number_format($revenue_stats['total_bookings'] ?: 0) . '</td>
            <td>' . number_format($revenue_stats['total_customers'] ?: 0) . '</td>
            <td>฿' . number_format($revenue_stats['avg_revenue_per_booking'] ?: 0, 2) . '</td>
            <td>' . number_format(($revenue_stats['cancelled_bookings'] / max(1, $revenue_stats['total_bookings'])) * 100, 1) . '%</td>
            <td>' . number_format(($revenue_stats['no_show_bookings'] / max(1, $revenue_stats['total_bookings'])) * 100, 1) . '%</td>
          </tr>';
    echo '<tr><td colspan="6">&nbsp;</td></tr>';

    // สถิติบริการ
    echo '<tr><th colspan="6" style="background-color: #34495e; color: white;">สถิติบริการ</th></tr>';
    echo '<tr>
            <th>บริการ</th>
            <th>จำนวนการจอง</th>
            <th>รายได้รวม</th>
            <th>รายได้เฉลี่ย</th>
            <th>ลูกค้าไม่ซ้ำ</th>
            <th>อัตราการยกเลิก</th>
          </tr>';

    $service_sql = "SELECT mt.name, 
                           COUNT(b.booking_id) as booking_count, 
                           SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                           AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_revenue,
                           COUNT(DISTINCT b.customer_id) as unique_customers,
                           COUNT(CASE WHEN b.status = 'cancelled' THEN 1 ELSE NULL END) as cancelled_count
                    FROM bookings b
                    JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
                    LEFT JOIN payments p ON b.booking_id = p.booking_id
                    WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                    GROUP BY mt.massage_type_id, mt.name
                    ORDER BY total_revenue DESC";

    $service_result = $conn->query($service_sql);
    if ($service_result->num_rows > 0) {
        while ($row = $service_result->fetch_assoc()) {
            $cancellation_rate = $row['booking_count'] > 0 ? ($row['cancelled_count'] / $row['booking_count']) * 100 : 0;
            echo '<tr>
                    <td>' . $row['name'] . '</td>
                    <td>' . number_format($row['booking_count']) . '</td>
                    <td>฿' . number_format($row['total_revenue'], 2) . '</td>
                    <td>฿' . number_format($row['avg_revenue'], 2) . '</td>
                    <td>' . number_format($row['unique_customers']) . '</td>
                    <td>' . number_format($cancellation_rate, 1) . '%</td>
                  </tr>';
        }
    }
    echo '<tr><td colspan="6">&nbsp;</td></tr>';

    // สถิติพนักงาน
    echo '<tr><th colspan="7" style="background-color: #34495e; color: white;">สถิติพนักงานนวด</th></tr>';
    echo '<tr>
            <th>ชื่อพนักงาน</th>
            <th>จำนวนการจอง</th>
            <th>รายได้รวม</th>
            <th>รายได้เฉลี่ย</th>
            <th>ลูกค้าไม่ซ้ำ</th>
            <th>คะแนนรีวิว</th>
            <th>จำนวนรีวิว</th>
          </tr>';

    $therapist_sql = "SELECT t.therapist_id, t.full_name, 
                             COUNT(b.booking_id) as booking_count,
                             SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                             AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_revenue,
                             AVG(r.rating) as avg_rating,
                             COUNT(r.review_id) as review_count,
                             COUNT(DISTINCT b.customer_id) as unique_customers
                      FROM bookings b
                      JOIN therapists t ON b.therapist_id = t.therapist_id
                      LEFT JOIN payments p ON b.booking_id = p.booking_id
                      LEFT JOIN reviews r ON b.booking_id = r.booking_id
                      WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                      GROUP BY t.therapist_id, t.full_name
                      ORDER BY total_revenue DESC";

    $therapist_result = $conn->query($therapist_sql);
    if ($therapist_result->num_rows > 0) {
        while ($row = $therapist_result->fetch_assoc()) {
            echo '<tr>
                    <td>' . $row['full_name'] . '</td>
                    <td>' . number_format($row['booking_count']) . '</td>
                    <td>฿' . number_format($row['total_revenue'], 2) . '</td>
                    <td>฿' . number_format($row['avg_revenue'], 2) . '</td>
                    <td>' . number_format($row['unique_customers']) . '</td>
                    <td>' . ($row['avg_rating'] ? number_format($row['avg_rating'], 1) : 'N/A') . '</td>
                    <td>' . number_format($row['review_count']) . '</td>
                  </tr>';
        }
    }
    echo '<tr><td colspan="7">&nbsp;</td></tr>';

    // สถิติลูกค้า
    echo '<tr><th colspan="6" style="background-color: #34495e; color: white;">ลูกค้ายอดนิยม (Top 10)</th></tr>';
    echo '<tr>
            <th>ชื่อลูกค้า</th>
            <th>อีเมล</th>
            <th>เบอร์โทร</th>
            <th>จำนวนการจอง</th>
            <th>ยอดใช้จ่าย</th>
            <th>ค่าเฉลี่ยต่อครั้ง</th>
          </tr>';

    $customer_sql = "SELECT u.user_id, u.full_name, u.email, u.phone,
                            COUNT(b.booking_id) as booking_count,
                            SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_spent,
                            AVG(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE NULL END) as avg_spent,
                            MIN(b.booking_date) as first_booking,
                            MAX(b.booking_date) as last_booking
                     FROM users u
                     JOIN bookings b ON u.user_id = b.customer_id
                     LEFT JOIN payments p ON b.booking_id = p.booking_id
                     WHERE b.booking_date BETWEEN '$start_date' AND '$end_date'
                     GROUP BY u.user_id, u.full_name, u.email, u.phone
                     HAVING booking_count > 0
                     ORDER BY total_spent DESC
                     LIMIT 10";

    $customer_result = $conn->query($customer_sql);
    if ($customer_result->num_rows > 0) {
        while ($row = $customer_result->fetch_assoc()) {
            echo '<tr>
                    <td>' . $row['full_name'] . '</td>
                    <td>' . $row['email'] . '</td>
                    <td>' . $row['phone'] . '</td>
                    <td>' . number_format($row['booking_count']) . '</td>
                    <td>฿' . number_format($row['total_spent'], 2) . '</td>
                    <td>฿' . number_format($row['avg_spent'], 2) . '</td>
                  </tr>';
        }
    }

    echo '</table></body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรายงานและสถิติเชิงวิเคราะห์</title>
    <?php include '../templates/admin-head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow me-2"></i>ระบบรายงานและสถิติเชิงวิเคราะห์</h1>
                <p class="text-muted mb-0">วิเคราะห์ข้อมูลธุรกิจและสร้างรายงานเชิงลึก</p>
            </div>
            <div>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-2"></i>ส่งออกรายงาน Excel
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองข้อมูล</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">ช่วงวันที่เริ่มต้น</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ช่วงวันที่สิ้นสุด</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ประเภทรายงาน</label>
                        <select name="report_type" class="form-select">
                            <option value="overview" <?= $report_type == 'overview' ? 'selected' : '' ?>>ภาพรวมธุรกิจ</option>
                            <option value="services" <?= $report_type == 'services' ? 'selected' : '' ?>>วิเคราะห์บริการ</option>
                            <option value="therapists" <?= $report_type == 'therapists' ? 'selected' : '' ?>>วิเคราะห์พนักงาน</option>
                            <option value="customers" <?= $report_type == 'customers' ? 'selected' : '' ?>>วิเคราะห์ลูกค้า</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-bar-chart me-2"></i>สร้างรายงาน
                            </button>
                        </div>
                    </div>
                </form>
                <div class="mt-3">
                    <span class="badge bg-primary">ช่วงเวลาที่วิเคราะห์: <?= date('d/m/Y', strtotime($start_date)) ?> -
                        <?= date('d/m/Y', strtotime($end_date)) ?></span>
                    <span class="badge bg-secondary ms-2">จำนวนวัน:
                        <?= round((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1 ?> วัน</span>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">รายได้รวม</h6>
                                <h3 class="card-title mb-0">฿<?= number_format($revenue_stats['total_revenue'] ?: 0, 2) ?></h3>
                                <small class="opacity-75">รายได้เฉลี่ยต่อวัน: ฿<?= number_format(($revenue_stats['total_revenue'] ?: 0) / max(1, round((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1), 2) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">การจองทั้งหมด</h6>
                                <h3 class="card-title mb-0"><?= number_format($revenue_stats['total_bookings'] ?: 0) ?></h3>
                                <small class="opacity-75">สำเร็จ: <?= number_format($total_completed_bookings) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">ลูกค้าทั้งหมด</h6>
                                <h3 class="card-title mb-0"><?= number_format($revenue_stats['total_customers'] ?: 0) ?></h3>
                                <small class="opacity-75">ลูกค้าเก่า: <?= number_format($revenue_stats['total_customers'] ?: 0) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">มูลค่าเฉลี่ย</h6>
                                <h3 class="card-title mb-0">฿<?= number_format($revenue_stats['avg_revenue_per_booking'] ?: 0, 2) ?></h3>
                                <small class="opacity-75">ARPU: ฿<?= number_format(($revenue_stats['total_revenue'] ?: 0) / max(1, $revenue_stats['total_customers']), 2) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-dark bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 opacity-75">อัตราการยกเลิก</h6>
                                <h3 class="card-title mb-0"><?= number_format($cancellation_rate, 1) ?>%</h3>
                                <small class="opacity-75">ยกเลิก: <?= number_format($revenue_stats['cancelled_bookings']) ?> | ไม่มา: <?= number_format($revenue_stats['no_show_bookings']) ?></small>
                            </div>
                            <div class="fs-1">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>การวิเคราะห์แนวโน้มรายเดือน</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendChart" style="height: 300px;"></canvas>
            </div>
        </div>

        <!-- Analytics Tabs -->
        <div class="mb-4">
            <ul class="nav nav-tabs" id="analyticsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'overview' ? 'active' : '' ?>" id="overview-tab"
                        data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                        <i class="bi bi-house me-2"></i>ภาพรวมธุรกิจ
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'services' ? 'active' : '' ?>" id="services-tab"
                        data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
                        <i class="bi bi-list-check me-2"></i>วิเคราะห์บริการ
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'therapists' ? 'active' : '' ?>" id="therapists-tab"
                        data-bs-toggle="tab" data-bs-target="#therapists" type="button" role="tab">
                        <i class="bi bi-person-badge me-2"></i>วิเคราะห์พนักงาน
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $report_type == 'customers' ? 'active' : '' ?>" id="customers-tab"
                        data-bs-toggle="tab" data-bs-target="#customers" type="button" role="tab">
                        <i class="bi bi-people me-2"></i>วิเคราะห์ลูกค้า
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-3" id="analyticsTabContent">
                <!-- Overview Tab -->
                <div class="tab-pane fade <?= $report_type == 'overview' ? 'show active' : '' ?>" id="overview"
                    role="tabpanel">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">รายได้และการจองรายวัน</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" style="height: 350px;"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">วิธีการชำระเงิน</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="paymentMethodChart" style="height: 350px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Services Tab -->
                <div class="tab-pane fade <?= $report_type == 'services' ? 'show active' : '' ?>" id="services"
                    role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">สถิติบริการทั้งหมด</h5>
                            <span class="badge bg-light text-dark"><?= count($service_stats) ?> บริการ</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>อันดับ</th>
                                            <th>บริการ</th>
                                            <th>จำนวนการจอง</th>
                                            <th>รายได้รวม</th>
                                            <th>รายได้เฉลี่ย</th>
                                            <th>ลูกค้าไม่ซ้ำ</th>
                                            <th>อัตราการยกเลิก</th>
                                            <th>ส่วนแบ่งรายได้</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($service_stats)): ?>
                                            <?php
                                            $total_service_revenue = array_sum(array_column($service_stats, 'total_revenue'));
                                            ?>
                                            <?php foreach ($service_stats as $index => $service): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><strong><?= $service['name'] ?></strong></td>
                                                    <td><?= number_format($service['booking_count']) ?></td>
                                                    <td>฿<?= number_format($service['total_revenue'], 2) ?></td>
                                                    <td>฿<?= number_format($service['avg_revenue'], 2) ?></td>
                                                    <td><?= number_format($service['unique_customers']) ?></td>
                                                    <td>
                                                        <?php
                                                        $service_cancellation_rate = $service['booking_count'] > 0 ?
                                                            ($service['cancelled_count'] / $service['booking_count']) * 100 : 0;
                                                        ?>
                                                        <span
                                                            class="<?= $service_cancellation_rate > 10 ? 'text-danger' : 'text-success' ?>">
                                                            <?= number_format($service_cancellation_rate, 1) ?>%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $revenue_share = $total_service_revenue > 0 ?
                                                            ($service['total_revenue'] / $total_service_revenue) * 100 : 0;
                                                        ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-grow-1 me-3">
                                                                <div class="progress">
                                                                    <div class="progress-bar" role="progressbar"
                                                                        style="width: <?= $revenue_share ?>%;"
                                                                        aria-valuenow="<?= $revenue_share ?>" aria-valuemin="0"
                                                                        aria-valuemax="100"></div>
                                                                </div>
                                                            </div>
                                                            <div class="text-nowrap"><?= number_format($revenue_share, 1) ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-muted">
                                                    <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>
                                                    <p class="mb-0">ไม่มีข้อมูลบริการในระยะเวลาที่เลือก</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Therapists Tab -->
                <div class="tab-pane fade <?= $report_type == 'therapists' ? 'show active' : '' ?>" id="therapists"
                    role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">สถิติพนักงานนวด</h5>
                            <span class="badge bg-light text-dark"><?= count($therapist_stats) ?> คน</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>อันดับ</th>
                                            <th>ชื่อพนักงาน</th>
                                            <th>จำนวนการจอง</th>
                                            <th>รายได้รวม</th>
                                            <th>รายได้เฉลี่ย</th>
                                            <th>ลูกค้าไม่ซ้ำ</th>
                                            <th>คะแนนรีวิว</th>
                                            <th>จำนวนรีวิว</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($therapist_stats)): ?>
                                            <?php foreach ($therapist_stats as $index => $therapist): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><strong><?= $therapist['full_name'] ?></strong></td>
                                                    <td><?= number_format($therapist['booking_count']) ?></td>
                                                    <td>฿<?= number_format($therapist['total_revenue'], 2) ?></td>
                                                    <td>฿<?= number_format($therapist['avg_revenue'], 2) ?></td>
                                                    <td><?= number_format($therapist['unique_customers']) ?></td>
                                                    <td>
                                                        <?php if ($therapist['avg_rating']): ?>
                                                            <span class="text-warning">
                                                                <?= number_format($therapist['avg_rating'], 1) ?> ⭐
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">ไม่มีคะแนน</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= number_format($therapist['review_count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-muted">
                                                    <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
                                                    <p class="mb-0">ไม่มีข้อมูลพนักงานในระยะเวลาที่เลือก</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customers Tab -->
                <div class="tab-pane fade <?= $report_type == 'customers' ? 'show active' : '' ?>" id="customers"
                    role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">ลูกค้ายอดนิยม (Top 20)</h5>
                            <span class="badge bg-light text-dark"><?= count($customer_stats) ?> คน</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>อันดับ</th>
                                            <th>ชื่อลูกค้า</th>
                                            <th>อีเมล</th>
                                            <th>เบอร์โทร</th>
                                            <th>จำนวนการจอง</th>
                                            <th>ยอดใช้จ่าย</th>
                                            <th>ค่าเฉลี่ยต่อครั้ง</th>
                                            <th>การจองแรก</th>
                                            <th>การจองล่าสุด</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($customer_stats)): ?>
                                            <?php foreach ($customer_stats as $index => $customer): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><strong><?= $customer['full_name'] ?></strong></td>
                                                    <td><?= $customer['email'] ?></td>
                                                    <td><?= $customer['phone'] ?></td>
                                                    <td><?= number_format($customer['booking_count']) ?></td>
                                                    <td>฿<?= number_format($customer['total_spent'], 2) ?></td>
                                                    <td>฿<?= number_format($customer['avg_spent'], 2) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($customer['first_booking'])) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($customer['last_booking'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-5 text-muted">
                                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                                    <p class="mb-0">ไม่มีข้อมูลลูกค้าในระยะเวลาที่เลือก</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
        // กราฟรายได้รายวัน
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($daily_revenue as $revenue): ?>
                                            '<?= date('d/m', strtotime($revenue['date'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: [
                        <?php foreach ($daily_revenue as $revenue): ?>
                                                <?= $revenue['daily_revenue'] ?: 0 ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    tension: 0.3,
                    yAxisID: 'y'
                }, {
                    label: 'จำนวนการจอง',
                    data: [
                        <?php foreach ($daily_revenue as $revenue): ?>
                                                <?= $revenue['daily_bookings'] ?: 0 ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#f72585',
                    backgroundColor: 'rgba(247, 37, 133, 0.1)',
                    tension: 0.3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'รายได้ (บาท)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'จำนวนการจอง'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // กราฟวิธีการชำระเงิน
        const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($payment_stats as $payment): ?>
                                            '<?= $payment_method_names[$payment['payment_method']] ?? $payment['payment_method'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($payment_stats as $payment): ?>
                                                <?= $payment['total_amount'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4361ee',
                        '#4cc9f0',
                        '#4895ef',
                        '#560bad',
                        '#f72585'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ฿${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // กราฟแนวโน้มรายเดือน
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($monthly_trends as $trend): ?>
                                            '<?= date('M Y', strtotime($trend['month'] . '-01')) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: [
                        <?php foreach ($monthly_trends as $trend): ?>
                                                <?= $trend['monthly_revenue'] ?: 0 ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: '#4361ee',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'จำนวนการจอง',
                    data: [
                        <?php foreach ($monthly_trends as $trend): ?>
                                                <?= $trend['monthly_bookings'] ?: 0 ?>,
                        <?php endforeach; ?>
                    ],
                    type: 'line',
                    borderColor: '#f72585',
                    backgroundColor: 'rgba(247, 37, 133, 0.1)',
                    tension: 0.3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'รายได้ (บาท)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'จำนวนการจอง'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // ฟังก์ชันส่งออกรายงาน Excel
        function exportToExcel() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const reportType = document.querySelector('select[name="report_type"]').value;

            // แสดง loading
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังสร้างรายงาน...';
            btn.disabled = true;

            // เปิดหน้าต่างใหม่สำหรับดาวน์โหลด Excel
            const url = `reports.php?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}&export=excel`;
            window.location.href = url;

            // รีเซ็ตปุ่มหลังจาก 3 วินาที
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        }
    </script>
</body>

</html>