<?php
require_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gider Kategorileri";

$error = '';
$editKategori = null;

if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];

    $stmt = $pdo->prepare('SELECT id, name FROM expense_categories WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editKategori = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Kategori adı boş olamaz.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO expense_categories (name, is_active)
                VALUES (:name, :is_active)
            ');
            $stmt->execute([
                'name' => $name,
                'is_active' => 1
            ]);

            header('Location: expense_category.php?ok=1');
            exit;
        }
    }

    if ($action === 'toggle') {
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            exit('Geçersiz kategori ID');
        }

        $id = (int)$_POST['id'];

        $stmt = $pdo->prepare('UPDATE expense_categories SET is_active = NOT is_active WHERE id = :id');
        $stmt->execute(['id' => $id]);

        header('Location: expense_category.php?toggled=1');
        exit;
    }

    if ($action === 'edit') {
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            exit('Geçersiz kategori ID');
        }

        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Kategori adı boş olamaz.';
        } else {
            $stmt = $pdo->prepare('UPDATE expense_categories SET name = :name WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'id' => $id
            ]);

            header('Location: expense_category.php?edited=1');
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM expense_categories ORDER BY id ASC');
$stmt->execute();
$kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success" role="alert">
        Kategori eklendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['toggled'])): ?>
    <div class="alert alert-success" role="alert">
        Kategori durumu güncellendi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['edited'])): ?>
    <div class="alert alert-success" role="alert">
        Kategori güncellendi.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <?php if ($editKategori): ?>
                    <h2 class="h4 mb-1">Kategori Düzenle</h2>
                    <p class="text-muted mb-0">Seçili gider kategorisini güncelleyin.</p>
                <?php else: ?>
                    <h2 class="h4 mb-1">Kategori Ekle</h2>
                    <p class="text-muted mb-0">Yeni gider kategorisi oluşturun.</p>
                <?php endif; ?>
            </div>

            <div class="card-body pt-4">
                <?php if ($editKategori): ?>
                    <form method="POST" action="expense_category.php">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo (int)$editKategori['id']; ?>">

                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Yeni Kategori Adı</label>
                            <input
                                type="text"
                                id="edit_name"
                                name="name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($editKategori['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Güncelle</button>
                            <a href="expense_category.php" class="btn btn-outline-secondary">İptal</a>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="POST" action="expense_category.php">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label for="name" class="form-label">Kategori Adı</label>
                            <input type="text" id="name" name="name" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-success">Kaydet</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gider Kategori Listesi</h2>
                <p class="text-muted mb-0">Tüm gider kategorilerini görüntüleyin ve yönetin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sıra</th>
                                <th>Kategori Adı</th>
                                <th>Durum</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kategoriler)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Kayıtlı kategori bulunamadı.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kategoriler as $index => $kategori): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($kategori['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ((int)$kategori['is_active'] === 1): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                                <form method="POST" action="expense_category.php" class="m-0">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int)$kategori['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        <?php echo (int)$kategori['is_active'] === 1 ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                                    </button>
                                                </form>

                                                <a href="expense_category.php?edit_id=<?php echo (int)$kategori['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    Düzenle
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="../expense/expense_list.php" class="btn btn-outline-secondary">Geri Dön</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>