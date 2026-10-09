<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM Trainers WHERE trainer_id = ?");
$stmt->execute([$id]);
$trainer = $stmt->fetch();

if (!$trainer) {
    header('Location: Trainer.php');
    exit;
}

$errors = [];
$success = isset($_GET['updated']);
$data = [
    'full_name'      => $trainer['full_name'],
    'phone'          => $trainer['phone'],
    'email'          => $trainer['email'] ?? '',
    'specialization' => $trainer['specialization'] ?? '',
    'salary'         => $trainer['salary'],
    'hire_date'      => $trainer['hire_date'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? '');
    }

    // ---------- Validation ----------
    if ($data['full_name'] === '') {
        $errors[] = 'الاسم مطلوب';
    } elseif (mb_strlen($data['full_name']) < 3) {
        $errors[] = 'الاسم لازم يكون 3 أحرف على الأقل';
    }

    if ($data['phone'] === '') {
        $errors[] = 'رقم الهاتف مطلوب';
    } elseif (!preg_match('/^[0-9+]{8,15}$/', $data['phone'])) {
        $errors[] = 'رقم الهاتف غير صحيح (أرقام فقط، من 8 إلى 15 خانة)';
    }

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }

    if ($data['salary'] !== '' && (!is_numeric($data['salary']) || $data['salary'] < 0)) {
        $errors[] = 'الراتب لازم يكون رقم موجب';
    }

    if ($data['hire_date'] === '') {
        $errors[] = 'تاريخ التعيين مطلوب';
    }

    // ---------- الصورة (اختيارية) ----------
    $photoName = $trainer['photo'];
    $newPhoto = false;

    if (!empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        $allowedExt = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'حصل خطأ أثناء رفع الصورة';
        } elseif (!in_array($ext, $allowedExt)) {
            $errors[] = 'نوع الصورة غير مسموح (jpg أو png فقط)';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'حجم الصورة أكبر من 2 ميجا';
        } elseif (getimagesize($file['tmp_name']) === false) {
            $errors[] = 'الملف المرفوع ليس صورة صحيحة';
        } else {
            $photoName = uniqid('trainer_', true) . '.' . $ext;
            $newPhoto = true;
        }
    }

    // ---------- الحفظ ----------
    if (empty($errors)) {
        if ($newPhoto) {
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/trainers/' . $photoName);
            if ($trainer['photo'] && file_exists('uploads/trainers/' . $trainer['photo'])) {
                unlink('uploads/trainers/' . $trainer['photo']);
            }
        }

        $stmt = $pdo->prepare(
            "UPDATE Trainers SET
                full_name = ?, phone = ?, email = ?, specialization = ?,
                salary = ?, photo = ?, hire_date = ?
             WHERE trainer_id = ?"
        );
        $stmt->execute([
            $data['full_name'],
            $data['phone'],
            $data['email'] ?: null,
            $data['specialization'] ?: null,
            $data['salary'] !== '' ? $data['salary'] : 0,
            $photoName,
            $data['hire_date'],
            $id,
        ]);

        header('Location: Trainerdetails.php?id=' . $id . '&updated=1');
        exit;
    }
}

// الحصص اللي بيدرّبها المدرب
$stmt = $pdo->prepare(
    "SELECT c.*,
            (SELECT COUNT(*) FROM Class_members cm WHERE cm.class_id = c.class_id) AS enrolled
     FROM Classes c
     WHERE c.trainer_id = ?
     ORDER BY c.class_id DESC"
);
$stmt->execute([$id]);
$classes = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>تفاصيل المدرب</h3>
    <a href="Trainer.php" class="btn btn-outline-secondary">رجوع</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">تم حفظ التعديلات ✅</div>
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

<!-- بطاقة التفاصيل -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-2 text-center mb-3 mb-md-0">
                <?php if ($trainer['photo']): ?>
                    <img src="uploads/trainers/<?= e($trainer['photo']) ?>"
                         style="width:110px;height:110px;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <i class="bi bi-person-circle text-secondary" style="font-size:90px;"></i>
                <?php endif; ?>
            </div>
            <div class="col-md-10">
                <h4><?= e($trainer['full_name']) ?></h4>
                <p class="mb-1"><i class="bi bi-telephone"></i> <?= e($trainer['phone']) ?></p>
                <p class="mb-1"><i class="bi bi-envelope"></i> <?= e($trainer['email'] ?: '-') ?></p>
                <p class="mb-1"><i class="bi bi-trophy"></i> <?= e($trainer['specialization'] ?: '-') ?></p>
                <p class="mb-1"><i class="bi bi-cash"></i> الراتب: <?= e($trainer['salary']) ?></p>
                <p class="mb-0"><i class="bi bi-calendar"></i> تاريخ التعيين: <?= e($trainer['hire_date']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- حصص المدرب -->
<div class="card shadow-sm mb-4">
    <div class="card-header">الحصص التي يدرّبها</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>الحصة</th>
                    <th>اليوم</th>
                    <th>من</th>
                    <th>إلى</th>
                    <th>المسجلين</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($classes) === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">لا يوجد حصص لهذا المدرب</td></tr>
                <?php endif; ?>
                <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?= e($c['class_name']) ?></td>
                    <td><?= e($c['schedule_day']) ?></td>
                    <td><?= e(substr($c['start_time'], 0, 5)) ?></td>
                    <td><?= e(substr($c['end_time'], 0, 5)) ?></td>
                    <td><?= $c['enrolled'] ?> / <?= $c['capacity'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- نموذج التعديل -->
<div class="card shadow-sm">
    <div class="card-header">تعديل البيانات</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">الاسم الكامل *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($data['full_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">رقم الهاتف *</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">التخصص</label>
                    <input type="text" name="specialization" class="form-control" value="<?= e($data['specialization']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">الراتب</label>
                    <input type="number" step="0.01" min="0" name="salary" class="form-control" value="<?= e($data['salary']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاريخ التعيين *</label>
                    <input type="date" name="hire_date" class="form-control" value="<?= e($data['hire_date']) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">تغيير الصورة (اختياري)</label>
                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">حفظ التعديلات</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>