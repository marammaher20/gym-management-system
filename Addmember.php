<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$data = [
    'full_name'  => '',
    'phone'      => '',
    'email'      => '',
    'gender'     => '',
    'birth_date' => '',
    'address'    => '',
    'join_date'  => date('Y-m-d'),
    'status'     => 'active',
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

    if (!in_array($data['gender'], ['male', 'female'])) {
        $errors[] = 'اختاري الجنس';
    }

    if ($data['join_date'] === '') {
        $errors[] = 'تاريخ الانضمام مطلوب';
    }

    if (!in_array($data['status'], ['active', 'inactive'])) {
        $data['status'] = 'active';
    }

    // ---------- رفع الصورة ----------
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
            $photoName = uniqid('member_', true) . '.' . $ext;
        }
    }

    // ---------- الحفظ ----------
    if (empty($errors)) {
        if ($photoName) {
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/members/' . $photoName);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO Members
             (full_name, phone, email, gender, birth_date, address, photo, join_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['full_name'],
            $data['phone'],
            $data['email'] ?: null,
            $data['gender'],
            $data['birth_date'] ?: null,
            $data['address'] ?: null,
            $photoName,
            $data['join_date'],
            $data['status'],
        ]);

        header('Location: Members.php');
        exit;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>إضافة عضو جديد</h3>
    <a href="Members.php" class="btn btn-outline-secondary">رجوع</a>
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
                    <label class="form-label">الجنس *</label>
                    <select name="gender" class="form-select">
                        <option value="">اختاري...</option>
                        <option value="male" <?= $data['gender'] === 'male' ? 'selected' : '' ?>>ذكر</option>
                        <option value="female" <?= $data['gender'] === 'female' ? 'selected' : '' ?>>أنثى</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاريخ الميلاد</label>
                    <input type="date" name="birth_date" class="form-control" value="<?= e($data['birth_date']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاريخ الانضمام *</label>
                    <input type="date" name="join_date" class="form-control" value="<?= e($data['join_date']) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="address" class="form-control" value="<?= e($data['address']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>فعّال</option>
                        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>غير فعّال</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">الصورة (jpg / png، حتى 2 ميجا)</label>
                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">حفظ</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>