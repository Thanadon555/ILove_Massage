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

// ตั้งค่าโฟลเดอร์สำหรับเก็บใบเสร็จรับเงิน
$receipt_dir = '../uploads/receipts/';
if (!is_dir($receipt_dir)) {
    mkdir($receipt_dir, 0755, true);
}

$error = '';
$success = '';

// ฟังก์ชันสำหรับอัพโหลดใบเสร็จรับเงิน
function uploadReceipt($file, $receipt_dir, $existing_receipt = null)
{
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('เกิดข้อผิดพลาดในการอัพโหลดไฟล์: ' . $file['error']);
    }

    // ตรวจสอบประเภทไฟล์
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPEG, JPG, PNG, GIF, WebP, PDF');
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > $max_size) {
        throw new Exception('ขนาดไฟล์ใหญ่เกินไป อนุญาตสูงสุด 5MB');
    }

    // สร้างชื่อไฟล์ใหม่
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $receipt_dir . $new_filename;

    // ย้ายไฟล์ไปยังโฟลเดอร์ปลายทาง
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
    }

    // ลบไฟล์เก่าหากมีการอัพโหลดไฟล์ใหม่และมีไฟล์เก่าอยู่
    if ($existing_receipt && file_exists($receipt_dir . $existing_receipt)) {
        unlink($receipt_dir . $existing_receipt);
    }

    return $new_filename;
}

// ดึงข้อมูลการชำระเงิน
$payment_id = isset($_GET['payment_id']) ? $conn->real_escape_string($_GET['payment_id']) : '';

if (!$payment_id) {
    header('Location: manage_payments.php');
    exit();
}

// ดึงข้อมูลการชำระเงินและข้อมูลที่เกี่ยวข้อง - แก้ไข query โดยลบการ join กับ system_settings
$sql = "SELECT p.*, b.booking_id, b.total_price, b.status as booking_status, 
               b.booking_date, b.start_time, b.end_time, b.notes as booking_notes,
               u.full_name as customer_name, u.phone as customer_phone, u.email as customer_email,
               mt.name as service_name, mt.duration_minutes,
               t.full_name as therapist_name
        FROM payments p
        JOIN bookings b ON p.booking_id = b.booking_id
        JOIN users u ON b.customer_id = u.user_id
        JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
        JOIN therapists t ON b.therapist_id = t.therapist_id
        WHERE p.payment_id = '$payment_id'";

$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header('Location: manage_payments.php');
    exit();
}

$payment = $result->fetch_assoc();

// จัดกลุ่มข้อมูลร้านจาก system_settings
$shop_settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('shop_name', 'shop_phone', 'shop_address')";
$settings_result = $conn->query($settings_sql);
while ($row = $settings_result->fetch_assoc()) {
    $shop_settings[$row['setting_key']] = $row['setting_value'];
}

// ตั้งค่าเริ่มต้นหากไม่พบข้อมูล
$shop_name = $shop_settings['shop_name'] ?? 'I Love Massage';
$shop_phone = $shop_settings['shop_phone'] ?? '082-6843254';
$shop_address = $shop_settings['shop_address'] ?? 'ช้างเผือก 30 ซอย สุขเกษม 1 ตำบลช้างเผือก เมือง เชียงใหม่ 50200';

// อัพโหลดใบเสร็จรับเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_receipt'])) {
    try {
        // ตรวจสอบว่าสถานะการชำระเงินเป็น completed
        if ($payment['payment_status'] == 'completed') {
            $current_receipt = $payment['receipt_file'] ?? null;

            if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
                $receipt_filename = uploadReceipt($_FILES['receipt_file'], $receipt_dir, $current_receipt);

                $update_receipt_sql = "UPDATE payments SET receipt_file = '$receipt_filename' WHERE payment_id = '$payment_id'";

                if ($conn->query($update_receipt_sql)) {
                    $success = 'อัพโหลดใบเสร็จรับเงินสำเร็จ';
                    // โหลดข้อมูลใหม่
                    $result = $conn->query($sql);
                    $payment = $result->fetch_assoc();
                } else {
                    throw new Exception('เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error);
                }
            } else {
                throw new Exception('กรุณาเลือกไฟล์ใบเสร็จรับเงิน');
            }
        } else {
            throw new Exception('สามารถแนบใบเสร็จรับเงินได้เฉพาะการชำระเงินที่เสร็จสมบูรณ์แล้วเท่านั้น');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ลบใบเสร็จรับเงิน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_receipt'])) {
    try {
        // ดึงข้อมูลใบเสร็จรับเงิน
        $receipt_sql = "SELECT receipt_file FROM payments WHERE payment_id = '$payment_id'";
        $receipt_result = $conn->query($receipt_sql);

        if ($receipt_result->num_rows > 0) {
            $receipt_data = $receipt_result->fetch_assoc();
            $receipt_file = $receipt_data['receipt_file'];

            // ลบไฟล์ใบเสร็จรับเงิน
            if ($receipt_file && file_exists($receipt_dir . $receipt_file)) {
                unlink($receipt_dir . $receipt_file);
            }

            // อัพเดทฐานข้อมูล
            $delete_receipt_sql = "UPDATE payments SET receipt_file = NULL WHERE payment_id = '$payment_id'";

            if ($conn->query($delete_receipt_sql)) {
                $success = 'ลบใบเสร็จรับเงินสำเร็จ';
                // โหลดข้อมูลใหม่
                $result = $conn->query($sql);
                $payment = $result->fetch_assoc();
            } else {
                throw new Exception('เกิดข้อผิดพลาดในการลบข้อมูล: ' . $conn->error);
            }
        } else {
            throw new Exception('ไม่พบข้อมูลการชำระเงิน');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// สร้างเลขที่ใบเสร็จรับเงิน
$receipt_number = 'RCP' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างใบเสร็จรับเงิน</title>
    <?php include '../templates/admin-head.php'; ?>
    <style>
        /* Receipt-specific styles */
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .receipt-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 8px 8px 0 0;
        }

        .receipt-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .receipt-number {
            font-size: 1rem;
            opacity: 0.9;
        }

        .section-title {
            font-weight: 600;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
        }

        .info-value {
            color: #212529;
        }

        .total-amount {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .stamp-area {
            text-align: center;
            margin: 2rem 0;
        }

        .stamp {
            display: inline-block;
            padding: 1rem 2rem;
            border: 3px double #28a745;
            color: #28a745;
            font-weight: 700;
            font-size: 1.2rem;
            transform: rotate(-5deg);
        }

        .receipt-footer {
            background: #f8f9fa;
            padding: 1.5rem;
            text-align: center;
            border-radius: 0 0 8px 8px;
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .receipt-container {
                max-width: 100%;
                box-shadow: none;
            }
            
            .receipt-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-receipt me-2"></i>ใบเสร็จรับเงิน</h1>
                <p class="text-muted mb-0">ระบบสร้างและจัดการใบเสร็จรับเงิน</p>
            </div>
            <a href="manage_payments.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>กลับ
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Receipt Card -->
        <div class="receipt-container">
            <div class="card shadow">
                <!-- Receipt Header -->
                <div class="receipt-header text-center">
                    <div class="receipt-title">ใบเสร็จรับเงิน</div>
                    <div class="receipt-number">เลขที่: <?= $receipt_number ?></div>
                    <div class="mt-3">
                        <div class="fs-5 fw-bold"><?= $shop_name ?></div>
                        <div>โทร: <?= $shop_phone ?></div>
                        <div><?= $shop_address ?></div>
                    </div>
                </div>

                    <!-- Receipt Body -->
                    <div class="card-body p-4">
                        <!-- ข้อมูลลูกค้า -->
                        <div class="mb-4">
                            <div class="section-title">ข้อมูลลูกค้า</div>
                            <div class="info-row">
                                <div class="info-label">ชื่อ-นามสกุล:</div>
                                <div class="info-value"><?= $payment['customer_name'] ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">เบอร์โทรศัพท์:</div>
                                <div class="info-value"><?= $payment['customer_phone'] ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">อีเมล:</div>
                                <div class="info-value"><?= $payment['customer_email'] ?></div>
                            </div>
                        </div>

                        <!-- ข้อมูลการจอง -->
                        <div class="mb-4">
                            <div class="section-title">ข้อมูลการจอง</div>
                            <div class="info-row">
                                <div class="info-label">รหัสการจอง:</div>
                                <div class="info-value">#<?= $payment['booking_id'] ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">บริการ:</div>
                                <div class="info-value"><?= $payment['service_name'] ?>
                                    (<?= $payment['duration_minutes'] ?> นาที)</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">หมอนวด:</div>
                                <div class="info-value"><?= $payment['therapist_name'] ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">วันที่:</div>
                                <div class="info-value"><?= date('d/m/Y', strtotime($payment['booking_date'])) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">เวลา:</div>
                                <div class="info-value"><?= date('H:i', strtotime($payment['start_time'])) ?> -
                                    <?= date('H:i', strtotime($payment['end_time'])) ?>
                                </div>
                            </div>
                            <?php if ($payment['booking_notes']): ?>
                                <div class="info-row">
                                    <div class="info-label">หมายเหตุ:</div>
                                    <div class="info-value"><?= $payment['booking_notes'] ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Items -->
                        <div>
                            <div class="section-title">รายการเงิน</div>
                            <div class="table-responsive">
                                <table class="table table-borderless">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>รายการ</th>
                                            <th class="text-end">จำนวนเงิน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ค่าบริการนวด <?= $payment['service_name'] ?></td>
                                            <td class="text-end">฿<?= number_format($payment['amount'], 2) ?></td>
                                        </tr>
                                        <tr class="border-top border-2">
                                            <td class="text-end fw-bold">รวมทั้งสิ้น</td>
                                            <td class="text-end total-amount p-2">
                                                ฿<?= number_format($payment['amount'], 2) ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ข้อมูลการชำระเงิน -->
                        <div class="mt-4">
                            <div class="section-title">ข้อมูลการชำระเงิน</div>
                            <div class="info-row">
                                <div class="info-label">วิธีการชำระเงิน:</div>
                                <div class="info-value">
                                    <?php
                                    $payment_methods = [
                                        'cash' => 'เงินสด',
                                        'credit_card' => 'บัตรเครดิต',
                                        'debit_card' => 'บัตรเดบิต',
                                        'promptpay' => 'พร้อมเพย์',
                                        'bank_transfer' => 'โอนเงิน'
                                    ];
                                    echo $payment_methods[$payment['payment_method']] ?? $payment['payment_method'];
                                    ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">วันที่ชำระเงิน:</div>
                                <div class="info-value">
                                    <?= $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : 'ยังไม่ชำระ' ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">สถานะ:</div>
                                <div class="info-value">
                                    <span class="badge bg-success">ชำระเงินเรียบร้อย</span>
                                </div>
                            </div>
                        </div>

                        <!-- พื้นที่สำหรับลายเซ็น -->
                        <div class="stamp-area">
                            <div class="stamp">
                                ใบเสร็จรับเงิน
                                <br>
                                <small><?= date('d/m/Y') ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- ท้ายใบเสร็จ -->
                    <div class="receipt-footer">
                        <p>ขอบคุณที่ใช้บริการ <?= $shop_name ?></p>
                        <p>ใบเสร็จรับเงินนี้เป็นหลักฐานการชำระเงินที่ถูกต้อง</p>
                        <p>สอบถามข้อมูลเพิ่มเติม โทร: <?= $shop_phone ?></p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row g-4 mt-3 no-print">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>จัดการใบเสร็จรับเงิน</h5>
                    </div>
                                <div class="card-body">
                                    <?php if ($payment['receipt_file']): ?>
                                        <div class="mb-3">
                                            <p class="fw-bold">ใบเสร็จรับเงินปัจจุบัน:</p>
                                            <?php
                                            $is_receipt_pdf = pathinfo($payment['receipt_file'], PATHINFO_EXTENSION) === 'pdf';
                                            ?>
                                            <?php if ($is_receipt_pdf): ?>
                                                <a href="<?= $receipt_dir . $payment['receipt_file'] ?>" target="_blank"
                                                    class="btn btn-primary mb-2 w-100">
                                                    <i class="bi bi-file-pdf"></i> ดูใบเสร็จรับเงิน (PDF)
                                                </a>
                                            <?php else: ?>
                                                <img src="<?= $receipt_dir . $payment['receipt_file'] ?>" alt="ใบเสร็จรับเงิน"
                                                    class="img-fluid rounded mb-2" style="max-height: 200px;">
                                                <br>
                                                <a href="<?= $receipt_dir . $payment['receipt_file'] ?>" target="_blank"
                                                    class="btn btn-primary w-100">
                                                    <i class="bi bi-eye"></i> ดูใบเสร็จรับเงิน
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                        <form method="POST" enctype="multipart/form-data" class="mb-3 needs-validation" novalidate>
                                            <input type="hidden" name="upload_receipt" value="1">
                                            <div class="mb-3">
                                                <label class="form-label">อัพโหลดใบเสร็จรับเงินใหม่ <span class="text-danger">*</span></label>
                                                <input type="file" name="receipt_file" class="form-control"
                                                    accept="image/*,.pdf" required>
                                                <div class="form-text">อนุญาตไฟล์ JPEG, JPG, PNG, GIF, WebP, PDF
                                                    ขนาดไม่เกิน 5MB</div>
                                                <div class="invalid-feedback">กรุณาเลือกไฟล์ใบเสร็จรับเงิน</div>
                                            </div>
                                            <button type="submit" class="btn btn-warning w-100">
                                                <i class="bi bi-arrow-repeat"></i> อัพเดทใบเสร็จรับเงิน
                                            </button>
                                        </form>

                                        <form method="POST"
                                            onsubmit="return confirm('คุณแน่ใจว่าต้องการลบใบเสร็จรับเงินนี้?')">
                                            <input type="hidden" name="delete_receipt" value="1">
                                            <button type="submit" class="btn btn-danger w-100">
                                                <i class="bi bi-trash"></i> ลบใบเสร็จรับเงิน
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-muted">ยังไม่มีใบเสร็จรับเงินที่แนบไว้</p>
                                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                            <input type="hidden" name="upload_receipt" value="1">
                                            <div class="mb-3">
                                                <label class="form-label">แนบใบเสร็จรับเงิน <span class="text-danger">*</span></label>
                                                <input type="file" name="receipt_file" class="form-control"
                                                    accept="image/*,.pdf" required>
                                                <div class="form-text">อนุญาตไฟล์ JPEG, JPG, PNG, GIF, WebP, PDF
                                                    ขนาดไม่เกิน 5MB</div>
                                                <div class="invalid-feedback">กรุณาเลือกไฟล์ใบเสร็จรับเงิน</div>
                                            </div>
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-upload"></i> อัพโหลดใบเสร็จรับเงิน
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-gear me-2"></i>การดำเนินการ</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="window.print()" class="btn btn-primary mb-2 w-100">
                            <i class="bi bi-printer me-1"></i>พิมพ์ใบเสร็จรับเงิน
                        </button>

                        <a href="manage_payments.php" class="btn btn-secondary mb-2 w-100">
                            <i class="bi bi-arrow-left me-1"></i>กลับไปจัดการการชำระเงิน
                        </a>

                        <?php if ($payment['receipt_file']): ?>
                        <a href="<?= $receipt_dir . $payment['receipt_file'] ?>"
                            download="ใบเสร็จรับเงิน_<?= $receipt_number ?>.<?= pathinfo($payment['receipt_file'], PATHINFO_EXTENSION) ?>"
                            class="btn btn-success mb-2 w-100">
                            <i class="bi bi-download me-1"></i>ดาวน์โหลดใบเสร็จรับเงิน
                        </a>
                        <?php endif; ?>

                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <strong><i class="bi bi-info-circle me-1"></i>หมายเหตุ:</strong><br>
                                - ใบเสร็จรับเงินนี้สามารถพิมพ์ได้โดยคลิกปุ่ม "พิมพ์ใบเสร็จรับเงิน"<br>
                                - สามารถแนบไฟล์ใบเสร็จรับเงินสแกนเพื่อส่งให้ลูกค้าได้<br>
                                - ใบเสร็จรับเงินจะแสดงเมื่อการชำระเงินเสร็จสมบูรณ์
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
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