<?php
require_once __DIR__ . '/../includes/auth_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Geçersiz proje ID');
}

$projectId = (int)$_GET['id'];


$settingsStmt = $pdo->query("
    SELECT *
    FROM settings
    ORDER BY id ASC
    LIMIT 1
");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

$projectStmt = $pdo->prepare("
    SELECT 
        projects.*,
        cari_accounts.name AS customer_name,
        cari_accounts.phone AS customer_phone,
        cari_accounts.email AS customer_email,
        cari_accounts.address AS customer_address
    FROM projects
    LEFT JOIN cari_accounts ON projects.customer_id = cari_accounts.id
    WHERE projects.id = :id
    LIMIT 1
");
$projectStmt->execute(['id' => $projectId]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    exit('Proje bulunamadı');
}

$paymentsStmt = $pdo->prepare("
    SELECT *
    FROM project_payments
    WHERE project_id = :project_id
    ORDER BY installment_no ASC
");
$paymentsStmt->execute(['project_id' => $projectId]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalNet = 0;
$totalVat = 0;
$grandTotal = 0;
$paidTotal = 0;

foreach ($payments as $payment) {
    $netAmount = isset($payment['net_amount']) ? (float)$payment['net_amount'] : 0;
    $vatAmount = isset($payment['vat_amount']) ? (float)$payment['vat_amount'] : 0;
    $grossAmount = (float)$payment['amount'];

    if ($netAmount <= 0) {
        $netAmount = $grossAmount;
    }

    $totalNet += $netAmount;
    $totalVat += $vatAmount;
    $grandTotal += $grossAmount;

    if ((int)$payment['is_paid'] === 1) {
        $paidTotal += $grossAmount;
    }
}

$remainingTotal = $grandTotal - $paidTotal;
if ($remainingTotal < 0) {
    $remainingTotal = 0;
}

$invoiceNo = 'PRJ-' . str_pad((string)$project['id'], 6, '0', STR_PAD_LEFT);
$issueDate = date('d.m.Y');

function tl($value)
{
    return number_format((float)$value, 2, ',', '.') . ' TL';
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Fatura / Proje Belgesi - <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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
            font-weight: 700;
            letter-spacing: 0.3px;
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

        .invoice-wrap {
            padding: 28px;
        }

        .invoice-head {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            align-items: start;
            margin-bottom: 28px;
        }

        .brand-box {
            display: flex;
            gap: 18px;
            align-items: flex-start;
        }

        .brand-logo {
            width: 82px;
            height: 82px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .brand-placeholder {
            color: #9ca3af;
            font-size: 12px;
            text-align: center;
            padding: 8px;
        }

        .brand-content h2 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #111827;
        }

        .brand-content p {
            margin: 4px 0;
            color: #4b5563;
            line-height: 1.5;
        }

        .meta-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            background: #fafafa;
        }

        .meta-card h3 {
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
            padding-bottom: 0;
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

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 26px;
        }

        .info-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            background: #fff;
        }

        .info-card h3 {
            margin: 0 0 12px;
            font-size: 17px;
            color: #111827;
        }

        .info-card p {
            margin: 6px 0;
            color: #374151;
            line-height: 1.5;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 26px;
        }

        .summary-card {
            border-radius: 16px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
            border: 1px solid #e5e7eb;
        }

        .summary-card small {
            display: block;
            color: #6b7280;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .summary-card .value {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .table-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .table-title {
            padding: 16px 18px;
            font-size: 17px;
            font-weight: 700;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #111827;
            color: #fff;
            padding: 14px 12px;
            font-size: 13px;
            text-align: left;
            letter-spacing: 0.2px;
        }

        tbody td {
            padding: 13px 12px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
            font-size: 14px;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-unpaid {
            background: #fee2e2;
            color: #991b1b;
        }

        .totals-box {
            margin-top: 20px;
            margin-left: auto;
            width: 360px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f7;
        }

        .totals-row:last-child {
            border-bottom: 0;
        }

        .totals-row strong {
            font-size: 16px;
        }

        .totals-grand {
            background: #111827;
            color: #fff;
        }

        .note-box {
            margin-top: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            background: #fcfcfd;
        }

        .note-box h3 {
            margin: 0 0 10px;
            font-size: 16px;
        }

        .note-box p {
            margin: 0;
            color: #4b5563;
            line-height: 1.6;
            white-space: pre-line;
        }

        .footer-note {
            margin-top: 24px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        @media (max-width: 900px) {

            .invoice-head,
            .info-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .totals-box {
                width: 100%;
            }

            .toolbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .toolbar-actions {
                width: 100%;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            html,
            body {
                background: #fff !important;
                margin: 0;
                padding: 0;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }

            .toolbar {
                display: none !important;
            }

            .invoice-wrap {
                padding: 0 !important;
            }

            .invoice-head,
            .info-grid,
            .summary-grid {
                display: block !important;
            }

            .brand-box,
            .meta-card,
            .info-card,
            .summary-card,
            .table-card,
            .totals-box,
            .note-box {
                page-break-inside: avoid;
                break-inside: avoid;
                margin-bottom: 14px !important;
                box-shadow: none !important;
            }

            .summary-card {
                width: 100% !important;
            }

            .table-card {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            tr,
            td,
            th {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .totals-box {
                width: 100% !important;
                margin-left: 0 !important;
            }

            .footer-note {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <div class="page">
        <div class="toolbar">
            <h1>Proje Fatura / Tahsilat Planı Belgesi</h1>
            <div class="toolbar-actions">
                <button class="btn btn-print" onclick="window.print()">Yazdır / PDF Al</button>
                <a class="btn btn-back" href="../projects/project_detail.php?id=<?php echo (int)$project['id']; ?>">Projeye Dön</a>
            </div>
        </div>

        <div class="invoice-wrap">
            <div class="invoice-head">
                <div class="brand-box">
                    <div class="brand-logo">
                        <?php if (!empty($settings['company_logo'])): ?>
                            <img src="../<?php echo htmlspecialchars($settings['company_logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Firma Logosu">
                        <?php else: ?>
                            <div class="brand-placeholder">LOGO</div>
                        <?php endif; ?>
                    </div>

                    <div class="brand-content">
                        <h2><?php echo htmlspecialchars($settings['company_name'] ?? 'Firma Adı', ENT_QUOTES, 'UTF-8'); ?></h2>

                        <p><?php echo htmlspecialchars($settings['company_address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Telefon: <?php echo htmlspecialchars($settings['company_phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>E-posta: <?php echo htmlspecialchars($settings['company_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Vergi Dairesi: <?php echo htmlspecialchars($settings['company_tax_office'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Vergi No: <?php echo htmlspecialchars($settings['company_tax_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="meta-card">
                    <h3>Belge Bilgileri</h3>

                    <div class="meta-row">
                        <div class="meta-label">Belge No</div>
                        <div class="meta-value"><?php echo htmlspecialchars($invoiceNo, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Oluşturma Tarihi</div>
                        <div class="meta-value"><?php echo $issueDate; ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Proje Adı</div>
                        <div class="meta-value"><?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Başlangıç Tarihi</div>
                        <div class="meta-value"><?php echo htmlspecialchars($project['start_date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Durum</div>
                        <div class="meta-value"><?php echo (int)$project['is_active'] === 1 ? 'Aktif' : 'Pasif'; ?></div>
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <h3>Müşteri Bilgileri</h3>
                    <p><strong>Adı:</strong> <?php echo htmlspecialchars($project['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Telefon:</strong> <?php echo htmlspecialchars($project['customer_phone'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>E-posta:</strong> <?php echo htmlspecialchars($project['customer_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Adres:</strong> <?php echo htmlspecialchars($project['customer_address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <div class="info-card">
                    <h3>Proje Özeti</h3>
                    <p><strong>Proje No:</strong> #<?php echo (int)$project['id']; ?></p>
                    <p><strong>Taksit Sayısı:</strong> <?php echo (int)$project['installment_count']; ?></p>
                    <p><strong>Ödenen Toplam:</strong> <?php echo tl($paidTotal); ?></p>
                    <p><strong>Kalan Toplam:</strong> <?php echo tl($remainingTotal); ?></p>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <small>Toplam Net</small>
                    <div class="value"><?php echo tl($totalNet); ?></div>
                </div>

                <div class="summary-card">
                    <small>Toplam KDV</small>
                    <div class="value"><?php echo tl($totalVat); ?></div>
                </div>

                <div class="summary-card">
                    <small>Genel Toplam</small>
                    <div class="value"><?php echo tl($grandTotal); ?></div>
                </div>

                <div class="summary-card">
                    <small>Ödenen / Kalan</small>
                    <div class="value"><?php echo tl($paidTotal); ?> / <?php echo tl($remainingTotal); ?></div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-title">Taksit / Kalem Detayları</div>

                <table>
                    <thead>
                        <tr>
                            <th>Taksit No</th>
                            <th>Vade Tarihi</th>
                            <th>Net Tutar</th>
                            <th>KDV Oranı</th>
                            <th>KDV Tutarı</th>
                            <th>Genel Tutar</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7">Bu projeye ait ödeme planı bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $rowNet = isset($payment['net_amount']) ? (float)$payment['net_amount'] : 0;
                                $rowVat = isset($payment['vat_amount']) ? (float)$payment['vat_amount'] : 0;
                                $rowGross = (float)$payment['amount'];

                                if ($rowNet <= 0) {
                                    $rowNet = $rowGross;
                                }

                                $rowVatRate = (isset($payment['vat_rate']) && $payment['vat_rate'] !== null && $payment['vat_rate'] !== '')
                                    ? '%' . number_format((float)$payment['vat_rate'], 2, ',', '.')
                                    : '-';
                                ?>
                                <tr>
                                    <td><?php echo (int)$payment['installment_no']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo tl($rowNet); ?></td>
                                    <td><?php echo $rowVatRate; ?></td>
                                    <td><?php echo tl($rowVat); ?></td>
                                    <td><?php echo tl($rowGross); ?></td>
                                    <td>
                                        <?php if ((int)$payment['is_paid'] === 1): ?>
                                            <span class="status status-paid">Ödendi</span>
                                        <?php else: ?>
                                            <span class="status status-unpaid">Ödenmedi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="totals-box">
                <div class="totals-row">
                    <span>Toplam Net</span>
                    <strong><?php echo tl($totalNet); ?></strong>
                </div>
                <div class="totals-row">
                    <span>Toplam KDV</span>
                    <strong><?php echo tl($totalVat); ?></strong>
                </div>
                <div class="totals-row totals-grand">
                    <span>Genel Toplam</span>
                    <strong><?php echo tl($grandTotal); ?></strong>
                </div>
            </div>

            <div class="note-box">
                <h3>Proje Notu</h3>
                <p><?php echo htmlspecialchars($project['note'] ?: 'Not bulunmuyor.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <div class="footer-note">
                Bu belge sistem üzerinden oluşturulmuş proje / tahsilat planı çıktısıdır.
            </div>
        </div>
    </div>

</body>

</html>