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

/* Toplam cari sayısı */
$stmt = $pdo->query("SELECT COUNT(*) FROM cari_accounts");
$toplamCariSayisi = (int)$stmt->fetchColumn();

/* Geciken taksit sayısı */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM project_payments
    WHERE is_paid = 0
      AND due_date < CURDATE()
");
$gecikenTaksitSayisi = (int)$stmt->fetchColumn();

/* Geciken taksit toplamı */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM project_payments
    WHERE is_paid = 0
      AND due_date < CURDATE()
");
$gecikenTaksitTutari = (float)$stmt->fetchColumn();

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

/* Toplam proje tahsilatı */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM income
    WHERE project_id IS NOT NULL
");
$toplamProjeTahsilati = (float)$stmt->fetchColumn();

/* Toplam proje ödeme planı */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM project_payments
");
$toplamProjePlani = (float)$stmt->fetchColumn();

/* Tahsilat oranı */
$tahsilatOrani = 0;
if ($toplamProjePlani > 0) {
    $tahsilatOrani = ($toplamProjeTahsilati / $toplamProjePlani) * 100;
}

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

/* Tahsilat performansı */
$tahsilatPerformansYuzdesi = 0;
if ($buAyBeklenenTahsilat > 0) {
    $tahsilatPerformansYuzdesi = ($buAyTahsilat / $buAyBeklenenTahsilat) * 100;
}
if ($tahsilatPerformansYuzdesi > 100) {
    $tahsilatPerformansYuzdesi = 100;
}

$eksikTahsilat = $buAyBeklenenTahsilat - $buAyTahsilat;
if ($eksikTahsilat < 0) {
    $eksikTahsilat = 0;
}

/* En çok gelir getiren kategori */
$stmt = $pdo->query("
    SELECT 
        ic.name AS category_name,
        COALESCE(SUM(i.amount),0) AS total
    FROM income i
    LEFT JOIN income_categories ic ON i.category_id = ic.id
    GROUP BY i.category_id, ic.name
    ORDER BY total DESC
    LIMIT 1
");
$enCokGelirKategori = $stmt->fetch(PDO::FETCH_ASSOC);

/* En çok gider olan kategori */
$stmt = $pdo->query("
    SELECT 
        ec.name AS category_name,
        COALESCE(SUM(e.amount),0) AS total
    FROM expense e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    GROUP BY e.category_id, ec.name
    ORDER BY total DESC
    LIMIT 1
");
$enCokGiderKategori = $stmt->fetch(PDO::FETCH_ASSOC);

/* En çok tahsilat gelen proje */
$stmt = $pdo->query("
    SELECT 
        p.name AS project_name,
        COALESCE(SUM(i.amount),0) AS total
    FROM income i
    INNER JOIN projects p ON i.project_id = p.id
    GROUP BY i.project_id, p.name
    ORDER BY total DESC
    LIMIT 1
");
$enCokTahsilatProje = $stmt->fetch(PDO::FETCH_ASSOC);

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

/* Son giderler */
$stmt = $pdo->query("
    SELECT
        e.*,
        ec.name AS category_name
    FROM expense e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    ORDER BY e.date DESC, e.id DESC
    LIMIT 8
");
$sonGiderler = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Son 6 ay gelir */
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') AS ay,
        COALESCE(SUM(amount), 0) AS toplam
    FROM income
    WHERE date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY ay ASC
");
$aylikGelirler = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Son 6 ay gider */
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') AS ay,
        COALESCE(SUM(amount), 0) AS toplam
    FROM expense
    WHERE date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY ay ASC
");
$aylikGiderler = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Son 6 ay beklenen tahsilat */
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(due_date, '%Y-%m') AS ay,
        COALESCE(SUM(amount), 0) AS toplam
    FROM project_payments
    WHERE due_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(due_date, '%Y-%m')
    ORDER BY ay ASC
");
$aylikBeklenenTahsilatlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Son 6 ay gerçek tahsilat */
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') AS ay,
        COALESCE(SUM(amount), 0) AS toplam
    FROM income
    WHERE project_id IS NOT NULL
      AND date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY ay ASC
");
$aylikGercekTahsilatlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Gider kategorileri pasta grafik */
$stmt = $pdo->query("
    SELECT 
        COALESCE(ec.name, 'Kategori Silinmiş') AS category_name,
        COALESCE(SUM(e.amount), 0) AS total
    FROM expense e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    GROUP BY e.category_id, ec.name
    HAVING total > 0
    ORDER BY total DESC
");
$giderKategoriDagilim = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Ayları birleştir */
$tumAylar = [];
foreach ($aylikGelirler as $row) {
    $tumAylar[$row['ay']] = true;
}
foreach ($aylikGiderler as $row) {
    $tumAylar[$row['ay']] = true;
}
foreach ($aylikBeklenenTahsilatlar as $row) {
    $tumAylar[$row['ay']] = true;
}
foreach ($aylikGercekTahsilatlar as $row) {
    $tumAylar[$row['ay']] = true;
}

$tumAylar = array_keys($tumAylar);
sort($tumAylar);

/* Haritalar */
$gelirMap = [];
$giderMap = [];
$beklenenMap = [];
$gercekMap = [];

foreach ($aylikGelirler as $row) {
    $gelirMap[$row['ay']] = (float)$row['toplam'];
}
foreach ($aylikGiderler as $row) {
    $giderMap[$row['ay']] = (float)$row['toplam'];
}
foreach ($aylikBeklenenTahsilatlar as $row) {
    $beklenenMap[$row['ay']] = (float)$row['toplam'];
}
foreach ($aylikGercekTahsilatlar as $row) {
    $gercekMap[$row['ay']] = (float)$row['toplam'];
}

$chartLabels = [];
$chartGelir = [];
$chartGider = [];
$chartNet = [];
$chartBeklenen = [];
$chartGercek = [];

foreach ($tumAylar as $ay) {
    $dt = DateTime::createFromFormat('Y-m', $ay);
    $label = $dt ? $dt->format('m.Y') : $ay;

    $gelir = $gelirMap[$ay] ?? 0;
    $gider = $giderMap[$ay] ?? 0;
    $beklenen = $beklenenMap[$ay] ?? 0;
    $gercek = $gercekMap[$ay] ?? 0;

    $chartLabels[] = $label;
    $chartGelir[] = $gelir;
    $chartGider[] = $gider;
    $chartNet[] = $gelir - $gider;
    $chartBeklenen[] = $beklenen;
    $chartGercek[] = $gercek;
}

$pieLabels = [];
$pieData = [];

foreach ($giderKategoriDagilim as $row) {
    $pieLabels[] = $row['category_name'];
    $pieData[] = (float)$row['total'];
}

function tl($v)
{
    return number_format((float)$v, 2, ',', '.') . ' TL';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">Genel finans, tahsilat ve proje özeti</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/income/income_add.php" class="btn btn-success w-100 py-3 fw-semibold">
                + Gelir Ekle
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/expense/expense_add.php" class="btn btn-danger w-100 py-3 fw-semibold">
                + Gider Ekle
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/projects/project_add.php" class="btn btn-primary w-100 py-3 fw-semibold">
                + Proje Ekle
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/cari/cari_add.php" class="btn btn-dark w-100 py-3 fw-semibold">
                + Müşteri Ekle
            </a>
        </div>
    </div>

    <?php if ($gecikenTaksitSayisi > 0): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
            <strong>Dikkat:</strong> <?php echo $gecikenTaksitSayisi; ?> adet gecikmiş taksit bulunuyor.
            Toplam gecikmiş tutar: <strong><?php echo tl($gecikenTaksitTutari); ?></strong>
        </div>
    <?php endif; ?>

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
                    <small class="text-muted d-block mb-2">Toplam Cari</small>
                    <div class="fs-4 fw-bold text-primary"><?php echo $toplamCariSayisi; ?></div>
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
                    <small class="text-muted d-block mb-2">Tahsilat Oranı</small>
                    <div class="fs-4 fw-bold text-warning">%<?php echo number_format($tahsilatOrani, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Tahsilat Performansı</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-2">Bu Ay Beklenen Tahsilat</small>
                        <div class="fs-5 fw-bold text-warning"><?php echo tl($buAyBeklenenTahsilat); ?></div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block mb-2">Bu Ay Gerçekleşen Tahsilat</small>
                        <div class="fs-5 fw-bold text-success"><?php echo tl($buAyTahsilat); ?></div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block mb-2">Eksik Kalan Tutar</small>
                        <div class="fs-5 fw-bold text-danger"><?php echo tl($eksikTahsilat); ?></div>
                    </div>

                    <div class="mb-2 d-flex justify-content-between">
                        <small class="text-muted">Performans</small>
                        <small class="fw-semibold">%<?php echo number_format($tahsilatPerformansYuzdesi, 2, ',', '.'); ?></small>
                    </div>

                    <div class="progress" style="height: 12px;">
                        <div
                            class="progress-bar <?php echo $tahsilatPerformansYuzdesi >= 70 ? 'bg-success' : ($tahsilatPerformansYuzdesi >= 40 ? 'bg-warning' : 'bg-danger'); ?>"
                            role="progressbar"
                            style="width: <?php echo $tahsilatPerformansYuzdesi; ?>%;"
                            aria-valuenow="<?php echo $tahsilatPerformansYuzdesi; ?>"
                            aria-valuemin="0"
                            aria-valuemax="100">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Gider Kategorileri Dağılımı</h2>
                </div>
                <div class="card-body">
                    <canvas id="expensePieChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">En Çok Gelir Getiren Kategori</small>
                    <div class="fw-semibold mb-2">
                        <?php echo htmlspecialchars($enCokGelirKategori['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="fs-5 fw-bold text-success"><?php echo tl($enCokGelirKategori['total'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">En Çok Gider Olan Kategori</small>
                    <div class="fw-semibold mb-2">
                        <?php echo htmlspecialchars($enCokGiderKategori['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="fs-5 fw-bold text-danger"><?php echo tl($enCokGiderKategori['total'] ?? 0); ?></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">En Çok Tahsilat Gelen Proje</small>
                    <div class="fw-semibold mb-2">
                        <?php echo htmlspecialchars($enCokTahsilatProje['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="fs-5 fw-bold text-primary"><?php echo tl($enCokTahsilatProje['total'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Son 6 Ay Gelir / Gider / Net Durum</h2>
                </div>
                <div class="card-body">
                    <canvas id="financeChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Tahsilat Karşılaştırması</h2>
                </div>
                <div class="card-body">
                    <canvas id="collectionChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
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
                                    <th>Vade</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($yaklasanTaksitler)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($yaklasanTaksitler as $taksit): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($taksit['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($taksit['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
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

        <div class="col-12 col-xl-4">
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

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h5 mb-0 fw-semibold">Son Giderler</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Kategori</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sonGiderler)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sonGiderler as $gider): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($gider['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($gider['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="fw-semibold text-danger"><?php echo tl($gider['amount']); ?></td>
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

<?php
$pageScripts = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const financeCtx = document.getElementById("financeChart");
    if (financeCtx) {
        new Chart(financeCtx, {
            type: "bar",
            data: {
                labels: ' . json_encode($chartLabels, JSON_UNESCAPED_UNICODE) . ',
                datasets: [
                    {
                        label: "Gelir",
                        data: ' . json_encode($chartGelir, JSON_UNESCAPED_UNICODE) . ',
                        backgroundColor: "rgba(25, 135, 84, 0.75)",
                        borderColor: "rgba(25, 135, 84, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: "Gider",
                        data: ' . json_encode($chartGider, JSON_UNESCAPED_UNICODE) . ',
                        backgroundColor: "rgba(220, 53, 69, 0.75)",
                        borderColor: "rgba(220, 53, 69, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: "Net",
                        data: ' . json_encode($chartNet, JSON_UNESCAPED_UNICODE) . ',
                        type: "line",
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: "top"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    const collectionCtx = document.getElementById("collectionChart");
    if (collectionCtx) {
        new Chart(collectionCtx, {
            type: "bar",
            data: {
                labels: ' . json_encode($chartLabels, JSON_UNESCAPED_UNICODE) . ',
                datasets: [
                    {
                        label: "Beklenen",
                        data: ' . json_encode($chartBeklenen, JSON_UNESCAPED_UNICODE) . ',
                        backgroundColor: "rgba(255, 193, 7, 0.75)",
                        borderColor: "rgba(255, 193, 7, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: "Gerçekleşen",
                        data: ' . json_encode($chartGercek, JSON_UNESCAPED_UNICODE) . ',
                        backgroundColor: "rgba(13, 110, 253, 0.75)",
                        borderColor: "rgba(13, 110, 253, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: "top"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    const pieCtx = document.getElementById("expensePieChart");
    if (pieCtx) {
        new Chart(pieCtx, {
            type: "pie",
            data: {
                labels: ' . json_encode($pieLabels, JSON_UNESCAPED_UNICODE) . ',
                datasets: [
                    {
                        data: ' . json_encode($pieData, JSON_UNESCAPED_UNICODE) . ',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: "bottom"
                    }
                }
            }
        });
    }
});
</script>
';

require_once __DIR__ . '/includes/footer.php';
?>