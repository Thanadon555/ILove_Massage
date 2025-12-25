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
$error = '';
$success = '';

// ดึงข้อมูลแจ้งเตือน
$notifications = [];
$sql = "SELECT n.*, b.booking_id 
        FROM notifications n 
        LEFT JOIN bookings b ON n.booking_id = b.booking_id
        WHERE n.user_id = '$user_id' 
        ORDER BY n.created_at DESC";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// อัพเดทสถานะการอ่าน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_as_read'])) {
    $notification_id = $conn->real_escape_string($_POST['notification_id']);

    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE notification_id = '$notification_id' AND user_id = '$user_id'";

    if ($conn->query($update_sql)) {
        $success = 'อัพเดทสถานะสำเร็จ';
        // โหลดข้อมูลใหม่
        $result = $conn->query($sql);
        $notifications = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
        }
    } else {
        $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
    }
}

// ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_all_read'])) {
    $update_sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = '$user_id' AND is_read = FALSE";

    if ($conn->query($update_sql)) {
        $success = 'ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว';
        // โหลดข้อมูลใหม่
        $result = $conn->query($sql);
        $notifications = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
        }
    } else {
        $error = 'เกิดข้อผิดพลาด: ' . $conn->error;
    }
}

// นับจำนวนแจ้งเตือนที่ยังไม่อ่าน
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การแจ้งเตือน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include 'templates\navbar-user.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="list-group">
                    <a href="profile.php" class="list-group-item list-group-item-action">ข้อมูลส่วนตัว</a>
                    <a href="booking_history.php" class="list-group-item list-group-item-action">ประวัติการจอง</a>
                    <a href="notifications.php" class="list-group-item list-group-item-action active">
                        การแจ้งเตือน
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger float-end"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <h2>การแจ้งเตือน</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <!-- สถิติแจ้งเตือน -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body text-center">
                                <h4><?= count($notifications) ?></h4>
                                <p>แจ้งเตือนทั้งหมด</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body text-center">
                                <h4><?= $unread_count ?></h4>
                                <p>ยังไม่อ่าน</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <h4><?= count($notifications) - $unread_count ?></h4>
                                <p>อ่านแล้ว</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ปุ่มจัดการ -->
                <?php if (!empty($notifications)): ?>
                    <div class="d-flex justify-content-between mb-3">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="mark_all_read" value="1">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-check-double"></i> ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว
                            </button>
                        </form>

                        <div>
                            <span class="text-muted">
                                แสดง <?= count($notifications) ?> รายการ
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- รายการแจ้งเตือน -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">ไม่มีแจ้งเตือน</h5>
                                <p class="text-muted">เมื่อมีอัพเดทใหม่เกี่ยวกับการจองของคุณ จะแสดงที่นี่</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <div
                                        class="list-group-item <?= !$notification['is_read'] ? 'list-group-item-warning' : '' ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= $notification['title'] ?></h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>

                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                <?php if ($notification['booking_id']): ?>
                                                    การจอง #<?= $notification['booking_id'] ?>
                                                <?php endif; ?>
                                            </small>

                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="mark_as_read" value="1">
                                                    <input type="hidden" name="notification_id"
                                                        value="<?= $notification['notification_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-check"></i> ทำเครื่องหมายว่าอ่านแล้ว
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check"></i> อ่านแล้ว
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ตัวอย่างแจ้งเตือนประเภทต่างๆ -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6>ประเภทการแจ้งเตือน</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-calendar-check text-success me-2"></i>
                                    <span>การยืนยันการจอง</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-clock text-warning me-2"></i>
                                    <span>การแจ้งเตือนล่วงหน้า</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                    <span>การเปลี่ยนแปลงการจอง</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>