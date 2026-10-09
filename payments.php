<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$search = trim($_GET['search'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$success = isset($_GET['added']) ? 'تمت إضافة الدفعة ✅' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM Payments WHERE payment_id = ?");
    $stmt->execute([$id]);
    header('Location: payments.php?deleted=1');
    exit;
}
if (isset($_GET['deleted'])) $success = 'تم حذف الدفعة ✅';

$sql = "FROM Payments pay
        JOIN Subscriptions s ON s.subscription_id = pay.subscription_id
        JOIN Members m ON m.member_id = s.member_id
        JOIN Membership_plans p ON p.plan_id = s.plan_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (m.full_name LIKE ? OR m.phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
if ($from !== '') {
    $sql .= " AND pay.payment_date >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $sql .= " AND pay.payment_date <= ?";
    $params[] = $to;
}

$stmt = $pdo->prepare("SELECT pay.*, m.full_name, m.phone, p.plan_name $sql ORDER BY pay.payment_date DESC, pay.payment_id DESC");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(pay.amount), 0) $sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$methods = ['cash' => 'نقداً', 'card' => 'بطاقة', 'transfer' => 'تحويل'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>المدفوعات</h3>
    <a href="Addpayments.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> إضافة دفعة
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-12 col-md-4">
        <input type="text" name="search" class="form-control"
               placeholder="ابحث باسم العضو أو الهاتف" value="<?= e($search) ?>">
    </div>
    <div class="col-6 col-md-2">
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>" title="من تاريخ">
    </div>
    <div class="col-6 col-md-2">
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>" title="إلى تاريخ">
    </div>
    <div class="col-6 col-md-2">
        <button class="btn btn-outline-secondary w-100">بحث</button>
    </div>
    <div class="col-6 col-md-2">
        <a href="payments.php" class="btn btn-outline-dark w-100">مسح</a>
    </div>
</form>

<div class="alert alert-info">
    إجمالي المبالغ: <strong><?= number_format($total, 2) ?></strong>
    (<?= count($payments) ?> دفعة)
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>العضو</th>
                    <th>الخطة</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                    <th>الطريقة</th>
                    <th>ملاحظات</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($payments) === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">لا يوجد دفعات</td></tr>
                <?php endif; ?>

                <?php foreach ($payments as $pm): ?>
                <tr>
                    <td><?= $pm['payment_id'] ?></td>
                    <td><?= e($pm['full_name']) ?><br><small class="text-muted"><?= e($pm['phone']) ?></small></td>
                    <td><?= e($pm['plan_name']) ?></td>
                    <td><?= number_format($pm['amount'], 2) ?></td>
                    <td><?= e($pm['payment_date']) ?></td>
                    <td><?= e($methods[$pm['payment_method']] ?? $pm['payment_method']) ?></td>
                    <td><?= e($pm['notes'] ?: '-') ?></td>
                    <td>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('متأكدة من حذف هاي الدفعة؟');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $pm['payment_id'] ?>">
                            <button class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>