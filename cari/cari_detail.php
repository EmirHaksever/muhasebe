<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Müşteri Detayı';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz müşteri ID');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare('SELECT * FROM cari_accounts WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$cari = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cari) {
    exit('Müşteri bulunamadı');
}

$stmt = $pdo->prepare("
    SELECT 
        projects.*,

        (
            SELECT COALESCE(SUM(income.amount), 0)
            FROM income
            WHERE income.project_id = projects.id
        ) AS tahsil_edilen,

        (
            SELECT COALESCE(SUM(project_payments.amount), 0)
            FROM project_payments
            WHERE project_payments.project_id = projects.id
        ) AS proje_toplami

    FROM projects
    WHERE projects.customer_id = :customer_id
    ORDER BY projects.id DESC
");
$stmt->execute(['customer_id' => $id]);
$projeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(income.amount), 0) AS toplam_tahsilat
    FROM income
    INNER JOIN projects ON income.project_id = projects.id
    WHERE projects.customer_id = :customer_id
");
$stmt->execute(['customer_id' => $id]);
$toplamTahsilat = (float)$stmt->fetchColumn();

$aktifProjeSayisi = 0;
$toplamKalanAlacak = 0;

foreach ($projeler as $proje) {
    if ((int)$proje['is_active'] === 1) {
        $aktifProjeSayisi++;
    }

    $tahsilEdilen = (float)$proje['tahsil_edilen'];
    $toplam = (float)$proje['proje_toplami'];

    if ($toplam <= 0) {
        $toplam = !empty($proje['grand_total'])
            ? (float)$proje['grand_total']
            : (float)$proje['total_amount'];
    }

    $kalan = $toplam - $tahsilEdilen;
    if ($kalan < 0) {
        $kalan = 0;
    }

    $toplamKalanAlacak += $kalan;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM project_payments
    INNER JOIN projects ON project_payments.project_id = projects.id
    WHERE projects.customer_id = :customer_id
      AND project_payments.is_paid = 0
      AND project_payments.due_date < CURDATE()
");
$stmt->execute(['customer_id' => $id]);
$gecikmisTaksitSayisi = (int)$stmt->fetchColumn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_cari') {
        $type = $_POST['type'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if ($type === '' || $name === '') {
            $error = 'Cari tipi ve adı zorunludur.';
        } elseif ($type !== 'musteri' && $type !== 'tedarikci') {
            $error = 'Geçersiz cari tipi.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE cari_accounts
                SET type = :type,
                    name = :name,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    note = :note
                WHERE id = :id
            ");

            $stmt->execute([
                'type' => $type,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'note' => $note,
                'id' => $id
            ]);

            header('Location: cari_detail.php?id=' . $id . '&updated=1');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

function tl($v)
{
    return number_format((float)$v, 2, ',', '.') . " TL";
}

$projeSayisi = count($projeler);
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success" role="alert">
        Cari bilgileri güncellendi.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <?php if (isset($_GET['edit'])): ?>
                    <h2 class="h4 mb-1">Müşteri Bilgilerini Düzenle</h2>
                    <p class="text-muted mb-0">Cari hesap bilgilerini güncelleyin.</p>
                <?php else: ?>
                    <h2 class="h4 mb-1">Müşteri Bilgileri</h2>
                    <p class="text-muted mb-0">Cari hesap detaylarını görüntüleyin.</p>
                <?php endif; ?>
            </div>

            <div class="card-body pt-4">
                <?php if (isset($_GET['edit'])): ?>
                    <form method="POST" action="cari_detail.php?id=<?php echo (int)$cari['id']; ?>">
                        <input type="hidden" name="action" value="update_cari">

                        <div class="mb-3">
                            <label for="type" class="form-label">Cari Tipi</label>
                            <select name="type" id="type" class="form-select">
                                <option value="">Seçiniz</option>
                                <option value="musteri" <?php echo (($cari['type'] ?? '') === 'musteri') ? 'selected' : ''; ?>>Müşteri</option>
                                <option value="tedarikci" <?php echo (($cari['type'] ?? '') === 'tedarikci') ? 'selected' : ''; ?>>Tedarikçi</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Adı</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="<?php echo htmlspecialchars($cari['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="text" name="phone" id="phone" class="form-control"
                                    value="<?php echo htmlspecialchars($cari['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="text" name="email" id="email" class="form-control"
                                    value="<?php echo htmlspecialchars($cari['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Adres</label>
                            <textarea name="address" id="address" rows="4" class="form-control"><?php echo htmlspecialchars($cari['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="note" class="form-label">Not</label>
                            <textarea name="note" id="note" rows="4" class="form-control"><?php echo htmlspecialchars($cari['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                            <a class="btn btn-outline-secondary" href="cari_detail.php?id=<?php echo (int)$cari['id']; ?>">İptal</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div><strong>Adı:</strong></div>
                            <div><?php echo htmlspecialchars($cari['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Tipi:</strong></div>
                            <div><?php echo $cari['type'] === 'musteri' ? 'Müşteri' : 'Tedarikçi'; ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Telefon:</strong></div>
                            <div><?php echo htmlspecialchars($cari['phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>E-posta:</strong></div>
                            <div><?php echo htmlspecialchars($cari['email'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div><strong>Durum:</strong></div>
                            <div>
                                <?php if ((int)$cari['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <div><strong>Adres:</strong></div>
                            <div><?php echo nl2br(htmlspecialchars($cari['address'] ?: '-', ENT_QUOTES, 'UTF-8')); ?></div>
                        </div>

                        <div class="col-12">
                            <div><strong>Not:</strong></div>
                            <div><?php echo nl2br(htmlspecialchars($cari['note'] ?: '-', ENT_QUOTES, 'UTF-8')); ?></div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a class="btn btn-outline-primary" href="cari_detail.php?id=<?php echo (int)$cari['id']; ?>&edit=1">Düzenle</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-xl-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Toplam Proje Sayısı</small>
                        <div class="fs-4 fw-bold"><?php echo $projeSayisi; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Aktif Proje Sayısı</small>
                        <div class="fs-4 fw-bold text-primary"><?php echo $aktifProjeSayisi; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Toplam Tahsilat</small>
                        <div class="fs-4 fw-bold text-success"><?php echo tl($toplamTahsilat); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Toplam Kalan Alacak</small>
                        <div class="fs-4 fw-bold text-danger"><?php echo tl($toplamKalanAlacak); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Gecikmiş Taksit Sayısı</small>
                        <div class="fs-4 fw-bold <?php echo $gecikmisTaksitSayisi > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $gecikmisTaksitSayisi; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h2 class="h4 mb-1">Bu Müşteriye Ait Projeler</h2>
        <p class="text-muted mb-0">Müşteriye bağlı proje ve tahsilat durumları.</p>
    </div>

    <div class="card-body pt-4">
        <?php if (empty($projeler)): ?>
            <div class="text-muted">Bu müşteriye ait proje bulunamadı.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Proje Adı</th>
                            <th>KDV Dahil Toplam</th>
                            <th>Tahsil Edilen</th>
                            <th>Kalan</th>
                            <th>Ödeme %</th>
                            <th>Taksit Sayısı</th>
                            <th>Başlangıç Tarihi</th>
                            <th>Durum</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projeler as $proje): ?>
                            <?php
                            $tahsilEdilen = (float)$proje['tahsil_edilen'];
                            $toplam = (float)$proje['proje_toplami'];

                            if ($toplam <= 0) {
                                $toplam = !empty($proje['grand_total'])
                                    ? (float)$proje['grand_total']
                                    : (float)$proje['total_amount'];
                            }

                            $kalan = $toplam - $tahsilEdilen;
                            if ($kalan < 0) {
                                $kalan = 0;
                            }

                            if ($toplam > 0) {
                                $oran = ($tahsilEdilen / $toplam) * 100;
                            } else {
                                $oran = 0;
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($proje['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="fw-semibold"><?php echo tl($toplam); ?></td>
                                <td class="text-success fw-semibold"><?php echo tl($tahsilEdilen); ?></td>
                                <td class="text-danger fw-semibold"><?php echo tl($kalan); ?></td>
                                <td>%<?php echo number_format($oran, 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($proje['installment_count'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($proje['start_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ((int)$proje['is_active'] === 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="../projects/project_detail.php?id=<?php echo (int)$proje['id']; ?>">
                                        Detay
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>