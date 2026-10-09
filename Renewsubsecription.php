<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.*, m.full_name, m.phone, p.plan_name
     FROM Subscriptions s
     JOIN Members m ON m.member_id = s.member_id
     JOIN Membership_plans p ON p.plan_id = s.plan_id
     WHERE s.subscription_id = ?"
);
$stmt->execute([$id]);
$old = $stmt->fetch();

if (!$old) {
    header('Location: Activesubsecription.php');
    exit;
}

$plans = $pdo->query("SELECT * FROM Membership_plans ORDER BY plan_name")->fetchAll();

$today = date('Y-m-d');
$defaultStart = ($old['end_date'] >= $today)
    ? date('Y-m-d', strtotime($old['end_date'] . ' +1 day'))
    : $today;

$errors = [];
$data = [
    'plan_id'        => (string)$old['plan_id'],
    'start_date'     => $defaultStart,
    'amount'         => '',
    'payment_method' => 'cash',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? '');
    }

    $plan = null;
    if ($data['plan_id'] === '') {
        $errors[] = 'اختاري الخطة';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM Membership_plans WHERE plan_id = ?");
        $stmt->execute([(int)$data['plan_id']]);
        $plan = $stmt->fetch();
        if (!$plan) {
            $errors[] = 'الخطة غير موجودة';
        }
    }

    $startOk = DateTime::createFromFormat('Y-m-d', $data['start_date']);
    if (!$startOk || $startOk->format('Y-m-d') !== $data['start_date']) {
        $errors[] = 'تاريخ البداية غير صحيح';
    }

    if ($data['amount'] !== '' && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
        $errors[] = 'المبلغ لازم يكون رقم أكبر من صفر';
    }

    if (!in_array($data['payment_method'], ['cash', 'card', 'transfer'])) {
        $data['payment_method'] = 'cash';
    }

    // ---------- الحفظ ----------
    if (empty($errors)) {
        $start = new DateTime($data['start_date']);
        $end = (clone $start)->modify('+' . (int)$plan['duration_days'] . ' days');

        try {
            $pdo->beginTransaction();

            // الاشتراك القديم يصير منتهي
            $stmt = $pdo->prepare("UPDATE Subscriptions SET status = 'expired' WHERE subscription_id = ?");
            $stmt->execute([$id]);

            // اشتراك جديد
            $stmt = $pdo->prepare(
                "INSERT INTO Subscriptions (member_id, plan_id, start_date, end_date, status)
                 VALUES (?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                $old['member_id'],
                (int)$data['plan_id'],
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ]);
            $newId = $pdo->lastInsertId();

            if ($data['amount'] !== '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO Payments (subscription_id, amount, payment_date, payment_method, notes)
                     VALUES (?, ?, CURDATE(), ?, 'دفعة تجديد')"
                );
                $stmt->execute([$newId, $data['amount'], $data['payment_method']]);
            }

            $pdo->commit();
            header('Location: Activesubsecription.php?renewed=1');
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'حصل خطأ أثناء الحفظ، حاولي مرة ثانية';
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>تجديد اشتراك</h3>
    <a href="Activesubsecription.php" class="btn btn-outline-secondary">رجوع</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">الاشتراك الحالي</div>
    <div class="card-body">
        <p class="mb-1"><strong>العضو:</strong> <?= e($old['full_name']) ?> (<?= e($old['phone']) ?>)</p>
        <p class="mb-1"><strong>الخطة:</strong> <?= e($old['plan_name']) ?></p>
        <p class="mb-0"><strong>الفترة:</strong> <?= e($old['start_date']) ?> إلى <?= e($old['end_date']) ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">بيانات التجديد</div>
    <div class="card-body">
        <form method="POST" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">الخطة *</label>
                    <select name="plan_id" class="form-select">
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['plan_id'] ?>"
                                <?= $data['plan_id'] == $p['plan_id'] ? 'selected' : '' ?>>
                                <?= e($p['plan_name']) ?> - <?= $p['duration_days'] ?> يوم - <?= e($p['price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاريخ البداية *</label>
                    <input type="date" name="start_date" class="form-control" value="<?= e($data['start_date']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">المبلغ المدفوع الآن (اختياري)</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?= e($data['amount']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">طريقة الدفع</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash" <?= $data['payment_method'] === 'cash' ? 'selected' : '' ?>>نقداً</option>
                        <option value="card" <?= $data['payment_method'] === 'card' ? 'selected' : '' ?>>بطاقة</option>
                        <option value="transfer" <?= $data['payment_method'] === 'transfer' ? 'selected' : '' ?>>تحويل</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-success mt-4">تأكيد التجديد</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>