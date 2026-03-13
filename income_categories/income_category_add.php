<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gelir Kategori Ekleme";


$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = ($_POST['name'] ?? '');
    if ($name == '') {
        $error = 'Kategori adı boş olamaz.';
    }
    if ($error == '') {
        $sorgu = $pdo->prepare('INSERT INTO income_categories (name, is_active) VALUES (:name, :is_active)');
        $sorgu->execute([
            'name' => $name,
            'is_active' => 1
        ]);
        header('Location: income_category_list.php');
        exit;
    }
}

include_once __DIR__ . '/../includes/header.php';

?>

<?php if ($error != ''): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<form method="POST" action="income_category_add.php">
    <label> Kategori Adı: </label> <br>
    <input name="name" type="text">
    <button type="submit">Kaydet</button>
</form>
<br>
<a href="/income_categories/income_category_list.php" class="btn">Geri Dön</a>
</body>

</html>