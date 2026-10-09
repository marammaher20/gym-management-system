<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$data = [
    'subscription_id' => '',
    'amount'          => '',
    'payment_date'    => date('Y-m-d'),
    'payment_method'  => 'cash',
    'notes'           => '',
];
if (isset($_GET['subscription_id'])) {
    $data['subscription_id'] = (string)(int)$_GET['subscription_id'];
}

$subs = $pdo->query(
    "SELECT s.subscription_id, s.start_date, s.end_date, s.status,
            m.full_name, m.phone, p.plan_name, p.price,
            COALESCE((SELECT SUM(pay.amount) FROM Payments pay
                      WHERE pay.subscription_id = s.subscription_id), 0) AS paid
     FROM Subscriptions s
     JOIN Members m ON m.member_id = s.member_id
     JOIN Membership_plans p ON p.plan_id = s.plan_id
     WHERE s.status <> 'cancelled'
     ORDER BY s.subscription_id DESC"
)->fetchAll();

$subsById = [];
foreach ($subs as $s) {
    $subsById[$s['subscription_id']] = $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? '');
    }
    $sub = null;
    if ($data['subscription_id'] === '') {
        $errors[] = 'اختاري الاشتراك';
    } elseif (!isset($subsById[(int)$data['subscription_id']])) {
        $errors[] = 'الاشتراك غير موجود';
    } else {
        $sub = $subsById[(int)$data['subscription_id']];
    }

    if ($data['amount'] === '' || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        $errors[] = 'المبلغ لازم يكون رقم أكبر من صفر';
    } elseif ($sub) {
        $remaining = $sub['price'] - $sub['paid'];
        if ($data['amount'] > $remaining) {
            $errors[] = 'المبلغ أكبر من المتبقي (' . number_format(max($remaining, 0), 2) . ')';
        }
    }

    $dateOk = DateTime::createFromFormat('Y-m-d', $data['payment_date']);
    if (!$dateOk || $dateOk->format('Y-m-d') !== $data['payment_date']) {
        $errors[] = 'تاريخ الدفع غير صحيح';
    }

    if (!in_array($data['payment_method'], ['cash', 'card', 'transfer'])) {
        $data['payment_method'] = 'cash';
    }

    if (mb_strlen($data['notes']) > 255) {
        $errors[] = 'الملاحظات طويلة (الحد 255 حرف)';
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO Payments (subscription_id, amount, payment_date, payment_method, notes)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$data['subscription_id'],
            $data['amount'],
            $data['payment_date'],
            $data['payment_method'],
            $data['notes'] ?: null,
        ]);

        header('Location: payments.php?added=1');
        exit;
    }
}
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>إضافة دفعة</h3>
    <a href="payments.php" class="btn btn-outline-secondary">رجوع</a>
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
                <div class="col-12">
                    <label class="form-label">الاشتراك *</label>
                    <select name="subscription_id" class="form-select">
                        <option value="">اختاري الاشتراك...</option>
                        <?php foreach ($subs as $s): ?>
                            <?php $rest = max($s['price'] - $s['paid'], 0); ?>
                            <option value="<?= $s['subscription_id'] ?>"
                                <?= $data['subscription_id'] == $s['subscription_id'] ? 'selected' : '' ?>>
                                #<?= $s['subscription_id'] ?> - <?= e($s['full_name']) ?>
                                | <?= e($s['plan_name']) ?>
                                (<?= e($s['start_date']) ?> → <?= e($s['end_date']) ?>)
                                | السعر <?= number_format($s['price'], 2) ?>
                                | المدفوع <?= number_format($s['paid'], 2) ?>
                                | المتبقي <?= number_format($rest, 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">المبلغ *</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?= e($data['amount']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">تاريخ الدفع *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= e($data['payment_date']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">طريقة الدفع</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash" <?= $data['payment_method'] === 'cash' ? 'selected' : '' ?>>نقداً</option>
                        <option value="card" <?= $data['payment_method'] === 'card' ? 'selected' : '' ?>>بطاقة</option>
                        <option value="transfer" <?= $data['payment_method'] === 'transfer' ? 'selected' : '' ?>>تحويل</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">ملاحظات</label>
                    <input type="text" name="notes" maxlength="255" class="form-control" value="<?= e($data['notes']) ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">حفظ الدفعة</button>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>