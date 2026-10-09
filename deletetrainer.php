<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT photo FROM Trainers WHERE trainer_id = ?");
    $stmt->execute([$id]);
    $trainer = $stmt->fetch();

    if ($trainer) {
        if ($trainer['photo'] && file_exists('uploads/trainers/' . $trainer['photo'])) {
            unlink('uploads/trainers/' . $trainer['photo']);
        }
        $stmt = $pdo->prepare("DELETE FROM Trainers WHERE trainer_id = ?");
        $stmt->execute([$id]);
    }
}

header('Location: Trainer.php');
exit;