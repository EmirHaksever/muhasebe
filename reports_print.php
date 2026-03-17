<?php
require_once __DIR__ . '/includes/auth_check.php';

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$whereIncome = [];
$paramsIncome = [];

$whereExpense = [];
$paramsExpense = [];

if ($start !== '') {
    $whereIncome[] = "income.date >= :start";
    $paramsIncome['start'] = $start;

    $whereExpense[] = "expense.date >= :start";
    $paramsExpense['start'] = $start;
}

if ($end !== '') {
    $whereIncome[] = "income.date <= :end";
    $paramsIncome['end'] = $end;

    $whereExpense[] = "expense.date <= :end";
    $paramsExpense['end'] = $end;
}

/* Firma bilgileri */
$settingsStmt = $pdo->query("
    SELECT *
    FROM settings
    ORDER BY id ASC
    LIMIT 1
");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

/* Toplam gelir */
$gelirSql = "SELECT COALESCE(SUM(amount),0) FROM income";
if (!empty($whereIncome)) {
    $gelirSql .= " WHERE " . implode(" AND ", $whereIncome);
}
$stmt = $pdo->prepare($gelirSql);
$stmt->execute($paramsIncome);
$toplamGelir = (float)$stmt->fetchColumn();

/* Toplam gider */
$giderSql = "SELECT COALESCE(SUM(amount),0) FROM expense";
if (!empty($whereExpense)) {
    $giderSql .= " WHERE " . implode(" AND ", $whereExpense);
}
$stmt = $pdo->prepare($giderSql);
$stmt->execute($paramsExpense);
$toplamGider = (float)$stmt->fetchColumn();

$netKazanc = $toplamGelir - $toplamGider;

$netKarMarji = 0;
if ($toplamGelir > 0) {
    $netKarMarji = ($netKazanc / $toplamGelir) * 100;
}

/* Gelir kategorileri */
$gelirKategoriSql = "
    SELECT 
        income_categories.name AS category_name,
        COALESCE(SUM(income.amount),0) AS total
    FROM income
    LEFT JOIN income_categories ON income.category_id = income_categories.id
";
if (!empty($whereIncome)) {
    $gelirKategoriSql .= " WHERE " . implode(" AND ", $whereIncome);
}
$gelirKategoriSql .= " GROUP BY income.category_id, income_categories.name";
$gelirKategoriSql .= " ORDER BY total DESC";

$stmt = $pdo->prepare($gelirKategoriSql);
$stmt->execute($paramsIncome);
$gelirKategoriToplamlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Gider kategorileri */
$giderKategoriSql = "
    SELECT 
        expense_categories.name AS category_name,
        COALESCE(SUM(expense.amount),0) AS total
    FROM expense
    LEFT JOIN expense_categories ON expense.category_id = expense_categories.id
";
if (!empty($whereExpense)) {
    $giderKategoriSql .= " WHERE " . implode(" AND ", $whereExpense);
}
$giderKategoriSql .= " GROUP BY expense.category_id, expense_categories.name";
$giderKategoriSql .= " ORDER BY total DESC";

$stmt = $pdo->prepare($giderKategoriSql);
$stmt->execute($paramsExpense);
$giderKategoriToplamlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Proje tahsilatları */
$projeTahsilatSql = "
    SELECT 
        projects.name AS project_name,
        COALESCE(SUM(income.amount),0) AS total
    FROM income
    INNER JOIN projects ON income.project_id = projects.id
";
$projeWhere = ["income.project_id IS NOT NULL"];

if (!empty($whereIncome)) {
    $projeWhere = array_merge($projeWhere, $whereIncome);
}

if (!empty($projeWhere)) {
    $projeTahsilatSql .= " WHERE " . implode(" AND ", $projeWhere);
}

$projeTahsilatSql .= " GROUP BY income.project_id, projects.name";
$projeTahsilatSql .= " ORDER BY total DESC";

$stmt = $pdo->prepare($projeTahsilatSql);
$stmt->execute($paramsIncome);
$projeTahsilatToplamlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enCokGelirKategori = $gelirKategoriToplamlari[0] ?? null;
$enCokGiderKategori = $giderKategoriToplamlari[0] ?? null;
$enCokTahsilatProje = $projeTahsilatToplamlari[0] ?? null;

function tl($v)
{
    return number_format((float)$v, 2, ',', '.') . " TL";
}
$cariSayisiStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM cari_accounts
");
$toplamCariSayisi = (int)$cariSayisiStmt->fetchColumn();

$aktifProjeStmt = $pdo->query("
    SELECT COUNT(*)
    FROM projects
    WHERE is_active = 1
");
$toplamAktifProjeSayisi = (int)$aktifProjeStmt->fetchColumn();

$gecikmisTaksitStmt = $pdo->query("
    SELECT COUNT(*)
    FROM project_payments
    WHERE is_paid = 0
      AND due_date < CURDATE()
");
$gecikmisTaksitSayisi = (int)$gecikmisTaksitStmt->fetchColumn();

$odenmemisTaksitTutarStmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM project_payments
    WHERE is_paid = 0
");
$toplamOdenmemisTaksitTutari = (float)$odenmemisTaksitTutarStmt->fetchColumn();

$aylikGelirSql = "
    SELECT 
        DATE_FORMAT(income.date, '%Y-%m') AS ay,
        COALESCE(SUM(income.amount), 0) AS toplam
    FROM income
";

if (!empty($whereIncome)) {
    $aylikGelirSql .= " WHERE " . implode(" AND ", $whereIncome);
}

$aylikGelirSql .= " GROUP BY DATE_FORMAT(income.date, '%Y-%m')";
$aylikGelirSql .= " ORDER BY ay ASC";

$stmt = $pdo->prepare($aylikGelirSql);
$stmt->execute($paramsIncome);
$aylikGelirler = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aylikGiderSql = "
    SELECT 
        DATE_FORMAT(expense.date, '%Y-%m') AS ay,
        COALESCE(SUM(expense.amount), 0) AS toplam
    FROM expense
";

if (!empty($whereExpense)) {
    $aylikGiderSql .= " WHERE " . implode(" AND ", $whereExpense);
}

$aylikGiderSql .= " GROUP BY DATE_FORMAT(expense.date, '%Y-%m')";
$aylikGiderSql .= " ORDER BY ay ASC";

$stmt = $pdo->prepare($aylikGiderSql);
$stmt->execute($paramsExpense);
$aylikGiderler = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tumAylar = [];

foreach ($aylikGelirler as $row) {
    $tumAylar[$row['ay']] = true;
}

foreach ($aylikGiderler as $row) {
    $tumAylar[$row['ay']] = true;
}

$tumAylar = array_keys($tumAylar);
sort($tumAylar);

$gelirMap = [];
$giderMap = [];

foreach ($aylikGelirler as $row) {
    $gelirMap[$row['ay']] = (float)$row['toplam'];
}

foreach ($aylikGiderler as $row) {
    $giderMap[$row['ay']] = (float)$row['toplam'];
}

$chartLabels = [];
$chartIncomeData = [];
$chartExpenseData = [];

foreach ($tumAylar as $ay) {
    $dt = DateTime::createFromFormat('Y-m', $ay);
    $chartLabels[] = $dt ? $dt->format('m.Y') : $ay;
    $chartIncomeData[] = $gelirMap[$ay] ?? 0;
    $chartExpenseData[] = $giderMap[$ay] ?? 0;
}

$raporNo = 'RPR-' . date('Ymd-His');
$olusturmaTarihi = date('d.m.Y H:i');

$tarihAraligi = '-';
if ($start !== '' && $end !== '') {
    $tarihAraligi = date('d.m.Y', strtotime($start)) . ' - ' . date('d.m.Y', strtotime($end));
} elseif ($start !== '') {
    $tarihAraligi = date('d.m.Y', strtotime($start)) . ' sonrası';
} elseif ($end !== '') {
    $tarihAraligi = date('d.m.Y', strtotime($end)) . ' öncesi';
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Genel Şirket Raporu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        .page {
            max-width: 1100px;
            margin: 30px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #111827;
            color: #fff;
        }

        .toolbar h1 {
            margin: 0;
            font-size: 20px;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
        }

        .btn-back {
            background: #e5e7eb;
            color: #111827;
        }

        .content {
            padding: 28px;
        }

        .top-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .brand-card,
        .meta-card,
        .summary-card,
        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
        }

        .brand-card,
        .meta-card {
            padding: 20px;
        }

        .brand-card h2 {
            margin: 0 0 10px;
            font-size: 28px;
            color: #111827;
        }

        .brand-card p {
            margin: 6px 0;
            color: #4b5563;
        }

        .meta-card h3,
        .section-title {
            margin: 0 0 14px;
            font-size: 18px;
            color: #111827;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            border-bottom: 1px dashed #d1d5db;
        }

        .meta-row:last-child {
            border-bottom: 0;
        }

        .meta-label {
            color: #6b7280;
            font-weight: 600;
        }

        .meta-value {
            color: #111827;
            font-weight: 700;
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-grid-6 {
            grid-template-columns: repeat(3, 1fr);
        }

        .print-page-break {
            display: none;
        }

        canvas {
            max-width: 100% !important;
        }

        .summary-card {
            padding: 18px;
        }

        .summary-card small {
            display: block;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: 800;
        }

        .green {
            color: #16a34a;
        }

        .red {
            color: #dc2626;
        }

        .blue {
            color: #2563eb;
        }

        .section-card {
            margin-bottom: 22px;
            overflow: hidden;
        }

        .section-head {
            padding: 16px 18px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .section-body {
            padding: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #111827;
            color: #fff;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eef2f7;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .footer-note {
            margin-top: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        @media (max-width: 900px) {

            .top-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }

            html,
            body {
                background: #fff;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body {
                font-size: 12px;
                color: #111827;
            }

            .page {
                margin: 0;
                max-width: 100%;
                width: 100%;
                box-shadow: none;
                border-radius: 0;
                overflow: visible;
            }

            .toolbar {
                display: none;
            }

            .content {
                padding: 0;
            }

            /* Üst alan 2 kolon kalsın */
            .top-grid {
                display: grid;
                grid-template-columns: 1.4fr 1fr;
                gap: 10px;
                margin-bottom: 10px;
            }

            /* Özet kartlar 3 kolon kalsın */
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                margin-bottom: 10px;
            }

            .brand-card,
            .meta-card,
            .summary-card,
            .section-card {
                break-inside: avoid;
                page-break-inside: avoid;
                box-shadow: none;
                margin-bottom: 0;
            }

            .brand-card,
            .meta-card,
            .summary-card {
                padding: 10px !important;
            }

            .brand-card h2 {
                font-size: 20px;
                margin: 0 0 6px;
            }

            .brand-card p {
                margin: 3px 0;
                font-size: 11px;
            }

            .meta-card h3,
            .section-title {
                font-size: 15px;
                margin: 0 0 8px;
            }

            .meta-row {
                padding: 5px 0;
            }

            .meta-label,
            .meta-value {
                font-size: 11px;
            }

            .summary-card small {
                margin-bottom: 6px;
                font-size: 11px;
            }

            .summary-card .value {
                font-size: 18px;
                line-height: 1.2;
            }

            .section-head {
                padding: 10px 12px;
            }

            .section-body {
                padding: 10px 12px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                padding: 6px 8px;
                font-size: 11px;
            }

            canvas {
                max-width: 100% !important;
                max-height: 180px !important;
            }

            .footer-note {
                margin-top: 8px;
                font-size: 11px;
            }

            .print-page-break {
                display: block;
                page-break-before: always;
                break-before: page;
            }

            .summary-grid-6 {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>

    <div class="page">
        <div class="toolbar">
            <h1>Genel Şirket Raporu</h1>
            <div class="toolbar-actions">
                <button class="btn btn-print" onclick="window.print()">Yazdır / PDF Al</button>
                <a class="btn btn-back" href="reports.php?start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>">Raporlara Dön</a>
            </div>
        </div>

        <div class="content">
            <div class="top-grid">
                <div class="brand-card">
                    <div style="display:flex; gap:18px; align-items:flex-start;">
                        <div style="
            width:70px;
            height:70px;
            border:1px solid #e5e7eb;
            border-radius:14px;
            background:#fafafa;
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            flex-shrink:0;
        ">
                            <?php if (!empty($settings['company_logo'])): ?>
                                <img
                                    src="<?php echo htmlspecialchars($settings['company_logo'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="Firma Logosu"
                                    style="max-width:100%; max-height:100%; object-fit:contain;">
                            <?php else: ?>
                                <span style="font-size:12px; color:#9ca3af;">LOGO</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h2><?php echo htmlspecialchars($settings['company_name'] ?? 'Firma Adı', ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p><?php echo htmlspecialchars($settings['company_address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Telefon: <?php echo htmlspecialchars($settings['company_phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>E-posta: <?php echo htmlspecialchars($settings['company_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Vergi Dairesi: <?php echo htmlspecialchars($settings['company_tax_office'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Vergi No: <?php echo htmlspecialchars($settings['company_tax_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="meta-card">
                    <h3>Rapor Bilgileri</h3>

                    <div class="meta-row">
                        <div class="meta-label">Rapor No</div>
                        <div class="meta-value"><?php echo htmlspecialchars($raporNo, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Oluşturma Tarihi</div>
                        <div class="meta-value"><?php echo $olusturmaTarihi; ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Tarih Aralığı</div>
                        <div class="meta-value"><?php echo htmlspecialchars($tarihAraligi, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Rapor Türü</div>
                        <div class="meta-value">Genel Şirket Raporu</div>
                    </div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <small>Toplam Gelir</small>
                    <div class="value green"><?php echo tl($toplamGelir); ?></div>
                </div>

                <div class="summary-card">
                    <small>Toplam Gider</small>
                    <div class="value red"><?php echo tl($toplamGider); ?></div>
                </div>

                <div class="summary-card">
                    <small>Net Kazanç</small>
                    <div class="value <?php echo $netKazanc >= 0 ? 'green' : 'red'; ?>">
                        <?php echo tl($netKazanc); ?>
                    </div>
                </div>
            </div>
            <div class="summary-grid summary-grid-6">
                <div class="summary-card">
                    <small>Toplam Cari Sayısı</small>
                    <div class="value blue"><?php echo $toplamCariSayisi; ?></div>
                </div>

                <div class="summary-card">
                    <small>Toplam Aktif Proje Sayısı</small>
                    <div class="value green"><?php echo $toplamAktifProjeSayisi; ?></div>
                </div>

                <div class="summary-card">
                    <small>Gecikmiş Taksit Sayısı</small>
                    <div class="value <?php echo $gecikmisTaksitSayisi > 0 ? 'red' : 'green'; ?>">
                        <?php echo $gecikmisTaksitSayisi; ?>
                    </div>
                </div>

                <div class="summary-card">
                    <small>Net Kâr Marjı</small>
                    <div class="value <?php echo $netKarMarji >= 0 ? 'green' : 'red'; ?>">
                        %<?php echo number_format($netKarMarji, 2, ',', '.'); ?>
                    </div>
                </div>

                <div class="summary-card">
                    <small>Ödenmemiş Taksit Tutarı</small>
                    <div class="value red"><?php echo tl($toplamOdenmemisTaksitTutari); ?></div>
                </div>

                <div class="summary-card">
                    <small>Rapor Durumu</small>
                    <div class="value <?php echo $netKazanc >= 0 ? 'green' : 'red'; ?>">
                        <?php echo $netKazanc >= 0 ? 'Pozitif' : 'Negatif'; ?>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <div class="section-title">Aylık Gelir / Gider Grafiği</div>
                </div>
                <div class="section-body">
                    <canvas id="monthlyFinanceChart" height="65"></canvas>

                    <div style="margin-top:12px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ay</th>
                                    <th>Gelir</th>
                                    <th>Gider</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chartLabels as $i => $label): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo tl($chartIncomeData[$i] ?? 0); ?></td>
                                        <td><?php echo tl($chartExpenseData[$i] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="print-page-break"></div>

            <div class="section-card">
                <div class="section-head">
                    <div class="section-title">Gelir Kategorilerine Göre Toplamlar</div>
                </div>
                <div class="section-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($gelirKategoriToplamlari)): ?>
                                <tr>
                                    <td colspan="2">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($gelirKategoriToplamlari as $kategori): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kategori['category_name'] ?? 'Kategori Silinmiş', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo tl($kategori['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <div class="section-title">Gider Kategorilerine Göre Toplamlar</div>
                </div>
                <div class="section-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($giderKategoriToplamlari)): ?>
                                <tr>
                                    <td colspan="2">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($giderKategoriToplamlari as $kategori): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kategori['category_name'] ?? 'Kategori Silinmiş', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo tl($kategori['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <div class="section-title">Projelere Göre Tahsilat Toplamları</div>
                </div>
                <div class="section-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Proje</th>
                                <th>Toplam Tahsilat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projeTahsilatToplamlari)): ?>
                                <tr>
                                    <td colspan="2">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projeTahsilatToplamlari as $proje): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proje['project_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo tl($proje['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="footer-note">
                Bu belge sistem üzerinden oluşturulmuş genel şirket raporu çıktısıdır.
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById("monthlyFinanceChart");

            if (!ctx) return;

            new Chart(ctx, {
                type: "bar",
                data: {
                    labels: <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                            label: "Gelir",
                            data: <?php echo json_encode($chartIncomeData, JSON_UNESCAPED_UNICODE); ?>,
                            backgroundColor: "rgba(22, 163, 74, 0.75)",
                            borderColor: "rgba(22, 163, 74, 1)",
                            borderWidth: 1,
                            borderRadius: 6
                        },
                        {
                            label: "Gider",
                            data: <?php echo json_encode($chartExpenseData, JSON_UNESCAPED_UNICODE); ?>,
                            backgroundColor: "rgba(220, 38, 38, 0.75)",
                            borderColor: "rgba(220, 38, 38, 1)",
                            borderWidth: 1,
                            borderRadius: 6
                        }
                    ]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    layout: {
                        padding: {
                            top: 10
                        }
                    },
                    plugins: {
                        legend: {
                            position: "top",
                            labels: {
                                font: {
                                    size: 12,
                                    weight: "600"
                                }
                            }
                        },
                        datalabels: {
                            anchor: "end",
                            align: "end",
                            offset: 2,
                            color: "#111827",
                            font: {
                                weight: "700",
                                size: 10
                            },
                            formatter: function(value) {
                                if (!value || value === 0) return "";
                                return new Intl.NumberFormat("tr-TR").format(value);
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw || 0;
                                    return context.dataset.label + ": " + new Intl.NumberFormat("tr-TR", {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }).format(value) + " TL";
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: "#374151",
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: "#374151",
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return new Intl.NumberFormat("tr-TR").format(value);
                                }
                            },
                            grid: {
                                color: "rgba(0,0,0,0.08)"
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>