<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gelir Kategori Toggle";



if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    exit('Geçersiz kategori ID');
}

$id = (int) $_POST['id'];

$stmt = $pdo->prepare("UPDATE income_categories SET is_active = NOT is_active WHERE id = :id");
$stmt->execute(['id' => $id]);

header('Location: income_category_list.php');

include_once __DIR__ . '/../includes/header.php';
