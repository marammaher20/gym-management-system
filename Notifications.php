<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$days = (int)($_GET['days'] ?? 7);
if ($days < 1 || $days > 60) {
    $days = 7;
}

$stmt = $pdo->prepare(
    "SELECT s.subscription_id, m.full_name, m.phone, p.plan_name, s.end_date,
            DATEDIFF(s.end_date, CURDATE()) AS days_left
     FROM Subscriptions s
     JOIN Members m ON m.member_id = s.member_id
     JOIN Membership_plans p ON p.plan_id = s.plan_id
     WHERE s.status = 'active'
       AND s.end_date >= CURDATE()
       AND s.end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
     ORDER BY s.end_date ASC"
);
$stmt->execute([$days]);
$expiring = $stmt->fetchAll();

$expired = $pdo->query(
    "SELECT s.subscription_id, m.full_name, m.phone, p.plan_name, s.end_date,
            DATEDIFF(CURDATE(), s.end_date) AS days_since
     FROM Subscriptions s
     JOIN Members m ON m.member_id = s.member_id
     JOIN Membership_plans p ON p.plan_id = s.plan_id
     WHERE s.end_date < CURDATE()
       AND s.status <> 'cancelled'
       AND s.subscription_id = (
           SELECT MAX(s2.subscription_id)
           FROM Subscriptions s2
           WHERE s2.member_id = s.member_id
       )
     ORDER BY s.end_date DESC"
)->fetchAll();

$unpaid = $pdo->query(
    "SELECT s.subscription_id, m.full_name, m.phone, p.plan_name, p.price,
            COALESCE((SELECT SUM(pay.amount) FROM Payments pay
                      WHERE pay.subscription_id = s.subscription_id), 0) AS paid
     FROM Subscriptions s
     JOIN Members m ON m.member_id = s.member_id
     JOIN Membership_plans p ON p.plan_id = s.plan_id
     WHERE s.status <> 'cancelled'
     HAVING paid < p.price
     ORDER BY s.subscription_id DESC"
)->fetchAll();

$totalAlerts = count($expiring) + count($expired) + count($unpaid);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>الإشعارات
        <span class="badge bg-danger"><?= $totalAlerts ?></span>
    </h3>
</div>

<!-- 1) قاربت تنتهي -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hourglass-split text-warning"></i>
            اشتراكات ستنتهي خلال <?= $days ?> يوم (<?= count($expiring) ?>)</span>
        <form method="GET" class="d-flex gap-2">
            <input type="number" name="days" min="1" max="60" value="<?= $days ?>"
                   class="form-control form-control-sm" style="width:80px;">
            <button class="btn btn-sm btn-outline-secondary">تحديث</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>العضو</th>
                    <th>الهاتف</th>
                    <th>الخطة</th>
                    <th>تاريخ الانتهاء</th>
                    <th>المتبقي</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($expiring) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">لا يوجد اشتراكات قاربت على الانتهاء</td></tr>
                <?php endif; ?>
                <?php foreach ($expiring as $r): ?>
                <tr>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['phone']) ?></td>
                    <td><?= e($r['plan_name']) ?></td>
                    <td><?= e($r['end_date']) ?></td>
                    <td><span class="badge bg-warning text-dark"><?= $r['days_left'] ?> يوم</span></td>
                    <td>
                        <a href="Renewsubsecription.php?id=<?= $r['subscription_id'] ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-repeat"></i> تجديد
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 2) انتهت -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <i class="bi bi-x-circle text-danger"></i>
        اشتراكات منتهية (<?= count($expired) ?>)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>العضو</th>
                    <th>الهاتف</th>
                    <th>الخطة</th>
                    <th>انتهى بتاريخ</th>
                    <th>منذ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($expired) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">لا يوجد اشتراكات منتهية</td></tr>
                <?php endif; ?>
                <?php foreach ($expired as $r): ?>
                <tr>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['phone']) ?></td>
                    <td><?= e($r['plan_name']) ?></td>
                    <td><?= e($r['end_date']) ?></td>
                    <td><span class="badge bg-danger"><?= $r['days_since'] ?> يوم</span></td>
                    <td>
                        <a href="Renewsubsecription.php?id=<?= $r['subscription_id'] ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-repeat"></i> تجديد
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 3) دفعات متبقية -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="bi bi-cash-coin text-primary"></i>
        مبالغ غير مسددة (<?= count($unpaid) ?>)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>العضو</th>
                    <th>الهاتف</th>
                    <th>الخطة</th>
                    <th>السعر</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($unpaid) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">كل الاشتراكات مسددة</td></tr>
                <?php endif; ?>
                <?php foreach ($unpaid as $r): ?>
                <tr>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['phone']) ?></td>
                    <td><?= e($r['plan_name']) ?></td>
                    <td><?= number_format($r['price'], 2) ?></td>
                    <td><?= number_format($r['paid'], 2) ?></td>
                    <td><span class="badge bg-danger"><?= number_format($r['price'] - $r['paid'], 2) ?></span></td>
                    <td>
                        <a href="Addpayments.php?subscription_id=<?= $r['subscription_id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg"></i> دفعة
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>