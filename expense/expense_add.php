<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gider Ekleme";

$sorgu = $pdo->prepare('SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name ASC');
$sorgu->execute();
$kategoriler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

$error = '';

$category_id = $_POST['category_id'] ?? '';
$date = $_POST['date'] ?? '';
$amount = $_POST['amount'] ?? '';
$description = $_POST['description'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($category_id === '' || $date === '' || $amount === '') {
        $error = 'Lütfen tüm zorunlu alanları doldurun.';
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $error = 'Tutar sıfırdan büyük olmalıdır.';
    }

    if ($error === '') {
        $stmt = $pdo->prepare("
            INSERT INTO expense (
                category_id,
                date,
                amount,
                description,
                created_at
            ) VALUES (
                :category_id,
                :date,
                :amount,
                :description,
                :created_at
            )
        ");

        $stmt->execute([
            'category_id' => $category_id,
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        header('Location: expense_add.php?ok=1');
        exit;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success" role="alert">
        Gider başarıyla kaydedildi.
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gider Ekle</h2>
                <p class="text-muted mb-0">Yeni gider kaydı oluşturun.</p>
            </div>

            <div class="card-body pt-4">
                <form method="POST">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">Kategori Seçiniz</option>
                            <?php foreach ($kategoriler as $kategori): ?>
                                <option value="<?php echo (int)$kategori['id']; ?>"
                                    <?php echo ((string)$category_id === (string)$kategori['id']) ? 'selected' : ''; ?>>
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
                                value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>"
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
                                value="<?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea
                            name="description"
                            id="description"
                            class="form-control"
                            rows="4"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            Kaydet
                        </button>

                        <a href="expense_list.php" class="btn btn-outline-secondary">
                            Gider Listesi
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>