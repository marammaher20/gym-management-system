<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$success = '';
$editId = (int)($_GET['edit'] ?? 0);
$data = [
    'plan_name'     => '',
    'duration_days' => '',
    'price'         => '',
    'description'   => '',
];

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM Membership_plans WHERE plan_id = ?");
    $stmt->execute([$editId]);
    $plan = $stmt->fetch();
    if ($plan) {
        $data = [
            'plan_name'     => $plan['plan_name'],
            'duration_days' => $plan['duration_days'],
            'price'         => $plan['price'],
            'description'   => $plan['description'] ?? '',
        ];
    } else {
        $editId = 0;
    }
}

//using post...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Subscriptions WHERE plan_id = ?");
        $stmt->execute([$id]);

        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'ما بتقدري تحذفي هاي الخطة لأنه في اشتراكات مرتبطة فيها';
        } else {
            $stmt = $pdo->prepare("DELETE FROM Membership_plans WHERE plan_id = ?");
            $stmt->execute([$id]);
            header('Location: membershipplans.php?deleted=1');
            exit;
        }
    }

    if ($action === 'save') {
        $editId = (int)($_POST['plan_id'] ?? 0);

        foreach ($data as $key => $value) {
            $data[$key] = trim($_POST[$key] ?? '');
        }

        // Validation
        if ($data['plan_name'] === '') {
            $errors[] = 'اسم الخطة مطلوب';
        }

        if ($data['duration_days'] === '' || !ctype_digit($data['duration_days']) || (int)$data['duration_days'] < 1) {
            $errors[] = 'المدة لازم تكون عدد أيام صحيح (1 أو أكثر)';
        }

        if ($data['price'] === '' || !is_numeric($data['price']) || $data['price'] < 0) {
            $errors[] = 'السعر لازم يكون رقم موجب';
        }

        if (empty($errors)) {
            if ($editId) {
                $stmt = $pdo->prepare(
                    "UPDATE Membership_plans
                     SET plan_name = ?, duration_days = ?, price = ?, description = ?
                     WHERE plan_id = ?"
                );
                $stmt->execute([
                    $data['plan_name'],
                    (int)$data['duration_days'],
                    $data['price'],
                    $data['description'] ?: null,
                    $editId,
                ]);
                header('Location: membershipplans.php?updated=1');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO Membership_plans (plan_name, duration_days, price, description)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $data['plan_name'],
                    (int)$data['duration_days'],
                    $data['price'],
                    $data['description'] ?: null,
                ]);
                header('Location: membershipplans.php?added=1');
            }
            exit;
        }
    }
}

if (isset($_GET['added']))   $success = 'تمت إضافة الخطة ✅';
if (isset($_GET['updated'])) $success = 'تم تعديل الخطة ✅';
if (isset($_GET['deleted'])) $success = 'تم حذف الخطة ✅';

$plans = $pdo->query("SELECT * FROM Membership_plans ORDER BY plan_id DESC")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<h3 class="mb-3">خطط الاشتراك</h3>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- النموذج -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <?= $editId ? 'تعديل الخطة' : 'إضافة خطة جديدة' ?>
    </div>
    <div class="card-body">
        <form method="POST" novalidate>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="plan_id" value="<?= $editId ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">اسم الخطة *</label>
                    <input type="text" name="plan_name" class="form-control"
                           placeholder="مثال: شهري" value="<?= e($data['plan_name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">المدة (بالأيام) *</label>
                    <input type="number" min="1" name="duration_days" class="form-control"
                           placeholder="30" value="<?= e($data['duration_days']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">السعر *</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control"
                           value="<?= e($data['price']) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" rows="2" class="form-control"><?= e($data['description']) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">
                <?= $editId ? 'حفظ التعديلات' : 'إضافة' ?>
            </button>
            <?php if ($editId): ?>
                <a href="membershipplans.php" class="btn btn-outline-secondary mt-3">إلغاء</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- الجدول -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>الخطة</th>
                    <th>المدة (أيام)</th>
                    <th>السعر</th>
                    <th>الوصف</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($plans) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">لا يوجد خطط</td></tr>
                <?php endif; ?>

                <?php foreach ($plans as $p): ?>
                <tr>
                    <td><?= $p['plan_id'] ?></td>
                    <td><?= e($p['plan_name']) ?></td>
                    <td><?= $p['duration_days'] ?></td>
                    <td><?= e($p['price']) ?></td>
                    <td><?= e($p['description'] ?: '-') ?></td>
                    <td>
                        <a href="membershipplans.php?edit=<?= $p['plan_id'] ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('متأكدة من حذف هاي الخطة؟');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['plan_id'] ?>">
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