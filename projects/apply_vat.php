<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: project_list.php');
    exit;
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if ($projectId <= 0) {
    header('Location: project_list.php?error=gecersiz_proje');
    exit;
}

try {
    $pdo->beginTransaction();

    // Projeyi çek
    $stmt = $pdo->prepare("
        SELECT *
        FROM projects
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        $pdo->rollBack();
        header('Location: project_list.php?error=proje_bulunamadi');
        exit;
    }

    if ((int)$project['is_active'] !== 1) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $projectId . '&error=pasif_projeye_kdv_uygulanamaz');
        exit;
    }

    if ((int)$project['has_vat'] === 1) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $projectId . '&error=kdv_zaten_uygulanmis');
        exit;
    }

    // Ödenmiş taksit var mı?
    $stmtPaid = $pdo->prepare("
        SELECT COUNT(*)
        FROM project_payments
        WHERE project_id = :project_id
          AND is_paid = 1
    ");
    $stmtPaid->execute(['project_id' => $projectId]);
    $paidCount = (int)$stmtPaid->fetchColumn();

    if ($paidCount > 0) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $projectId . '&error=odeme_baslamis_projeye_kdv_uygulanamaz');
        exit;
    }

    // Varsayılan KDV oranı
    $stmtSettings = $pdo->query("
        SELECT default_vat_rate
        FROM settings
        ORDER BY id ASC
        LIMIT 1
    ");
    $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);

    $vatRate = isset($settings['default_vat_rate']) ? (float)$settings['default_vat_rate'] : 0;

    if ($vatRate <= 0) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $projectId . '&error=gecerli_kdv_orani_yok');
        exit;
    }

    // Taksitleri çek
    $stmtPayments = $pdo->prepare("
        SELECT *
        FROM project_payments
        WHERE project_id = :project_id
        ORDER BY installment_no ASC
    ");
    $stmtPayments->execute(['project_id' => $projectId]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    if (!$payments || count($payments) === 0) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $projectId . '&error=taksit_kayitlari_uyusmuyor');
        exit;
    }

    $stmtUpdatePayment = $pdo->prepare("
        UPDATE project_payments
        SET net_amount = :net_amount,
            vat_rate = :vat_rate,
            vat_amount = :vat_amount,
            amount = :amount
        WHERE id = :id
    ");

    foreach ($payments as $payment) {
        $netAmount = isset($payment['net_amount']) ? (float)$payment['net_amount'] : 0;

        // Eski kayıt uyumu: net_amount boşsa amount'u net kabul et
        if ($netAmount <= 0) {
            $netAmount = (float)$payment['amount'];
        }

        $vatAmount = round($netAmount * $vatRate / 100, 2);
        $grossAmount = round($netAmount + $vatAmount, 2);

        $stmtUpdatePayment->execute([
            'net_amount' => $netAmount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $grossAmount,
            'id' => $payment['id']
        ]);
    }

    // Proje özet alanlarını taksitlerden yeniden hesapla
    $stmtTotals = $pdo->prepare("
        SELECT
            COALESCE(SUM(net_amount), 0) AS total_net,
            COALESCE(SUM(vat_amount), 0) AS total_vat,
            COALESCE(SUM(amount), 0) AS grand_total
        FROM project_payments
        WHERE project_id = :project_id
    ");
    $stmtTotals->execute(['project_id' => $projectId]);
    $totals = $stmtTotals->fetch(PDO::FETCH_ASSOC);

    $totalNet = (float)($totals['total_net'] ?? 0);
    $totalVat = (float)($totals['total_vat'] ?? 0);
    $grandTotal = (float)($totals['grand_total'] ?? 0);

    $stmtUpdateProject = $pdo->prepare("
        UPDATE projects
        SET total_amount = :total_amount,
            has_vat = 1,
            vat_rate = :vat_rate,
            vat_amount = :vat_amount,
            grand_total = :grand_total,
            vat_applied_at = NOW()
        WHERE id = :id
    ");

    $stmtUpdateProject->execute([
        'total_amount' => $totalNet,
        'vat_rate' => $vatRate,
        'vat_amount' => $totalVat,
        'grand_total' => $grandTotal,
        'id' => $projectId
    ]);

    $pdo->commit();

    header('Location: project_detail.php?id=' . $projectId . '&success=kdv_uygulandi');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: project_detail.php?id=' . $projectId . '&error=beklenmeyen_hata');
    exit;
}


require_once __DIR__ . '/../includes/footer.php';
