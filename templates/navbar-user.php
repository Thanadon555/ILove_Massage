<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <!-- Brand/Logo -->
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="fas fa-spa me-2"></i>I Love Massage
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Items -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- แสดง Navbar Items เมื่อล็อกอินแล้ว -->
                <ul class="navbar-nav me-auto">
                    <!-- หน้าแรก -->
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"
                            href="index.php">
                            <i class="fas fa-home me-1"></i>หน้าแรก
                        </a>
                    </li>

                    <!-- จองคิว -->
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'booking.php' ? 'active' : '' ?>"
                            href="booking.php">
                            <i class="fas fa-calendar-plus me-1"></i>จองคิว
                        </a>
                    </li>

                    <!-- ประวัติการจอง -->
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'booking_history.php' ? 'active' : '' ?>"
                            href="booking_history.php">
                            <i class="fas fa-history me-1"></i>ประวัติการจอง
                        </a>
                    </li>

                    <!-- ติดต่อเรา -->
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>"
                            href="contact.php">
                            <i class="fas fa-phone me-1"></i>ติดต่อเรา
                        </a>
                    </li>
                </ul>
            <?php endif; ?>

            <!-- Right Side - User Section -->
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- การแจ้งเตือน -->
                    <!-- <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell me-1"></i>
                            <?php
                            // นับจำนวนการแจ้งเตือนที่ยังไม่ได้อ่าน
                            $unread_count = 0;
                            if (isset($_SESSION['user_id'])) {
                                $user_id = $_SESSION['user_id'];
                                $notification_sql = "SELECT COUNT(*) as unread_count 
                                                   FROM notifications 
                                                   WHERE user_id = '$user_id' AND is_read = FALSE";
                                $notification_result = $conn->query($notification_sql);
                                if ($notification_result->num_rows > 0) {
                                    $unread_count = $notification_result->fetch_assoc()['unread_count'];
                                }
                            }
                            ?>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $unread_count ?>
                                </span>
                            <?php endif; ?>
                        </a> -->
                    <!-- <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li>
                                <h6 class="dropdown-header">การแจ้งเตือน</h6>
                            </li>
                            <?php if ($unread_count > 0): ?>
                                <li><a class="dropdown-item" href="notifications.php">มี <?= $unread_count ?>
                                        การแจ้งเตือนใหม่</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item text-muted" href="notifications.php">ไม่มีแจ้งเตือนใหม่</a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="notifications.php"><i
                                        class="fas fa-list me-2"></i>ดูทั้งหมด</a></li>
                        </ul> -->
                    </li>

                    <!-- ผู้ใช้ -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i
                                        class="fas fa-user-circle me-2"></i>โปรไฟล์</a></li>
                            <!-- <li><a class="dropdown-item" href="booking_history.php"><i
                                        class="fas fa-history me-2"></i>ประวัติการจอง</a></li> -->
                            <!-- <li><a class="dropdown-item" href="review.php"><i class="fas fa-star me-2"></i>รีวิวของฉัน</a>
                            </li> -->
                            <!-- <li><a class="dropdown-item" href="notifications.php"><i
                                        class="fas fa-bell me-2"></i>การแจ้งเตือน</a></li>
                            <li> -->
                            <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a>
                    </li>
                </ul>
                </li>
            <?php else: ?>
                <!-- ไม่ได้ล็อกอิน - แสดงเฉพาะปุ่มเข้าสู่ระบบและสมัครสมาชิก -->
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : '' ?>"
                        href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>เข้าสู่ระบบ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : '' ?>"
                        href="register.php">
                        <i class="fas fa-user-plus me-1"></i>สมัครสมาชิก
                    </a>
                </li>
            <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<style>
    .navbar {
        box-shadow: 0 2px 15px rgba(79, 195, 161, 0.1);
        background: linear-gradient(135deg, #2d5a5a 0%, #234e52 100%) !important;
        padding: 15px 0;
    }

    .navbar-brand {
        font-size: 1.5rem;
        color: #ffffff !important;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .navbar-brand:hover {
        color: #68d391 !important;
        transform: translateY(-1px);
    }

    .navbar-brand i {
        color: #68d391;
    }

    .nav-link {
        font-weight: 500;
        transition: all 0.3s ease;
        color: #e6f7f3 !important;
        padding: 8px 15px !important;
        border-radius: 8px;
        margin: 0 2px;
    }

    .nav-link:hover {
        color: #ffffff !important;
        background: rgba(104, 211, 145, 0.2);
        transform: translateY(-1px);
    }

    .nav-link.active {
        color: #ffffff !important;
        background: linear-gradient(135deg, #68d391, #48bb78);
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(104, 211, 145, 0.3);
    }

    .navbar-toggler {
        border: 2px solid #68d391;
        padding: 6px 10px;
    }

    .navbar-toggler:focus {
        box-shadow: 0 0 0 3px rgba(104, 211, 145, 0.3);
    }

    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28104, 211, 145, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    .dropdown-menu {
        border: none;
        box-shadow: 0 8px 25px rgba(79, 195, 161, 0.15);
        border-radius: 10px;
        background: #ffffff;
        border: 1px solid #e6f7f3;
        padding: 10px 0;
    }

    .dropdown-item {
        padding: 10px 20px;
        transition: all 0.3s ease;
        color: #2d5a5a;
        font-weight: 500;
    }

    .dropdown-item:hover {
        background: linear-gradient(135deg, #f0f9f7, #e6f7f3);
        color: #4fc3a1;
        transform: translateX(5px);
    }

    .dropdown-item i {
        color: #4fc3a1;
        width: 20px;
        text-align: center;
    }

    .dropdown-header {
        color: #2d5a5a;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 10px 20px;
    }

    .dropdown-divider {
        border-color: #e6f7f3;
        margin: 8px 0;
    }

    .badge {
        font-size: 0.6rem;
        padding: 0.25em 0.4em;
        background: linear-gradient(45deg, #fc8181, #f56565) !important;
    }

    .position-relative .badge {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    /* User dropdown specific styles */
    .nav-link.dropdown-toggle {
        background: rgba(104, 211, 145, 0.1);
        border: 1px solid rgba(104, 211, 145, 0.3);
    }

    .nav-link.dropdown-toggle:hover {
        background: rgba(104, 211, 145, 0.2);
        border-color: rgba(104, 211, 145, 0.5);
    }

    /* Notification icon */
    .nav-link.position-relative {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 5px;
    }

    .nav-link.position-relative:hover {
        background: rgba(104, 211, 145, 0.2);
    }

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
        .navbar-nav {
            text-align: center;
            padding: 10px 0;
        }

        .nav-item {
            margin: 5px 0;
        }

        .nav-link {
            padding: 12px 20px !important;
            margin: 2px 0;
        }

        .dropdown-menu {
            text-align: center;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .navbar-collapse {
            background: linear-gradient(135deg, #2d5a5a 0%, #234e52 100%);
            border-radius: 10px;
            margin-top: 10px;
            padding: 15px;
        }

        .nav-link.dropdown-toggle::after {
            margin-left: 8px;
        }
    }

    @media (max-width: 576px) {
        .navbar-brand {
            font-size: 1.3rem;
        }

        .nav-link {
            padding: 10px 15px !important;
        }

        .dropdown-menu {
            width: 100%;
        }
    }

    /* Smooth transitions */
    .navbar-collapse {
        transition: all 0.3s ease;
    }

    /* Focus states for accessibility */
    .nav-link:focus,
    .dropdown-item:focus,
    .navbar-toggler:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(104, 211, 145, 0.3);
    }

    /* Login/Register specific styles */
    .nav-link[href*="login"],
    .nav-link[href*="register"] {
        background: rgba(104, 211, 145, 0.2);
        border: 1px solid rgba(104, 211, 145, 0.3);
    }

    .nav-link[href*="login"]:hover,
    .nav-link[href*="register"]:hover {
        background: rgba(104, 211, 145, 0.3);
        border-color: rgba(104, 211, 145, 0.5);
    }

    /* Icon spacing */
    .nav-link i,
    .dropdown-item i {
        margin-right: 8px;
    }
</style>