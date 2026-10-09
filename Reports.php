<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// ---------- السنة المختارة ----------
$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// السنوات المتاحة (من الدفعات) + السنة الحالية
$yearsRows = $pdo->query("SELECT DISTINCT YEAR(payment_date) AS y FROM Payments ORDER BY y DESC")->fetchAll();
$years = array_map(fn($r) => (int)$r['y'], $yearsRows);
if (!in_array((int)date('Y'), $years)) {
    $years[] = (int)date('Y');
    rsort($years);
}
if (!in_array($year, $years)) {
    $years[] = $year;
    rsort($years);
}

//الايرادات 
$stmt = $pdo->prepare(
    "SELECT MONTH(payment_date) AS m, SUM(amount) AS total, COUNT(*) AS cnt
     FROM Payments
     WHERE YEAR(payment_date) = ?
     GROUP BY MONTH(payment_date)"
);
$stmt->execute([$year]);
$monthly = array_fill(1, 12, ['total' => 0, 'cnt' => 0]);
foreach ($stmt->fetchAll() as $r) {
    $monthly[(int)$r['m']] = ['total' => (float)$r['total'], 'cnt' => (int)$r['cnt']];
}
$monthNames = [1 => 'كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران',
               'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'];
$yearTotal = array_sum(array_column($monthly, 'total'));
$maxMonth = max(array_column($monthly, 'total')) ?: 1;

// ---------- 2) الاشتراكات   ----------
$byPlan = $pdo->query(
    "SELECT p.plan_name, p.price,
            COUNT(s.subscription_id) AS subs_count,
            COALESCE(SUM(pay_sum.paid), 0) AS revenue
     FROM Membership_plans p
     LEFT JOIN Subscriptions s ON s.plan_id = p.plan_id AND s.status <> 'cancelled'
     LEFT JOIN (
         SELECT subscription_id, SUM(amount) AS paid
         FROM Payments GROUP BY subscription_id
     ) pay_sum ON pay_sum.subscription_id = s.subscription_id
     GROUP BY p.plan_id, p.plan_name, p.price
     ORDER BY subs_count DESC"
)->fetchAll();

// ---------- 3) أكثر الحصص تسجيلاً ----------
$topClasses = $pdo->query(
    "SELECT c.class_name, c.capacity, t.full_name AS trainer_name,
            COUNT(cm.id) AS enrolled
     FROM Classes c
     LEFT JOIN Trainers t ON t.trainer_id = c.trainer_id
     LEFT JOIN Class_members cm ON cm.class_id = c.class_id
     GROUP BY c.class_id, c.class_name, c.capacity, t.full_name
     ORDER BY enrolled DESC
     LIMIT 10"
)->fetchAll();

// ---------- 4) ملخص الأعضاء ----------
$memberStats = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'active')   AS active_count,
            SUM(status = 'inactive') AS inactive_count,
            SUM(gender = 'male')     AS male_count,
            SUM(gender = 'female')   AS female_count
     FROM Members"
)->fetch();
$mTotal = (int)$memberStats['total'];
$pct = fn($n) => $mTotal > 0 ? round(((int)$n / $mTotal) * 100) : 0;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">التقارير والإحصاءات</h3>
    <form method="GET" class="d-flex gap-2">
        <select name="year" class="form-select" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-outline-secondary">عرض</button></noscript>
    </form>
</div>

<!-- 1) الإيرادات الشهرية -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between">
        <span>الإيرادات الشهرية - <?= $year ?></span>
        <strong>الإجمالي: <?= number_format($yearTotal, 2) ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>الشهر</th>
                    <th>عدد الدفعات</th>
                    <th>المبلغ</th>
                    <th style="width:40%">النسبة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly as $m => $row): ?>
                <tr>
                    <td><?= e($monthNames[$m]) ?></td>
                    <td><?= $row['cnt'] ?></td>
                    <td><?= number_format($row['total'], 2) ?></td>
                    <td>
                        <div class="progress" style="height:14px;">
                            <div class="progress-bar bg-success" style="width: <?= round(($row['total'] / $maxMonth) * 100) ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- 2) حسب الخطة -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">الاشتراكات حسب الخطة</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>الخطة</th>
                            <th>السعر</th>
                            <th>عدد الاشتراكات</th>
                            <th>المدفوع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($byPlan) === 0): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">لا يوجد خطط</td></tr>
                        <?php endif; ?>
                        <?php foreach ($byPlan as $p): ?>
                        <tr>
                            <td><?= e($p['plan_name']) ?></td>
                            <td><?= number_format($p['price'], 2) ?></td>
                            <td><span class="badge bg-primary"><?= $p['subs_count'] ?></span></td>
                            <td><?= number_format($p['revenue'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 4) ملخص الأعضاء -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">ملخص الأعضاء (<?= $mTotal ?>)</div>
            <div class="card-body">
                <?php
                $bars = [
                    ['فعّال',      $memberStats['active_count'],   'bg-success'],
                    ['غير فعّال',  $memberStats['inactive_count'], 'bg-secondary'],
                    ['ذكور',       $memberStats['male_count'],     'bg-primary'],
                    ['إناث',       $memberStats['female_count'],   'bg-danger'],
                ];
                foreach ($bars as [$label, $count, $color]): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span><?= $label ?></span>
                            <span><?= (int)$count ?> (<?= $pct($count) ?>%)</span>
                        </div>
                        <div class="progress" style="height:12px;">
                            <div class="progress-bar <?= $color ?>" style="width: <?= $pct($count) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3) أكثر الحصص تسجيلاً -->
<div class="card shadow-sm">
    <div class="card-header">أكثر الحصص تسجيلاً</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>الحصة</th>
                    <th>المدرب</th>
                    <th>المسجلين</th>
                    <th style="width:35%">نسبة الامتلاء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($topClasses) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">لا يوجد حصص</td></tr>
                <?php endif; ?>
                <?php foreach ($topClasses as $c): ?>
                    <?php $fill = $c['capacity'] > 0 ? round(($c['enrolled'] / $c['capacity']) * 100) : 0; ?>
                <tr>
                    <td><?= e($c['class_name']) ?></td>
                    <td><?= e($c['trainer_name'] ?: 'بدون مدرب') ?></td>
                    <td><?= $c['enrolled'] ?> / <?= $c['capacity'] ?></td>
                    <td>
                        <div class="progress" style="height:14px;">
                            <div class="progress-bar <?= $fill >= 100 ? 'bg-danger' : 'bg-info' ?>" style="width: <?= min($fill, 100) ?>%">
                                <?= $fill ?>%
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>