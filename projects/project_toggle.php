<?php
include_once __DIR__ . "/../includes/auth_check.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Geçersiz istek');
}

$id = $_POST['id'] ?? '';

if (!is_numeric($id)) {
    exit('Geçersiz proje ID');
}

$id = (int)$id;

$stmt = $pdo->prepare("UPDATE projects SET is_active = NOT is_active WHERE id = :id");
$stmt->execute(['id' => $id]);

header('Location: project_list.php?toggled=1');
exit;


require_once __DIR__ . '/../includes/footer.php';
