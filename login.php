<?php
session_start();
require_once 'config/db.php';
if (isset($_SESSION['user_id'])) {
    header('Location: Dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'الرجاء إدخال اسم المستخدم وكلمة السر';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            header('Location: Dashboard.php');
            exit;
        } else {
            $error = 'اسم المستخدم أو كلمة السر غير صحيحة';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول - Gym System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-11 col-sm-8 col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h4 class="text-center mb-4">
                            <i class="bi bi-activity"></i> Gym System
                        </h4>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label class="form-label">اسم المستخدم</label>
                                <input type="text" name="username" class="form-control"
                                       value="<?= htmlspecialchars($username ?? '') ?>" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">كلمة السر</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">دخول</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>