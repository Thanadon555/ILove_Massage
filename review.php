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
$booking_id = isset($_GET['booking_id']) ? $conn->real_escape_string($_GET['booking_id']) : '';

$error = '';
$success = '';

// ตรวจสอบว่าการจองนี้เป็นของลูกค้าและสามารถให้คะแนนได้
if ($booking_id) {
    $check_sql = "SELECT b.*, t.therapist_id, t.full_name as therapist_name, 
                         mt.name as service_name, r.review_id, p.receipt_file
                  FROM bookings b
                  JOIN therapists t ON b.therapist_id = t.therapist_id
                  JOIN massage_types mt ON b.massage_type_id = mt.massage_type_id
                  LEFT JOIN payments p ON b.booking_id = p.booking_id
                  LEFT JOIN reviews r ON b.booking_id = r.booking_id
                  WHERE b.booking_id = '$booking_id' 
                  AND b.customer_id = '$user_id' 
                  AND b.status = 'completed'";

    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows == 0) {
        $error = 'ไม่พบข้อมูลการจองหรือไม่สามารถให้คะแนนได้';
        $booking = null;
    } else {
        $booking = $check_result->fetch_assoc();

        // ตรวจสอบว่ามีการให้คะแนนไปแล้วหรือยัง
        if ($booking['review_id']) {
            $error = 'คุณได้ให้คะแนนการจองนี้ไปแล้ว';
        }
        // ตรวจสอบว่ามีใบเสร็จรับเงินหรือไม่
        elseif (empty($booking['receipt_file'])) {
            $error = 'ยังไม่สามารถให้คะแนนได้ เนื่องจากยังไม่มีใบเสร็จรับเงิน';
        }
    }
} else {
    $error = 'ไม่พบรหัสการจอง';
    $booking = null;
}

// บันทึกการให้คะแนน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    if (!$booking) {
        $error = 'ไม่พบข้อมูลการจอง';
    } else if ($booking['review_id']) {
        $error = 'คุณได้ให้คะแนนการจองนี้ไปแล้ว';
    } else if (empty($booking['receipt_file'])) {
        $error = 'ยังไม่สามารถให้คะแนนได้ เนื่องจากยังไม่มีใบเสร็จรับเงิน';
    } else {
        $rating = $conn->real_escape_string($_POST['rating']);
        $comment = $conn->real_escape_string($_POST['comment']);

        // ตรวจสอบความถูกต้องของคะแนน
        if ($rating < 1 || $rating > 5) {
            $error = 'กรุณาให้คะแนนระหว่าง 1-5 ดาว';
        } else {
            // บันทึกการรีวิว
            $insert_sql = "INSERT INTO reviews (booking_id, customer_id, therapist_id, rating, comment, created_at)
                          VALUES ('$booking_id', '$user_id', '{$booking['therapist_id']}', '$rating', '$comment', NOW())";

            if ($conn->query($insert_sql)) {
                $success = 'ขอบคุณสำหรับการให้คะแนนและความคิดเห็น!';

                // อัพเดทข้อมูลเพื่อป้องกันการให้คะแนนซ้ำ
                $check_result = $conn->query($check_sql);
                $booking = $check_result->fetch_assoc();
            } else {
                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error;
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
    <title>ให้คะแนนบริการ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .rating-stars {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
        }

        .rating-stars .star {
            transition: color 0.2s;
        }

        .rating-stars .star:hover,
        .rating-stars .star.active {
            color: #ffc107;
        }

        .review-card {
            max-width: 600px;
            margin: 0 auto;
        }

        .service-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .receipt-status {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
        }

        .receipt-available {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .receipt-unavailable {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <?php include 'templates/navbar-user.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card review-card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-star me-2"></i>ให้คะแนนบริการ</h4>
                    </div>

                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                            </div>
                            <div class="text-center">
                                <a href="booking_history.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>กลับไปประวัติการจอง
                                </a>
                            </div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success text-center">
                                <h5><i class="fas fa-check-circle me-2"></i><?= $success ?></h5>
                                <p>ความคิดเห็นของคุณมีค่าสำหรับเรามาก</p>
                                <a href="booking_history.php" class="btn btn-success">
                                    <i class="fas fa-history me-1"></i>กลับไปประวัติการจอง
                                </a>
                            </div>
                        <?php elseif ($booking && !$booking['review_id'] && !empty($booking['receipt_file'])): ?>
                            <!-- แสดงข้อมูลการจอง -->
                            <div class="service-info mb-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="mb-0">ข้อมูลการจอง #<?= $booking['booking_id'] ?></h5>
                                    <span class="receipt-status receipt-available">
                                        <i class="fas fa-file-invoice me-2"></i>มีใบเสร็จรับเงิน
                                    </span>
                                </div>
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td width="120"><strong>บริการ:</strong></td>
                                        <td><?= $booking['service_name'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>หมอนวด:</strong></td>
                                        <td><?= $booking['therapist_name'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>วันที่:</strong></td>
                                        <td><?= date('d/m/Y', strtotime($booking['booking_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>เวลา:</strong></td>
                                        <td><?= substr($booking['start_time'], 0, 5) ?> -
                                            <?= substr($booking['end_time'], 0, 5) ?>
                                        </td>
                                    </tr>
                                </table>

                                <!-- ลิงก์ดูใบเสร็จรับเงิน -->
                                <div class="mt-3 text-center">
                                    <a href="uploads/receipts/<?= $booking['receipt_file'] ?>" target="_blank"
                                        class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-eye me-1"></i>ดูใบเสร็จรับเงิน
                                    </a>
                                </div>
                            </div>

                            <!-- ฟอร์มให้คะแนน -->
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-star me-1"></i>ให้คะแนนบริการ
                                    </label>
                                    <div class="rating-stars text-center mb-2" id="ratingStars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star" data-rating="<?= $i ?>">
                                                <i class="far fa-star"></i>
                                            </span>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="hidden" name="rating" id="ratingValue" value="0" required>
                                    <div class="text-center text-muted" id="ratingText">
                                        กรุณาเลือกจำนวนดาว
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-comment me-1"></i>ความคิดเห็น
                                    </label>
                                    <textarea name="comment" class="form-control" rows="5"
                                        placeholder="แบ่งปันประสบการณ์ในการใช้บริการของคุณ..."></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        ความคิดเห็นของคุณจะช่วยให้เราพัฒนาบริการได้ดียิ่งขึ้น
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" name="submit_review" class="btn btn-success btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>ส่งคำรีวิว
                                    </button>
                                    <a href="booking_history.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>ยกเลิก
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ระบบให้คะแนนด้วยดาว
        document.addEventListener('DOMContentLoaded', function () {
            const stars = document.querySelectorAll('.star');
            const ratingValue = document.getElementById('ratingValue');
            const ratingText = document.getElementById('ratingText');

            const ratingTexts = {
                0: 'กรุณาเลือกจำนวนดาว',
                1: 'แย่มาก',
                2: 'พอใช้',
                3: 'ดี',
                4: 'ดีมาก',
                5: 'ยอดเยี่ยม'
            };

            stars.forEach(star => {
                star.addEventListener('click', function () {
                    const rating = this.getAttribute('data-rating');
                    ratingValue.value = rating;

                    // อัพเดทการแสดงผลดาว
                    stars.forEach((s, index) => {
                        const starIcon = s.querySelector('i');
                        if (index < rating) {
                            starIcon.className = 'fas fa-star';
                            s.classList.add('active');
                        } else {
                            starIcon.className = 'far fa-star';
                            s.classList.remove('active');
                        }
                    });

                    // อัพเดทข้อความ
                    ratingText.textContent = ratingTexts[rating];
                    ratingText.className = 'text-center fw-bold text-warning';
                });

                // เอฟเฟกต์เมื่อเมาส์ hover
                star.addEventListener('mouseover', function () {
                    const rating = this.getAttribute('data-rating');
                    stars.forEach((s, index) => {
                        const starIcon = s.querySelector('i');
                        if (index < rating) {
                            starIcon.className = 'fas fa-star';
                        } else {
                            starIcon.className = 'far fa-star';
                        }
                    });
                });

                star.addEventListener('mouseout', function () {
                    const currentRating = ratingValue.value;
                    stars.forEach((s, index) => {
                        const starIcon = s.querySelector('i');
                        if (index < currentRating) {
                            starIcon.className = 'fas fa-star';
                        } else {
                            starIcon.className = 'far fa-star';
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>