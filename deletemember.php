<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT photo FROM Members WHERE member_id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch();

    if ($member) {
        if ($member['photo'] && file_exists('uploads/members/' . $member['photo'])) {
            unlink('uploads/members/' . $member['photo']);
        }
        $stmt = $pdo->prepare("DELETE FROM Members WHERE member_id = ?");
        $stmt->execute([$id]);
    }
}

header('Location: Members.php');
exit;