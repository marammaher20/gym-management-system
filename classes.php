<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$success = '';
$days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];

$trainers = $pdo->query("SELECT trainer_id, full_name FROM Trainers ORDER BY full_name")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$data = [
    'class_name'   => '',
    'trainer_id'   => '',
    'schedule_day' => '',
    'start_time'   => '',
    'end_time'     => '',
    'capacity'     => '20',
];

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM Classes WHERE class_id = ?");
    $stmt->execute([$editId]);
    $class = $stmt->fetch();
    if ($class) {
        $data = [
            'class_name'   => $class['class_name'],
            'trainer_id'   => (string)($class['trainer_id'] ?? ''),
            'schedule_day' => $class['schedule_day'],
            'start_time'   => substr($class['start_time'], 0, 5),
            'end_time'     => substr($class['end_time'], 0, 5),
            'capacity'     => (string)$class['capacity'],
        ];
    } else {
        $editId = 0;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM Classes WHERE class_id = ?");
        $stmt->execute([$id]);
        header('Location: classes.php?deleted=1');
        exit;
    }
    if ($action === 'save') {
        $editId = (int)($_POST['class_id'] ?? 0);

        foreach ($data as $key => $value) {
            $data[$key] = trim($_POST[$key] ?? '');
        }
        if ($data['class_name'] === '') {
            $errors[] = 'اسم الحصة مطلوب';
        }

        if ($data['trainer_id'] !== '') {
            $stmt = $pdo->prepare("SELECT trainer_id FROM Trainers WHERE trainer_id = ?");
            $stmt->execute([(int)$data['trainer_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'المدرب غير موجود';
            }
        }

        if (!in_array($data['schedule_day'], $days)) {
            $errors[] = 'اختاري يوم الحصة';
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $data['start_time'])) {
            $errors[] = 'وقت البداية مطلوب';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $data['end_time'])) {
            $errors[] = 'وقت النهاية مطلوب';
        }
        if (empty($errors) && $data['end_time'] <= $data['start_time']) {
            $errors[] = 'وقت النهاية لازم يكون بعد وقت البداية';
        }

        if ($data['capacity'] === '' || !ctype_digit($data['capacity']) || (int)$data['capacity'] < 1) {
            $errors[] = 'السعة لازم تكون رقم صحيح (1 أو أكثر)';
        }

        if (empty($errors)) {
            $trainerId = $data['trainer_id'] !== '' ? (int)$data['trainer_id'] : null;

            if ($editId) {
                $stmt = $pdo->prepare(
                    "UPDATE Classes
                     SET class_name = ?, trainer_id = ?, schedule_day = ?,
                         start_time = ?, end_time = ?, capacity = ?
                     WHERE class_id = ?"
                );
                $stmt->execute([
                    $data['class_name'], $trainerId, $data['schedule_day'],
                    $data['start_time'], $data['end_time'], (int)$data['capacity'], $editId,
                ]);
                header('Location: classes.php?updated=1');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO Classes (class_name, trainer_id, schedule_day, start_time, end_time, capacity)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $data['class_name'], $trainerId, $data['schedule_day'],
                    $data['start_time'], $data['end_time'], (int)$data['capacity'],
                ]);
                header('Location: classes.php?added=1');
            }
            exit;
        }
    }
}

if (isset($_GET['added']))   $success = 'تمت إضافة الحصة ✅';
if (isset($_GET['updated'])) $success = 'تم تعديل الحصة ✅';
if (isset($_GET['deleted'])) $success = 'تم حذف الحصة ✅';

// ---------- جلب الحصص ----------
$classes = $pdo->query(
    "SELECT c.*, t.full_name AS trainer_name,
            (SELECT COUNT(*) FROM Class_members cm WHERE cm.class_id = c.class_id) AS enrolled
     FROM Classes c
     LEFT JOIN Trainers t ON t.trainer_id = c.trainer_id
     ORDER BY c.class_id DESC"
)->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<h3 class="mb-3">الحصص</h3>

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
    <div class="card-header"><?= $editId ? 'تعديل الحصة' : 'إضافة حصة جديدة' ?></div>
    <div class="card-body">
        <form method="POST" novalidate>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="class_id" value="<?= $editId ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">اسم الحصة *</label>
                    <input type="text" name="class_name" class="form-control"
                           placeholder="مثال: يوغا" value="<?= e($data['class_name']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">المدرب</label>
                    <select name="trainer_id" class="form-select">
                        <option value="">بدون مدرب</option>
                        <?php foreach ($trainers as $t): ?>
                            <option value="<?= $t['trainer_id'] ?>"
                                <?= $data['trainer_id'] == $t['trainer_id'] ? 'selected' : '' ?>>
                                <?= e($t['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">اليوم *</label>
                    <select name="schedule_day" class="form-select">
                        <option value="">اختاري...</option>
                        <?php foreach ($days as $d): ?>
                            <option value="<?= $d ?>" <?= $data['schedule_day'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">من *</label>
                    <input type="time" name="start_time" class="form-control" value="<?= e($data['start_time']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">إلى *</label>
                    <input type="time" name="end_time" class="form-control" value="<?= e($data['end_time']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">السعة (عدد الأعضاء) *</label>
                    <input type="number" min="1" name="capacity" class="form-control" value="<?= e($data['capacity']) ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><?= $editId ? 'حفظ التعديلات' : 'إضافة' ?></button>
            <?php if ($editId): ?>
                <a href="classes.php" class="btn btn-outline-secondary mt-3">إلغاء</a>
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
                    <th>الحصة</th>
                    <th>المدرب</th>
                    <th>اليوم</th>
                    <th>الوقت</th>
                    <th>المسجلين</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($classes) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">لا يوجد حصص</td></tr>
                <?php endif; ?>

                <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?= $c['class_id'] ?></td>
                    <td><?= e($c['class_name']) ?></td>
                    <td><?= e($c['trainer_name'] ?: 'بدون مدرب') ?></td>
                    <td><?= e($c['schedule_day']) ?></td>
                    <td><?= e(substr($c['start_time'], 0, 5)) ?> - <?= e(substr($c['end_time'], 0, 5)) ?></td>
                    <td>
                        <span class="badge <?= $c['enrolled'] >= $c['capacity'] ? 'bg-danger' : 'bg-success' ?>">
                            <?= $c['enrolled'] ?> / <?= $c['capacity'] ?>
                        </span>
                    </td>
                    <td>
                        <a href="Enrollclass.php?class_id=<?= $c['class_id'] ?>" class="btn btn-sm btn-info text-white" title="تسجيل أعضاء">
                            <i class="bi bi-person-plus"></i>
                        </a>
                        <a href="classes.php?edit=<?= $c['class_id'] ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('متأكدة من حذف هاي الحصة؟ رح تنحذف تسجيلات الأعضاء فيها كمان.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['class_id'] ?>">
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