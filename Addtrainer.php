<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$data = [
    'full_name'      => '',
    'phone'          => '',
    'email'          => '',
    'specialization' => '',
    'salary'         => '',
    'hire_date'      => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? '');
    }
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
    $photoName = null;

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
        }
    }
    if (empty($errors)) {
        if ($photoName) {
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/trainers/' . $photoName);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO Trainers
             (full_name, phone, email, specialization, salary, photo, hire_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['full_name'],
            $data['phone'],
            $data['email'] ?: null,
            $data['specialization'] ?: null,
            $data['salary'] !== '' ? $data['salary'] : 0,
            $photoName,
            $data['hire_date'],
        ]);

        header('Location: Trainer.php');
        exit;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>إضافة مدرب جديد</h3>
    <a href="Trainer.php" class="btn btn-outline-secondary">رجوع</a>
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
                    <input type="text" name="specialization" class="form-control"
                           placeholder="مثال: لياقة بدنية، يوغا..." value="<?= e($data['specialization']) ?>">
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
                    <label class="form-label">الصورة (jpg / png، حتى 2 ميجا)</label>
                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">حفظ</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>