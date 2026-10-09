<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT * FROM Members
         WHERE full_name LIKE ? OR phone LIKE ?
         ORDER BY member_id DESC"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM Members ORDER BY member_id DESC");
}
$members = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>الأعضاء</h3>
    <a href="Addmember.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> إضافة عضو
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-8 col-md-5">
        <input type="text" name="search" class="form-control"
               placeholder="ابحث بالاسم أو رقم الهاتف" value="<?= e($search) ?>">
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
                    <th>الحالة</th>
                    <th>تاريخ الانضمام</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($members) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">لا يوجد أعضاء</td></tr>
                <?php endif; ?>

                <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= $m['member_id'] ?></td>
                    <td>
                        <?php if ($m['photo']): ?>
                            <img src="uploads/members/<?= e($m['photo']) ?>" class="member-photo">
                        <?php else: ?>
                            <i class="bi bi-person-circle fs-3 text-secondary"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['full_name']) ?></td>
                    <td><?= e($m['phone']) ?></td>
                    <td>
                        <span class="badge <?= $m['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $m['status'] === 'active' ? 'فعّال' : 'غير فعّال' ?>
                        </span>
                    </td>
                    <td><?= e($m['join_date']) ?></td>
                    <td>
                        <a href="Editmember.php?id=<?= $m['member_id'] ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="deletemember.php" class="d-inline"
                              onsubmit="return confirm('متأكدة من حذف هاد العضو؟ رح تنحذف اشتراكاته كمان.');">
                            <input type="hidden" name="id" value="<?= $m['member_id'] ?>">
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