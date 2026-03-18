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

/* Son 6 ay gerçekleşen tahsilat */
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

/* Gider kategorileri dağılımı */
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

<style>
    .dashboard-shell {
        background: #f4f6fb;
        border-radius: 1rem;
    }

    .dashboard-alert {
        border: 0;
        border-radius: 1rem;
        background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
        color: #166534;
    }

    .quick-btn {
        border-radius: 1rem;
        min-height: 78px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.08);
    }

    .stat-card {
        border: 0;
        border-radius: 1rem;
        color: #fff;
        overflow: hidden;
        position: relative;
        box-shadow: 0 0.5rem 1.1rem rgba(0, 0, 0, 0.10);
    }

    .stat-card .card-body {
        padding: 1.2rem 1.25rem;
        position: relative;
        z-index: 2;
    }

    .stat-card .stat-title {
        opacity: .9;
        font-size: .9rem;
        margin-bottom: .35rem;
    }

    .stat-card .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .stat-card .stat-sub {
        margin-top: .45rem;
        font-size: .8rem;
        opacity: .9;
    }


    .stat-green {
        background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    }

    .stat-red {
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    }

    .stat-blue {
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    }

    .stat-amber {
        background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
    }

    .stat-dark {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    }

    .stat-cyan {
        background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    }

    .stat-purple {
        background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
    }

    .stat-slate {
        background: linear-gradient(135deg, #334155 0%, #475569 100%);
    }

    .panel-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.07);
    }

    .panel-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef2f7;
        border-top-left-radius: 1rem !important;
        border-top-right-radius: 1rem !important;
    }

    .mini-stat {
        border-radius: 1rem;
        border: 0;
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.07);
    }

    .mini-stat .value {
        font-size: 1.35rem;
        font-weight: 800;
    }

    .info-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.07);
    }

    .table thead th {
        font-size: .88rem;
        font-weight: 700;
    }

    .progress {
        border-radius: 999px;
        background: #e5e7eb;
    }

    .progress-bar {
        border-radius: 999px;
    }

    @media (max-width: 991.98px) {
        .stat-card .stat-value {
            font-size: 1.45rem;
        }
    }
</style>

<div class="container-fluid py-4 dashboard-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold">Dashboard</h2>
            <p class="text-muted mb-0">Genel finans, tahsilat ve proje özeti</p>
        </div>
    </div>

    <?php if ($gecikenTaksitSayisi > 0): ?>
        <div class="alert dashboard-alert mb-4 shadow-sm" role="alert">
            <strong>Dikkat:</strong> <?php echo $gecikenTaksitSayisi; ?> adet gecikmiş taksit bulunuyor.
            Toplam gecikmiş tutar: <strong><?php echo tl($gecikenTaksitTutari); ?></strong>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/income/income_add.php" class="quick-btn btn btn-success w-100">+ Gelir Ekle</a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/expense/expense_add.php" class="quick-btn btn btn-danger w-100">+ Gider Ekle</a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/projects/project_add.php" class="quick-btn btn btn-primary w-100">+ Proje Ekle</a>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="/cari/cari_add.php" class="quick-btn btn btn-dark w-100">+ Müşteri Ekle</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-green h-100">
                <div class="card-body">
                    <div class="stat-title">Toplam Gelir</div>
                    <div class="stat-value"><?php echo tl($toplamGelir); ?></div>
                    <div class="stat-sub">Sistemdeki toplam gelir kaydı</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-red h-100">
                <div class="card-body">
                    <div class="stat-title">Toplam Gider</div>
                    <div class="stat-value"><?php echo tl($toplamGider); ?></div>
                    <div class="stat-sub">Sistemdeki toplam gider kaydı</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card <?php echo $netDurum >= 0 ? 'stat-blue' : 'stat-dark'; ?> h-100">
                <div class="card-body">
                    <div class="stat-title">Net Durum</div>
                    <div class="stat-value"><?php echo tl($netDurum); ?></div>
                    <div class="stat-sub">Gelir - gider farkı</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-amber h-100">
                <div class="card-body">
                    <div class="stat-title">Kalan Alacak</div>
                    <div class="stat-value"><?php echo tl($kalanAlacak); ?></div>
                    <div class="stat-sub">Henüz tahsil edilmemiş toplam tutar</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-slate h-100">
                <div class="card-body">
                    <div class="stat-title">Aktif Proje</div>
                    <div class="stat-value"><?php echo $aktifProjeSayisi; ?></div>
                    <div class="stat-sub">Aktif durumdaki proje sayısı</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-cyan h-100">
                <div class="card-body">
                    <div class="stat-title">Toplam Cari</div>
                    <div class="stat-value"><?php echo $toplamCariSayisi; ?></div>
                    <div class="stat-sub">Müşteri ve tedarikçi toplamı</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-red h-100">
                <div class="card-body">
                    <div class="stat-title">Geciken Taksit</div>
                    <div class="stat-value"><?php echo $gecikenTaksitSayisi; ?></div>
                    <div class="stat-sub"><?php echo tl($gecikenTaksitTutari); ?> gecikmiş tutar</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card stat-purple h-100">
                <div class="card-body">
                    <div class="stat-title">Genel Tahsilat Oranı</div>
                    <div class="stat-value">%<?php echo number_format($tahsilatOrani, 2, ',', '.'); ?></div>
                    <div class="stat-sub">Tüm proje planlarına göre tahsilat</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card panel-card h-100">
                <div class="card-header pt-4 pb-3">
                    <h2 class="h5 mb-0 fw-semibold">Son 6 Ay Gelir / Gider / Net Durum</h2>
                </div>
                <div class="card-body">
                    <canvas id="financeChart" height="95"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="row g-4">
                <div class="col-12">
                    <div class="card info-card h-100">
                        <div class="card-header bg-white border-0 pt-4 pb-0">
                            <h2 class="h5 mb-0 fw-semibold">Bu Ay Tahsilat Performansı</h2>
                        </div>
                        <div class="card-body pt-4">
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Beklenen Tahsilat</small>
                                <div class="value text-warning"><?php echo tl($buAyBeklenenTahsilat); ?></div>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Gerçekleşen Tahsilat</small>
                                <div class="value text-success"><?php echo tl($buAyTahsilat); ?></div>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Eksik Kalan</small>
                                <div class="value text-danger"><?php echo tl($eksikTahsilat); ?></div>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <small class="text-muted">Performans</small>
                                <small class="fw-bold">%<?php echo number_format($tahsilatPerformansYuzdesi, 2, ',', '.'); ?></small>
                            </div>

                            <div class="progress" style="height:12px;">
                                <div
                                    class="progress-bar <?php echo $tahsilatPerformansYuzdesi >= 70 ? 'bg-success' : ($tahsilatPerformansYuzdesi >= 40 ? 'bg-warning' : 'bg-danger'); ?>"
                                    role="progressbar"
                                    style="width: <?php echo $tahsilatPerformansYuzdesi; ?>%;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card mini-stat h-100">
                        <div class="card-body">
                            <small class="text-muted d-block mb-1">En Çok Gelir Getiren Kategori</small>
                            <div class="fw-semibold mb-2"><?php echo htmlspecialchars($enCokGelirKategori['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="value text-success"><?php echo tl($enCokGelirKategori['total'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card mini-stat h-100">
                        <div class="card-body">
                            <small class="text-muted d-block mb-1">En Çok Gider Olan Kategori</small>
                            <div class="fw-semibold mb-2"><?php echo htmlspecialchars($enCokGiderKategori['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="value text-danger"><?php echo tl($enCokGiderKategori['total'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card mini-stat h-100">
                        <div class="card-body">
                            <small class="text-muted d-block mb-1">En Çok Tahsilat Gelen Proje</small>
                            <div class="fw-semibold mb-2"><?php echo htmlspecialchars($enCokTahsilatProje['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="value text-primary"><?php echo tl($enCokTahsilatProje['total'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-4">
            <div class="card panel-card h-100">
                <div class="card-header pt-4 pb-3">
                    <h2 class="h5 mb-0 fw-semibold">Gider Kategorileri Dağılımı</h2>
                </div>
                <div class="card-body">
                    <canvas id="expensePieChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card panel-card h-100">
                <div class="card-header pt-4 pb-3">
                    <h2 class="h5 mb-0 fw-semibold">Beklenen / Gerçekleşen Tahsilat</h2>
                </div>
                <div class="card-body">
                    <canvas id="collectionChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card panel-card h-100">
                <div class="card-header pt-4 pb-3">
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
            <div class="card panel-card h-100">
                <div class="card-header pt-4 pb-3">
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
            <div class="card panel-card h-100">
                <div class="card-header pt-4 pb-3">
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
                        backgroundColor: "rgba(34, 197, 94, 0.75)",
                        borderColor: "rgba(34, 197, 94, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: "Gider",
                        data: ' . json_encode($chartGider, JSON_UNESCAPED_UNICODE) . ',
                        backgroundColor: "rgba(239, 68, 68, 0.75)",
                        borderColor: "rgba(239, 68, 68, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: "Net",
                        data: ' . json_encode($chartNet, JSON_UNESCAPED_UNICODE) . ',
                        type: "line",
                        borderWidth: 2,
                        tension: 0.35,
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
                        backgroundColor: "rgba(245, 158, 11, 0.75)",
                        borderColor: "rgba(245, 158, 11, 1)",
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: "Gerçekleşen",
                        data: ' . json_encode($chartGercek, JSON_UNESCAPED_UNICODE) . ',
                        backgroundColor: "rgba(59, 130, 246, 0.75)",
                        borderColor: "rgba(59, 130, 246, 1)",
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
            type: "doughnut",
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
                cutout: "55%",
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