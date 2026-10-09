<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$classId = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT c.*, t.full_name AS trainer_name
     FROM Classes c
     LEFT JOIN Trainers t ON t.trainer_id = c.trainer_id
     WHERE c.class_id = ?"
);
$stmt->execute([$classId]);
$class = $stmt->fetch();

if (!$class) {
    header('Location: classes.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM Class_members WHERE class_id = ? AND member_id = ?");
        $stmt->execute([$classId, $memberId]);
        header('Location: Enrollclass.php?class_id=' . $classId . '&removed=1');
        exit;
    }
    if ($action === 'enroll') {
        $memberId = (int)($_POST['member_id'] ?? 0);

        if ($memberId === 0) {
            $errors[] = 'اختاري العضو';
        } else {
            // السعة
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Class_members WHERE class_id = ?");
            $stmt->execute([$classId]);
            if ($stmt->fetchColumn() >= $class['capacity']) {
                $errors[] = 'الحصة ممتلئة';
            }

            // اشتراك ساري
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Subscriptions
                 WHERE member_id = ? AND status = 'active' AND end_date >= CURDATE()"
            );
            $stmt->execute([$memberId]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'هاد العضو ما عنده اشتراك ساري';
            }

            // مسجل مسبقاً
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Class_members WHERE class_id = ? AND member_id = ?");
            $stmt->execute([$classId, $memberId]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'العضو مسجل بهاي الحصة مسبقاً';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO Class_members (class_id, member_id) VALUES (?, ?)");
            $stmt->execute([$classId, $memberId]);
            header('Location: Enrollclass.php?class_id=' . $classId . '&enrolled=1');
            exit;
        }
    }
}

if (isset($_GET['enrolled'])) $success = 'تم تسجيل العضو بالحصة ✅';
if (isset($_GET['removed']))  $success = 'تم إلغاء التسجيل ✅';

// ---------- الأعضاء المسجلين ----------
$stmt = $pdo->prepare(
    "SELECT m.member_id, m.full_name, m.phone, cm.enrolled_at
     FROM Class_members cm
     JOIN Members m ON m.member_id = cm.member_id
     WHERE cm.class_id = ?
     ORDER BY cm.enrolled_at DESC"
);
$stmt->execute([$classId]);
$enrolledList = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT DISTINCT m.member_id, m.full_name, m.phone
     FROM Members m
     JOIN Subscriptions s ON s.member_id = m.member_id
     WHERE m.status = 'active'
       AND s.status = 'active'
       AND s.end_date >= CURDATE()
       AND m.member_id NOT IN (
           SELECT member_id FROM Class_members WHERE class_id = ?
       )
     ORDER BY m.full_name"
);
$stmt->execute([$classId]);
$available = $stmt->fetchAll();

$count = count($enrolledList);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>تسجيل أعضاء بالحصة</h3>
    <a href="classes.php" class="btn btn-outline-secondary">رجوع</a>
</div>

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

<!-- بيانات الحصة -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5><?= e($class['class_name']) ?></h5>
        <p class="mb-1"><i class="bi bi-person-badge"></i> المدرب: <?= e($class['trainer_name'] ?: 'بدون مدرب') ?></p>
        <p class="mb-1"><i class="bi bi-calendar-event"></i> <?= e($class['schedule_day']) ?>،
            <?= e(substr($class['start_time'], 0, 5)) ?> - <?= e(substr($class['end_time'], 0, 5)) ?></p>
        <p class="mb-0"><i class="bi bi-people"></i> المسجلين:
            <span class="badge <?= $count >= $class['capacity'] ? 'bg-danger' : 'bg-success' ?>">
                <?= $count ?> / <?= $class['capacity'] ?>
            </span>
        </p>
    </div>
</div>

<!-- نموذج التسجيل -->
<div class="card shadow-sm mb-4">
    <div class="card-header">تسجيل عضو</div>
    <div class="card-body">
        <?php if ($count >= $class['capacity']): ?>
            <div class="text-muted">الحصة ممتلئة.</div>
        <?php elseif (count($available) === 0): ?>
            <div class="text-muted">لا يوجد أعضاء متاحين (لازم يكون للعضو اشتراك ساري وغير مسجل بالحصة).</div>
        <?php else: ?>
            <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="enroll">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <div class="col-md-8">
                    <select name="member_id" class="form-select">
                        <option value="">اختاري العضو...</option>
                        <?php foreach ($available as $m): ?>
                            <option value="<?= $m['member_id'] ?>">
                                <?= e($m['full_name']) ?> - <?= e($m['phone']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">تسجيل</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">الأعضاء المسجلين</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>الهاتف</th>
                    <th>تاريخ التسجيل</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($count === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد أعضاء مسجلين</td></tr>
                <?php endif; ?>

                <?php foreach ($enrolledList as $i => $m): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($m['full_name']) ?></td>
                    <td><?= e($m['phone']) ?></td>
                    <td><?= e(substr($m['enrolled_at'], 0, 10)) ?></td>
                    <td>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('إلغاء تسجيل هاد العضو؟');">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="class_id" value="<?= $classId ?>">
                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                            <button class="btn btn-sm btn-danger">
                                <i class="bi bi-x-lg"></i> إلغاء
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