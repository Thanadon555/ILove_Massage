<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-spa me-1"></i><span class="d-none d-md-inline">I Love Massage</span><span class="d-md-none">ILM</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_bookings.php' ? 'active' : '' ?>" href="manage_bookings.php" title="จัดการการจอง">
                        <i class="bi bi-calendar-check"></i>
                        <span class="nav-text">การจอง</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_schedule.php' ? 'active' : '' ?>" href="manage_schedule.php" title="จัดการตารางเวลา">
                        <i class="bi bi-clock"></i>
                        <span class="nav-text">ตารางเวลา</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_therapists.php' ? 'active' : '' ?>" href="manage_therapists.php" title="จัดการนักบำบัด">
                        <i class="bi bi-person-badge"></i>
                        <span class="nav-text">นักบำบัด</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_services.php' ? 'active' : '' ?>" href="manage_services.php" title="จัดการบริการ">
                        <i class="bi bi-spa"></i>
                        <!-- <i class="bi bi-heart-pulse"></i> -->
                        <i class="bi bi-flower1"></i>
                        <span class="nav-text">บริการ</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_payments.php' ? 'active' : '' ?>" href="manage_payments.php" title="จัดการการชำระเงิน">
                        <i class="bi bi-credit-card"></i>
                        <!-- <i class="bi bi-cash-coin"></i>
                        <i class="bi bi-wallet2"></i> -->
                        <span class="nav-text">ชำระเงิน</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : '' ?>" href="manage_users.php" title="จัดการผู้ใช้">
                        <i class="bi bi-people"></i>
                        <span class="nav-text">ผู้ใช้</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage_holidays.php' ? 'active' : '' ?>" href="manage_holidays.php" title="จัดการวันหยุด">
                        <i class="bi bi-calendar-x"></i>
                        <span class="nav-text">วันหยุด</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>" href="contact.php" title="ติดต่อเรา">
                        <i class="bi bi-telephone"></i>
                        <span class="nav-text">ติดต่อ</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'review.php' ? 'active' : '' ?>" href="review.php" title="รีวิวลูกค้า">
                        <i class="bi bi-star-fill"></i>
                        <!-- <i class="bi bi-chat-heart"></i> -->
                        <span class="nav-text">รีวิว</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php" title="รายงาน">
                        <i class="bi bi-bar-chart"></i>
                        <span class="nav-text">รายงาน</span>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                            <span class="d-none d-lg-inline ms-1"><?= htmlspecialchars($_SESSION['full_name'] ?? 'ผู้ดูแลระบบ') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person-circle me-2"></i>โปรไฟล์
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="index.php">
                                   <i class="bi bi-house-door me-2"></i>หน้าเว็บไซต์
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span class="d-none d-lg-inline ms-1">เข้าสู่ระบบ</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>