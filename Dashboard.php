<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$totalMembers = $pdo->query("SELECT COUNT(*) FROM Members")->fetchColumn();
$totalTrainers = $pdo->query("SELECT COUNT(*) FROM Trainers")->fetchColumn();

$activeSubs = $pdo->query(
    "SELECT COUNT(*) FROM Subscriptions
     WHERE status = 'active' AND end_date >= CURDATE()"
)->fetchColumn();

$expiredSubs = $pdo->query(
    "SELECT COUNT(*) FROM Subscriptions s
     WHERE s.end_date < CURDATE()
       AND s.status <> 'cancelled'
       AND s.subscription_id = (
           SELECT MAX(s2.subscription_id) FROM Subscriptions s2
           WHERE s2.member_id = s.member_id
       )"
)->fetchColumn();

$monthRevenue = $pdo->query(
    "SELECT COALESCE(SUM(amount), 0) FROM Payments
     WHERE YEAR(payment_date) = YEAR(CURDATE())
       AND MONTH(payment_date) = MONTH(CURDATE())"
)->fetchColumn();

$expiringSoon = $pdo->query(
    "SELECT COUNT(*) FROM Subscriptions
     WHERE status = 'active'
       AND end_date >= CURDATE()
       AND end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
)->fetchColumn();

$unpaidCount = $pdo->query(
    "SELECT COUNT(*)
     FROM Subscriptions s
     JOIN Membership_plans p ON p.plan_id = s.plan_id
     WHERE s.status <> 'cancelled'
       AND COALESCE((SELECT SUM(pay.amount) FROM Payments pay
                     WHERE pay.subscription_id = s.subscription_id), 0) < p.price"
)->fetchColumn();

$latestMembers = $pdo->query(
    "SELECT full_name, phone, join_date FROM Members ORDER BY member_id DESC LIMIT 5"
)->fetchAll();

$latestPayments = $pdo->query(
    "SELECT pay.amount, pay.payment_date, m.full_name
     FROM Payments pay
     JOIN Subscriptions s ON s.subscription_id = pay.subscription_id
     JOIN Members m ON m.member_id = s.member_id
     ORDER BY pay.payment_id DESC LIMIT 5"
)->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<h3 class="mb-1">لوحة التحكم</h3>
<p class="text-muted">أهلاً، <?= e($_SESSION['full_name']) ?> 👋</p>

<!-- البطاقات -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-top border-primary border-4 h-100">
            <div class="card-body">
                <div class="text-muted small">الأعضاء</div>
                <div class="fs-2 fw-bold"><?= $totalMembers ?></div>
                <small class="text-muted"><?= $totalTrainers ?> مدرب</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-top border-success border-4 h-100">
            <div class="card-body">
                <div class="text-muted small">اشتراكات نشطة</div>
                <div class="fs-2 fw-bold"><?= $activeSubs ?></div>
                <a href="Activesubsecription.php" class="small">عرض</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-top border-danger border-4 h-100">
            <div class="card-body">
                <div class="text-muted small">اشتراكات منتهية</div>
                <div class="fs-2 fw-bold"><?= $expiredSubs ?></div>
                <a href="Expiredsubsecriptions.php" class="small">عرض</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border-0 border-top border-warning border-4 h-100">
            <div class="card-body">
                <div class="text-muted small">إيرادات هذا الشهر</div>
                <div class="fs-2 fw-bold"><?= number_format($monthRevenue, 2) ?></div>
                <a href="payments.php" class="small">المدفوعات</a>
            </div>
        </div>
    </div>
</div>

<!-- تنبيهات سريعة -->
<?php if ($expiringSoon > 0 || $unpaidCount > 0): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div>
        <i class="bi bi-bell"></i>
        <?php if ($expiringSoon > 0): ?>
            <strong><?= $expiringSoon ?></strong> اشتراك سينتهي خلال 7 أيام.
        <?php endif; ?>
        <?php if ($unpaidCount > 0): ?>
            <strong><?= $unpaidCount ?></strong> اشتراك فيه مبلغ غير مسدد.
        <?php endif; ?>
    </div>
    <a href="Notifications.php" class="btn btn-sm btn-dark">الإشعارات</a>
</div>
<?php endif; ?>

<!-- آخر الأعضاء والدفعات -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">آخر الأعضاء</div>
            <ul class="list-group list-group-flush">
                <?php if (count($latestMembers) === 0): ?>
                    <li class="list-group-item text-muted text-center">لا يوجد أعضاء</li>
                <?php endif; ?>
                <?php foreach ($latestMembers as $m): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($m['full_name']) ?> <small class="text-muted"><?= e($m['phone']) ?></small></span>
                        <small class="text-muted"><?= e($m['join_date']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">آخر الدفعات</div>
            <ul class="list-group list-group-flush">
                <?php if (count($latestPayments) === 0): ?>
                    <li class="list-group-item text-muted text-center">لا يوجد دفعات</li>
                <?php endif; ?>
                <?php foreach ($latestPayments as $p): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($p['full_name']) ?></span>
                        <span>
                            <strong><?= number_format($p['amount'], 2) ?></strong>
                            <small class="text-muted"><?= e($p['payment_date']) ?></small>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>