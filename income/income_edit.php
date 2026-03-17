<?php
require_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gelir Düzenleme";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz gelir ID');
}

$id = (int)$_GET['id'];
$error = '';

$sorgu = $pdo->prepare("SELECT * FROM income WHERE id = :id");
$sorgu->execute(['id' => $id]);
$gelir = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$gelir) {
    header('Location: income_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        exit('Geçersiz gelir ID');
    }

    $category_id = $_POST['category_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($category_id === '' || $date === '' || $amount === '') {
        $error = 'Lütfen tüm zorunlu alanları doldurun.';
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $error = 'Tutar sıfırdan büyük olmalıdır.';
    }

    if ($error === '') {
        $stmt = $pdo->prepare("
            UPDATE income
            SET category_id = :category_id,
                date = :date,
                amount = :amount,
                description = :description
            WHERE id = :id
        ");

        $stmt->execute([
            'category_id' => $category_id,
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'id' => (int)$_POST['id']
        ]);

        header('Location: income_list.php');
        exit;
    }

    // Hata varsa ekranda POST değerleri görünsün
    $gelir['category_id'] = $category_id;
    $gelir['date'] = $date;
    $gelir['amount'] = $amount;
    $gelir['description'] = $description;
}

$sorgu2 = $pdo->prepare("SELECT id, name FROM income_categories WHERE is_active = 1 ORDER BY name ASC");
$sorgu2->execute();
$kategoriler = $sorgu2->fetchAll(PDO::FETCH_ASSOC);

include_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gelir Kaydını Düzenle</h2>
                <p class="text-muted mb-0">Seçili gelir kaydını güncelleyin.</p>
            </div>

            <div class="card-body pt-4">
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$gelir['id']; ?>">

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">Kategori Seçiniz</option>
                            <?php foreach ($kategoriler as $kategori): ?>
                                <option value="<?php echo (int)$kategori['id']; ?>"
                                    <?php echo ((string)$kategori['id'] === (string)$gelir['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kategori['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date" class="form-label">Tarih <span class="text-danger">*</span></label>
                            <input
                                type="date"
                                name="date"
                                id="date"
                                class="form-control"
                                value="<?php echo htmlspecialchars($gelir['date'], ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Tutar <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                id="amount"
                                class="form-control"
                                value="<?php echo htmlspecialchars($gelir['amount'], ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Açıklama</label>
                        <input
                            type="text"
                            name="description"
                            id="description"
                            class="form-control"
                            value="<?php echo htmlspecialchars($gelir['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            Güncelle
                        </button>

                        <a href="income_list.php" class="btn btn-outline-secondary">
                            Geri Dön
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>