<?php
require_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gelir Silme";



if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz gelir ID');
}
$id = (int)$_GET['id'];
$stmt = $pdo->prepare("DELETE FROM income WHERE id = :id");
$stmt->execute(['id' => $id]);
header('Location: income_list.php');
exit;

require_once __DIR__ . '/../includes/header.php';



require_once __DIR__ . '/../includes/footer.php';
