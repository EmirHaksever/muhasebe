<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Geçersiz istek');
}

$id = $_POST['id'] ?? '';

if (!is_numeric($id)) {
    exit('Geçersiz cari ID');
}

$id = (int)$id;

$stmt = $pdo->prepare("SELECT id FROM cari_accounts WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$cari = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cari) {
    exit('Cari bulunamadı');
}

$stmt = $pdo->prepare("UPDATE cari_accounts SET is_active =NOT is_active WHERE id=:id");
$stmt->execute(['id' => $id]);

header('Location:cari_list.php?toggled=1');
exit;


require_once __DIR__ . '/../includes/footer.php';
