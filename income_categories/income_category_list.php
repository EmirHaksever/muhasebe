<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gelir Kategori Listesi";

include_once __DIR__ . '/../includes/header.php';

$sorgu = $pdo->prepare('SELECT * FROM income_categories ORDER BY name ASC');
$sorgu->execute();
$kategoriler = $sorgu->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gelir Kategorileri</h2>
                <p class="text-muted mb-0">Gelir kategorilerini görüntüleyin ve yönetin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="income_category_add.php" class="btn btn-success">Kategori Ekle</a>
                </div>

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
                                                <form method="POST" action="income_category_toggle.php" class="m-0">
                                                    <input type="hidden" name="id" value="<?php echo (int)$kategori['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        <?php echo (int)$kategori['is_active'] === 1 ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                                    </button>
                                                </form>

                                                <a href="income_category_edit.php?id=<?php echo (int)$kategori['id']; ?>"
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
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>