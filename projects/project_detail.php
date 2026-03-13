<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Proje Detayı';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz proje ID');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare('
    SELECT projects.*, cari_accounts.name AS customer_name
    FROM projects
    LEFT JOIN cari_accounts ON projects.customer_id = cari_accounts.id
    WHERE projects.id = :id
    LIMIT 1
');
$stmt->execute(['id' => $id]);
$proje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proje) {
    exit('Proje bulunamadı');
}

$musteriSorgu = $pdo->prepare("
    SELECT id, name
    FROM cari_accounts
    WHERE type = :type AND is_active = 1
    ORDER BY name ASC
");
$musteriSorgu->execute([
    'type' => 'musteri'
]);
$musteriler = $musteriSorgu->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('
    SELECT *
    FROM project_payments
    WHERE project_id = :project_id
    ORDER BY installment_no ASC
');
$stmt->execute(['project_id' => $id]);
$odemeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| MODEL 2 HESAPLARI
|--------------------------------------------------------------------------
*/
$toplamNet = 0;
$toplamKdv = 0;
$grandTotal = 0;
$odenenToplam = 0;
$odenenTaksitVar = false;
$kalanTaksitSayisi = 0;
$aktifKdvOranlari = [];

foreach ($odemeler as $odeme) {
    $net_amount = isset($odeme['net_amount']) ? (float)$odeme['net_amount'] : 0;
    $vat_amount = isset($odeme['vat_amount']) ? (float)$odeme['vat_amount'] : 0;
    $amount = (float)$odeme['amount'];
    $vat_rate_payment = isset($odeme['vat_rate']) ? $odeme['vat_rate'] : null;

    if ($net_amount <= 0) {
        $net_amount = $amount;
    }

    $toplamNet += $net_amount;
    $toplamKdv += $vat_amount;
    $grandTotal += $amount;

    if ((int)$odeme['is_paid'] === 1) {
        $odenenToplam += $amount;
        $odenenTaksitVar = true;
    } else {
        $kalanTaksitSayisi++;
    }

    if ($vat_rate_payment !== null && $vat_rate_payment !== '' && (float)$vat_rate_payment > 0) {
        $aktifKdvOranlari[] = (float)$vat_rate_payment;
    }
}

$kalanToplam = $grandTotal - $odenenToplam;
if ($kalanToplam < 0) {
    $kalanToplam = 0;
}

if ($grandTotal > 0) {
    $odemeYuzdesi = ($odenenToplam / $grandTotal) * 100;
} else {
    $odemeYuzdesi = 0;
}

$hasVat = $toplamKdv > 0 ? 1 : 0;

$tekilOranlar = array_values(array_unique(array_map(function ($v) {
    return number_format((float)$v, 2, '.', '');
}, $aktifKdvOranlari)));

if (count($tekilOranlar) === 1) {
    $kdvOraniGosterim = '%' . number_format((float)$tekilOranlar[0], 2, ',', '.');
} elseif (count($tekilOranlar) > 1) {
    $kdvOraniGosterim = 'Değişken';
} else {
    $kdvOraniGosterim = '-';
}

$netToplam = $toplamNet;
$vatAmount = $toplamKdv;

$canApplyVat = (
    (int)$proje['is_active'] === 1 &&
    $hasVat === 0 &&
    $odenenTaksitVar === false
);

$canUpdateRemainingVat = (
    (int)$proje['is_active'] === 1 &&
    $kalanTaksitSayisi > 0
);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_project') {
        $name = trim($_POST['name'] ?? '');
        $customer_id = isset($_POST['customer_id']) && is_numeric($_POST['customer_id'])
            ? (int)$_POST['customer_id']
            : null;
        $note = trim($_POST['note'] ?? '');
        $is_active = $_POST['is_active'] ?? '';

        if ($name === '') {
            $error = 'Proje adı boş olamaz.';
        } elseif ($is_active !== '0' && $is_active !== '1') {
            $error = 'Geçersiz durum seçimi.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE projects
                SET name = :name,
                    customer_id = :customer_id,
                    note = :note,
                    is_active = :is_active
                WHERE id = :id
            ");

            $stmt->execute([
                'name' => $name,
                'customer_id' => $customer_id,
                'note' => $note,
                'is_active' => (int)$is_active,
                'id' => $id
            ]);

            header('Location: project_detail.php?id=' . $id . '&updated=1');
            exit;
        }
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" role="alert">Proje bilgileri güncellendi.</div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'kdv_uygulandi'): ?>
    <div class="alert alert-success" role="alert">Projeye KDV başarıyla uygulandı.</div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'kalan_taksit_kdv_guncellendi'): ?>
    <div class="alert alert-success" role="alert">Kalan taksitlerin KDV oranı başarıyla güncellendi.</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger" role="alert">
        <?php
        if ($_GET['error'] === 'pasif_projeye_kdv_uygulanamaz') {
            echo 'Aktif olmayan projeye KDV uygulanamaz.';
        } elseif ($_GET['error'] === 'kdv_zaten_uygulanmis') {
            echo 'Bu projeye zaten KDV uygulanmış.';
        } elseif ($_GET['error'] === 'odeme_baslamis_projeye_kdv_uygulanamaz') {
            echo 'Ödeme başlamış projeye sonradan KDV uygulanamaz.';
        } elseif ($_GET['error'] === 'gecerli_kdv_orani_yok') {
            echo 'Ayarlar kısmında geçerli bir KDV oranı yok.';
        } elseif ($_GET['error'] === 'taksit_kayitlari_uyusmuyor') {
            echo 'Proje taksit kayıtları ile taksit sayısı uyuşmuyor.';
        } elseif ($_GET['error'] === 'pasif_projede_kdv_guncellenemez') {
            echo 'Pasif projede kalan taksitlerin KDV oranı güncellenemez.';
        } elseif ($_GET['error'] === 'guncellenecek_kalan_taksit_yok') {
            echo 'Güncellenecek ödenmemiş taksit bulunamadı.';
        } elseif ($_GET['error'] === 'gecersiz_taksit_sayisi') {
            echo 'Projede geçersiz taksit sayısı bulunuyor.';
        } else {
            echo 'İşlem sırasında hata oluştu.';
        }
        ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <?php if (isset($_GET['edit'])): ?>
                    <h2 class="h4 mb-1">Proje Bilgilerini Düzenle</h2>
                    <p class="text-muted mb-0">Seçili proje bilgilerini güncelleyin.</p>
                <?php else: ?>
                    <h2 class="h4 mb-1">Proje Bilgileri</h2>
                    <p class="text-muted mb-0">Projenin genel özetini görüntüleyin.</p>
                <?php endif; ?>
            </div>

            <div class="card-body pt-4">
                <?php if (isset($_GET['edit'])): ?>
                    <form method="POST" action="project_detail.php?id=<?php echo (int)$proje['id']; ?>">
                        <input type="hidden" name="action" value="update_project">

                        <div class="mb-3">
                            <label for="name" class="form-label">Proje Adı</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="<?php echo htmlspecialchars($proje['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Müşteri</label>
                            <select name="customer_id" id="customer_id" class="form-select">
                                <option value="">Müşteri Seçiniz</option>
                                <?php foreach ($musteriler as $musteri): ?>
                                    <option value="<?php echo (int)$musteri['id']; ?>"
                                        <?php echo ((string)($proje['customer_id'] ?? '') === (string)$musteri['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($musteri['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="is_active" class="form-label">Durum</label>
                            <select name="is_active" id="is_active" class="form-select">
                                <option value="1" <?php echo ((string)$proje['is_active'] === '1') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?php echo ((string)$proje['is_active'] === '0') ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="note" class="form-label">Not</label>
                            <textarea name="note" id="note" rows="4" class="form-control"><?php echo htmlspecialchars($proje['note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                            <a class="btn btn-outline-secondary" href="project_detail.php?id=<?php echo (int)$proje['id']; ?>">İptal</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div><strong>Proje Adı:</strong></div>
                            <div><?php echo htmlspecialchars($proje['name']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Müşteri Adı:</strong></div>
                            <div><?php echo htmlspecialchars($proje['customer_name'] ?? '-'); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Net Tutar:</strong></div>
                            <div><?php echo number_format($netToplam, 2, ',', '.'); ?> TL</div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>KDV Durumu:</strong></div>
                            <div>
                                <?php if ($hasVat === 1): ?>
                                    <span class="badge bg-success">Uygulandı</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Uygulanmadı</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>KDV Oranı:</strong></div>
                            <div><?php echo $kdvOraniGosterim; ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Toplam KDV:</strong></div>
                            <div><?php echo number_format($vatAmount, 2, ',', '.'); ?> TL</div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>KDV Dahil Toplam:</strong></div>
                            <div><?php echo number_format($grandTotal, 2, ',', '.'); ?> TL</div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Taksit Sayısı:</strong></div>
                            <div><?php echo htmlspecialchars($proje['installment_count']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Başlangıç Tarihi:</strong></div>
                            <div><?php echo htmlspecialchars($proje['start_date']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Durum:</strong></div>
                            <div>
                                <?php if ((int)$proje['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <div><strong>Not:</strong></div>
                            <div><?php echo nl2br(htmlspecialchars($proje['note'] ?: '-', ENT_QUOTES, 'UTF-8')); ?></div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <a class="btn btn-outline-primary" href="project_detail.php?id=<?php echo (int)$proje['id']; ?>&edit=1">Düzenle</a>

                        <?php if ($canApplyVat): ?>
                            <form method="POST" action="apply_vat.php" class="m-0"
                                onsubmit="return confirm('Bu projeye KDV uygulanacak ve taksit tutarları yeniden hesaplanacak. Devam edilsin mi?');">
                                <input type="hidden" name="project_id" value="<?php echo (int)$proje['id']; ?>">
                                <button type="submit" class="btn btn-outline-dark">KDV Uygula</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($canUpdateRemainingVat): ?>
                        <div class="border rounded p-3 mt-4 bg-light">
                            <h3 class="h6 mb-3">Kalan Taksitler İçin KDV Güncelle</h3>

                            <form method="POST" action="update_remaining_vat.php" class="row g-3 align-items-end">
                                <input type="hidden" name="project_id" value="<?php echo (int)$proje['id']; ?>">

                                <div class="col-md-4">
                                    <label for="vat_rate" class="form-label">Yeni KDV Oranı (%)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="vat_rate"
                                        id="vat_rate"
                                        class="form-control"
                                        value="<?php echo count($tekilOranlar) === 1 ? htmlspecialchars((string)$tekilOranlar[0], ENT_QUOTES, 'UTF-8') : '20.00'; ?>"
                                        required>
                                </div>

                                <div class="col-md-auto">
                                    <button type="submit"
                                        class="btn btn-warning"
                                        onclick="return confirm('Sadece ödenmemiş taksitlerin KDV oranı güncellenecek. Devam edilsin mi?');">
                                        Kalan Taksitleri Güncelle
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h2 class="h4 mb-1">Ödeme Özeti</h2>
                        <p class="text-muted mb-0">Tahsilat durumunu görüntüleyin.</p>
                    </div>

                    <div class="card-body pt-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <small class="text-muted d-block mb-1">Ödenen Toplam</small>
                                    <div class="fs-5 fw-bold text-success"><?php echo number_format($odenenToplam, 2, ',', '.'); ?> TL</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <small class="text-muted d-block mb-1">Kalan Toplam</small>
                                    <div class="fs-5 fw-bold text-danger"><?php echo number_format($kalanToplam, 2, ',', '.'); ?> TL</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <small class="text-muted d-block mb-1">Ödeme Yüzdesi</small>
                                    <div class="fs-5 fw-bold">%<?php echo number_format($odemeYuzdesi, 2, ',', '.'); ?></div>
                                    <div class="progress mt-3" role="progressbar" aria-valuenow="<?php echo (int)$odemeYuzdesi; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: <?php echo min(100, max(0, $odemeYuzdesi)); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h2 class="h4 mb-1">Ödeme Planı</h2>
        <p class="text-muted mb-0">Taksit detaylarını ve ödeme durumlarını yönetin.</p>
    </div>

    <div class="card-body pt-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Taksit No</th>
                        <th>Vade Tarihi</th>
                        <th>Net Tutar</th>
                        <th>KDV Oranı</th>
                        <th>KDV Tutarı</th>
                        <th>Genel Tutar</th>
                        <th>Durum</th>
                        <th>Ödeme Tarihi</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($odemeler)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Ödeme planı bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($odemeler as $odeme): ?>
                            <?php
                            $satirNet = isset($odeme['net_amount']) ? (float)$odeme['net_amount'] : 0;
                            if ($satirNet <= 0) {
                                $satirNet = (float)$odeme['amount'];
                            }

                            $satirVatRate = isset($odeme['vat_rate']) && $odeme['vat_rate'] !== null && $odeme['vat_rate'] !== ''
                                ? '%' . number_format((float)$odeme['vat_rate'], 2, ',', '.')
                                : '-';

                            $satirVatAmount = isset($odeme['vat_amount']) ? (float)$odeme['vat_amount'] : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($odeme['installment_no']); ?></td>
                                <td><?php echo htmlspecialchars($odeme['due_date']); ?></td>
                                <td><?php echo number_format($satirNet, 2, ',', '.'); ?> TL</td>
                                <td><?php echo $satirVatRate; ?></td>
                                <td><?php echo number_format($satirVatAmount, 2, ',', '.'); ?> TL</td>
                                <td class="fw-semibold"><?php echo number_format((float)$odeme['amount'], 2, ',', '.'); ?> TL</td>
                                <td>
                                    <?php if ((int)$odeme['is_paid'] === 1): ?>
                                        <span class="badge bg-success">Ödendi</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ödenmedi</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($odeme['paid_at'] ?? '-'); ?></td>
                                <td class="text-end">
                                    <?php if ((int)$proje['is_active'] === 1): ?>
                                        <form method="POST" action="payment_toggle.php" class="m-0 d-inline">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$odeme['id']; ?>">
                                            <input type="hidden" name="project_id" value="<?php echo (int)$proje['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo (int)$odeme['is_paid'] === 1 ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                                <?php echo (int)$odeme['is_paid'] === 1 ? 'Geri Al' : 'Ödendi Yap'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Pasif Proje</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>