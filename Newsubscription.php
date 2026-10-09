<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$data = [
    'member_id'      => '',
    'plan_id'        => '',
    'start_date'     => date('Y-m-d'),
    'amount'         => '',
    'payment_method' => 'cash',
];

// الأعضاء الفعّالين والخطط للقوائم المنسدلة
$members = $pdo->query(
    "SELECT member_id, full_name, phone FROM Members WHERE status = 'active' ORDER BY full_name"
)->fetchAll();
$plans = $pdo->query("SELECT * FROM Membership_plans ORDER BY plan_name")->fetchAll();

// لو جاي من صفحة ثانية بعضو محدد
if (isset($_GET['member_id'])) {
    $data['member_id'] = (string)(int)$_GET['member_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? '');
    }

    // ---------- Validation ----------
    $member = null;
    if ($data['member_id'] === '') {
        $errors[] = 'اختاري العضو';
    } else {
        $stmt = $pdo->prepare("SELECT member_id FROM Members WHERE member_id = ?");
        $stmt->execute([(int)$data['member_id']]);
        $member = $stmt->fetch();
        if (!$member) {
            $errors[] = 'العضو غير موجود';
        }
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

    // منع اشتراك فعّال مكرر لنفس العضو
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Subscriptions
             WHERE member_id = ? AND status = 'active' AND end_date >= CURDATE()"
        );
        $stmt->execute([(int)$data['member_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'هاد العضو عنده اشتراك فعّال حالياً. استخدمي صفحة التجديد بدل هيك.';
        }
    }

    // ---------- saveee----------
    if (empty($errors)) {
        $start = new DateTime($data['start_date']);
        $end = (clone $start)->modify('+' . (int)$plan['duration_days'] . ' days');

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO Subscriptions (member_id, plan_id, start_date, end_date, status)
                 VALUES (?, ?, ?, ?, 'active')"
            );
            $stmt->execute([
                (int)$data['member_id'],
                (int)$data['plan_id'],
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ]);
            $subId = $pdo->lastInsertId();

            if ($data['amount'] !== '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO Payments (subscription_id, amount, payment_date, payment_method, notes)
                     VALUES (?, ?, CURDATE(), ?, 'دفعة عند الاشتراك')"
                );
                $stmt->execute([$subId, $data['amount'], $data['payment_method']]);
            }

            $pdo->commit();
            header('Location: Activesubsecription.php?added=1');
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
    <h3>اشتراك جديد</h3>
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

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">العضو *</label>
                    <select name="member_id" class="form-select">
                        <option value="">اختاري العضو...</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['member_id'] ?>"
                                <?= $data['member_id'] == $m['member_id'] ? 'selected' : '' ?>>
                                <?= e($m['full_name']) ?> - <?= e($m['phone']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">الخطة *</label>
                    <select name="plan_id" class="form-select">
                        <option value="">اختاري الخطة...</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['plan_id'] ?>"
                                <?= $data['plan_id'] == $p['plan_id'] ? 'selected' : '' ?>>
                                <?= e($p['plan_name']) ?> - <?= $p['duration_days'] ?> يوم - <?= e($p['price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاريخ البداية *</label>
                    <input type="date" name="start_date" class="form-control" value="<?= e($data['start_date']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">المبلغ المدفوع الآن (اختياري)</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?= e($data['amount']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">طريقة الدفع</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash" <?= $data['payment_method'] === 'cash' ? 'selected' : '' ?>>نقداً</option>
                        <option value="card" <?= $data['payment_method'] === 'card' ? 'selected' : '' ?>>بطاقة</option>
                        <option value="transfer" <?= $data['payment_method'] === 'transfer' ? 'selected' : '' ?>>تحويل</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">حفظ الاشتراك</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>