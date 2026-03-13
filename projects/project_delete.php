<?php
require_once __DIR__ . '/../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz proje ID');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$proje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proje) {
    exit('Proje bulunamadı');
}

$sorgu = $pdo->prepare('SELECT COUNT(*) FROM project_payments WHERE project_id = :id');
$sorgu->execute(['id' => $id]);
$odemeSayisi = $sorgu->fetchColumn();

if ($odemeSayisi > 0) {
    header('Location: project_list.php?delete_error=1');
    exit;
} else {
    $delete = $pdo->prepare('DELETE FROM projects WHERE id = :id');
    $delete->execute(['id' => $id]);
    header('Location:project_list.php?delete=1');
    exit;
}


require_once __DIR__ . '/../includes/footer.php';
