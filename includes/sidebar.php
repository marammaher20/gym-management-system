<?php $current = basename($_SERVER['PHP_SELF']); ?>
<div class="mobile-topbar d-md-none bg-dark text-white d-flex align-items-center p-2">
    <button class="btn btn-outline-light btn-sm" id="sidebarToggle" type="button">
        <i class="bi bi-list fs-5"></i>
    </button>
    <span class="me-2">Gym System</span>
</div>

<div class="d-flex">
    <nav class="sidebar bg-dark text-white p-3" id="sidebar">
        <h5 class="text-center mb-4">
            <i class="bi bi-activity"></i> Gym System
        </h5>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Dashboard.php' ? 'active' : '' ?>" href="Dashboard.php">
                    <i class="bi bi-speedometer2"></i> لوحة التحكم
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Members.php' ? 'active' : '' ?>" href="Members.php">
                    <i class="bi bi-people"></i> الأعضاء
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Trainer.php' ? 'active' : '' ?>" href="Trainer.php">
                    <i class="bi bi-person-badge"></i> المدربين
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'membershipplans.php' ? 'active' : '' ?>" href="membershipplans.php">
                    <i class="bi bi-card-list"></i> خطط الاشتراك
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Activesubsecription.php' ? 'active' : '' ?>" href="Activesubsecription.php">
                    <i class="bi bi-check-circle"></i> الاشتراكات النشطة
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Expiredsubsecriptions.php' ? 'active' : '' ?>" href="Expiredsubsecriptions.php">
                    <i class="bi bi-x-circle"></i> الاشتراكات المنتهية
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'classes.php' ? 'active' : '' ?>" href="classes.php">
                    <i class="bi bi-calendar-event"></i> الحصص
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'payments.php' ? 'active' : '' ?>" href="payments.php">
                    <i class="bi bi-cash-coin"></i> المدفوعات
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Notifications.php' ? 'active' : '' ?>" href="Notifications.php">
                    <i class="bi bi-bell"></i> الإشعارات
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current == 'Reports.php' ? 'active' : '' ?>" href="Reports.php">
                    <i class="bi bi-bar-chart"></i> التقارير
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                </a>
            </li>
        </ul>
    </nav>

    <main class="flex-grow-1 p-4">
    