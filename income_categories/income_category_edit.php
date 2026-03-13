<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gelir Kategori Düzenleme";



if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz İD');
}

$id = (int) $_GET['id'];
$sorgu = $pdo->prepare('SELECT * FROM income_categories WHERE id=:id');
$sorgu->execute(['id' => $id]);
$kategori = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$kategori) {
    header('Location: income_category_list.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    if ($name == '') {
        $error = "Kategori adı boş olamaz.";
    } else {
        $sorgu2 = $pdo->prepare('UPDATE income_categories SET name =:name WHERE id= :id');
        $sorgu2->execute(['name' => $name, 'id' => $id]);
        header('Location: income_category_list.php');
        exit;
    }
}

include_once __DIR__ . '/../includes/header.php';

?>

<form method="POST">
    <?php if ($error != ''): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <label> Kategori Adı: </label><br>
    <input type="text" name="name" value="<?php echo htmlspecialchars($kategori['name']); ?>"><br> <br>
    <button type="submit">Güncelle</button>
</form>
</body>

</html>