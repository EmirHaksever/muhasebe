<?php
require_once __DIR__ . '/includes/auth_check.php';
$pageTitle = 'Dashboard';

/* Toplam gelir */
$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM income");
$toplamGelir = (float)$stmt->fetchColumn();

/* Toplam gider */
$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense");
$toplamGider = (float)$stmt->fetchColumn();

$netDurum = $toplamGelir - $toplamGider;

/* Aktif proje sayısı */
$stmt = $pdo->query("SELECT COUNT(*) FROM projects WHERE is_active = 1");
$aktifProjeSayisi = (int)$stmt->fetchColumn();

/* Kalan toplam alacak */
$stmt = $pdo->query("
    SELECT
        COALESCE((SELECT SUM(amount) FROM project_payments), 0) -
        COALESCE((SELECT SUM(amount) FROM income WHERE project_id IS NOT NULL), 0)
");
$kalanAlacak = (float)$stmt->fetchColumn();
if ($kalanAlacak < 0) {
    $kalanAlacak = 0;
}

/* Geciken taksit sayısı */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM project_payments
    WHERE is_paid = 0
      AND due_date < CURDATE()
");
$gecikenTaksitSayisi = (int)$stmt->fetchColumn();

/* Bu ay tahsilat */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM income
    WHERE YEAR(date) = YEAR(CURDATE())
      AND MONTH(date) = MONTH(CURDATE())
");
$buAyTahsilat = (float)$stmt->fetchColumn();

/* Bu ay beklenen tahsilat */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM project_payments
    WHERE YEAR(due_date) = YEAR(CURDATE())
      AND MONTH(due_date) = MONTH(CURDATE())
");
$buAyBeklenenTahsilat = (float)$stmt->fetchColumn();

/* Yaklaşan taksitler */
$stmt = $pdo->query("
    SELECT
        pp.*,
        p.name AS project_name,
        c.name AS customer_name
    FROM project_payments pp
    INNER JOIN projects p ON pp.project_id = p.id
    LEFT JOIN cari_accounts c ON p.customer_id = c.id
    WHERE pp.is_paid = 0
    ORDER BY pp.due_date ASC
    LIMIT 8
");
$yaklasanTaksitler = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Son tahsilatlar */
$stmt = $pdo->query("
    SELECT
        i.*,
        p.name AS project_name
    FROM income i
    LEFT JOIN projects p ON i.project_id = p.id
    ORDER BY i.date DESC, i.id DESC
    LIMIT 8
");
$sonTahsilatlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

function tl($v)
{
    return number_format((float)$v, 2, ',', '.') . ' TL';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">Genel finans ve proje özeti</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Toplam Gelir</small>
                    <div class="fs-4 fw-bold text-success"><?php echo tl($toplamGelir); ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Toplam Gider</small>
                    <div class="fs-4 fw-bold text-danger"><?php echo tl($toplamGider); ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Net Durum</small>
                    <div class="fs-4 fw-bold <?php echo $netDurum >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo tl($netDurum); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Kalan Alacak</small>
                    <div class="fs-4 fw-bold text-primary"><?php echo tl($kalanAlacak); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Aktif Proje</small>
                    <div class="fs-4 fw-bold"><?php echo $aktifProjeSayisi; ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Geciken Taksit</small>
                    <div class="fs-4 fw-bold text-danger"><?php echo $gecikenTaksitSayisi; ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Bu Ay Tahsilat</small>
                    <div class="fs-4 fw-bold text-success"><?php echo tl($buAyTahsilat); ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">Bu Ay Beklenen Tahsilat</small>
                    <div class="fs-4 fw-bold text-warning"><?php echo tl($buAyBeklenenTahsilat); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Yaklaşan Taksitler</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Proje</th>
                                    <th>Müşteri</th>
                                    <th>Vade</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($yaklasanTaksitler)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($yaklasanTaksitler as $taksit): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($taksit['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($taksit['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($taksit['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="fw-semibold"><?php echo tl($taksit['amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Son Tahsilatlar</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Proje</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sonTahsilatlar)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sonTahsilatlar as $gelir): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($gelir['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($gelir['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="fw-semibold text-success"><?php echo tl($gelir['amount']); ?></td>
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>