<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM Trainers
         WHERE full_name LIKE ? OR phone LIKE ? OR specialization LIKE ?
         ORDER BY trainer_id DESC"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM Trainers ORDER BY trainer_id DESC");
}
$trainers = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>المدربين</h3>
    <a href="Addtrainer.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> إضافة مدرب
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-8 col-md-5">
        <input type="text" name="search" class="form-control"
               placeholder="ابحث بالاسم أو الهاتف أو التخصص" value="<?= e($search) ?>">
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
                    <th>الصورة</th>
                    <th>الاسم</th>
                    <th>الهاتف</th>
                    <th>التخصص</th>
                    <th>تاريخ التعيين</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($trainers) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">لا يوجد مدربين</td></tr>
                <?php endif; ?>

                <?php foreach ($trainers as $t): ?>
                <tr>
                    <td><?= $t['trainer_id'] ?></td>
                    <td>
                        <?php if ($t['photo']): ?>
                            <img src="uploads/trainers/<?= e($t['photo']) ?>" class="member-photo">
                        <?php else: ?>
                            <i class="bi bi-person-circle fs-3 text-secondary"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= e($t['full_name']) ?></td>
                    <td><?= e($t['phone']) ?></td>
                    <td><?= e($t['specialization']) ?></td>
                    <td><?= e($t['hire_date']) ?></td>
                    <td>
                        <a href="Trainerdetails.php?id=<?= $t['trainer_id'] ?>" class="btn btn-sm btn-info text-white">
                            <i class="bi bi-eye"></i>
                        </a>
                        <form method="POST" action="deletetrainer.php" class="d-inline"
                              onsubmit="return confirm('متأكدة من حذف هاد المدرب؟');">
                            <input type="hidden" name="id" value="<?= $t['trainer_id'] ?>">
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