<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gider Silme";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz gider ID');
}
$id = (int)$_GET['id'];
$stmt = $pdo->prepare("DELETE FROM expense WHERE id = :id");
$stmt->execute(['id' => $id]);
header('Location: expense_list.php');
exit;
include_once __DIR__ . '/../includes/header.php';


require_once __DIR__ . '/../includes/footer.php';
