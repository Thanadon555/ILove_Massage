<!-- Footer สำหรับ Admin -->
<footer class="bg-light text-dark mt-5 border-top">
    <div class="container-fluid py-3">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <span class="text-muted">
                    <i class="bi bi-c-circle me-1"></i>2025 ILove Massage Admin Panel
                </span>
                <span class="text-muted mx-2">|</span>
                <span class="badge bg-success">
                    <i class="bi bi-circle-fill me-1"></i>System Online
                </span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span class="text-muted small">
                    <i class="bi bi-person-badge me-1"></i>
                    Logged in as: <strong class="text-primary">
                        <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>
                    </strong>
                </span>
            </div>
        </div>
    </div>
</footer>

