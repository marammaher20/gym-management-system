<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT s.*, m.full_name, m.phone, p.plan_name,
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
          )";

$params = [];
if ($search !== '') {
    $sql .= " AND (m.full_name LIKE ? OR m.phone LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like];
}
$sql .= " ORDER BY s.end_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subs = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>الاشتراكات المنتهية</h3>
    <a href="Activesubsecription.php" class="btn btn-outline-secondary">الاشتراكات النشطة</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-8 col-md-5">
        <input type="text" name="search" class="form-control"
               placeholder="ابحث باسم العضو أو الهاتف" value="<?= e($search) ?>">
    </div>
    <div class="col-4 col-md-2">
        <button class="btn btn-outline-secondary w-100">بحث</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>العضو</th>
                    <th>الهاتف</th>
                    <th>الخطة</th>
                    <th>البداية</th>
                    <th>النهاية</th>
                    <th>منذ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($subs) === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">لا يوجد اشتراكات منتهية</td></tr>
                <?php endif; ?>

                <?php foreach ($subs as $s): ?>
                <tr>
                    <td><?= $s['subscription_id'] ?></td>
                    <td><?= e($s['full_name']) ?></td>
                    <td><?= e($s['phone']) ?></td>
                    <td><?= e($s['plan_name']) ?></td>
                    <td><?= e($s['start_date']) ?></td>
                    <td><?= e($s['end_date']) ?></td>
                    <td><span class="badge bg-danger"><?= $s['days_since'] ?> يوم</span></td>
                    <td>
                        <a href="Renewsubsecription.php?id=<?= $s['subscription_id'] ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-repeat"></i> تجديد
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>