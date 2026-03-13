<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Müşteri Hesapları';

$sorgu = $pdo->prepare('
    SELECT id, type, name, phone, email, address, note, is_active, created_at
    FROM cari_accounts
    ORDER BY id DESC
');
$sorgu->execute();
$cariler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['toggled'])): ?>
    <div class="alert alert-success" role="alert">
        Cari hesap durumu güncellendi.
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php if (empty($cariler)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    Kayıtlı cari hesap bulunamadı.
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($cariler as $cari): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h2 class="h5 mb-1">
                                    <?php echo htmlspecialchars($cari['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </h2>

                                <div class="text-muted small">
                                    <?php echo $cari['type'] === 'musteri' ? 'Müşteri' : 'Tedarikçi'; ?>
                                </div>
                            </div>

                            <div>
                                <?php if ((int)$cari['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Pasif</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="small text-body-secondary d-flex flex-column gap-2 mb-4">
                            <div><strong>Telefon:</strong> <?php echo htmlspecialchars($cari['phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div><strong>E-posta:</strong> <?php echo htmlspecialchars($cari['email'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div><strong>Adres:</strong> <?php echo htmlspecialchars($cari['address'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div><strong>Not:</strong> <?php echo htmlspecialchars($cari['note'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="mt-auto d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-secondary btn-sm" href="cari_detail.php?id=<?php echo (int)$cari['id']; ?>">
                                Detay
                            </a>



                            <form method="POST" action="cari_toggle.php" class="m-0">
                                <input type="hidden" name="id" value="<?php echo (int)$cari['id']; ?>">
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <?php echo (int)$cari['is_active'] === 1 ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>