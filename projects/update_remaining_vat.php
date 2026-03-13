<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: project_list.php');
    exit;
}

$project_id = $_POST['project_id'] ?? '';
$vat_rate = $_POST['vat_rate'] ?? '';

if (!is_numeric($project_id) || !is_numeric($vat_rate)) {
    header('Location: project_list.php?error=gecersiz_veri');
    exit;
}

$project_id = (int)$project_id;
$vat_rate = (float)$vat_rate;

if ($project_id <= 0 || $vat_rate < 0) {
    header('Location: project_list.php?error=gecersiz_veri');
    exit;
}

try {
    $pdo->beginTransaction();

    $projectStmt = $pdo->prepare("
        SELECT *
        FROM projects
        WHERE id = :id
        LIMIT 1
    ");
    $projectStmt->execute(['id' => $project_id]);
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        $pdo->rollBack();
        header('Location: project_list.php?error=proje_bulunamadi');
        exit;
    }

    if ((int)$project['is_active'] !== 1) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $project_id . '&error=pasif_projede_kdv_guncellenemez');
        exit;
    }

    // Ödenmemiş taksitleri çek
    $paymentsStmt = $pdo->prepare("
        SELECT *
        FROM project_payments
        WHERE project_id = :project_id
          AND is_paid = 0
        ORDER BY installment_no ASC
    ");
    $paymentsStmt->execute(['project_id' => $project_id]);
    $unpaidPayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$unpaidPayments || count($unpaidPayments) === 0) {
        $pdo->rollBack();
        header('Location: project_detail.php?id=' . $project_id . '&error=guncellenecek_kalan_taksit_yok');
        exit;
    }

    $updateStmt = $pdo->prepare("
        UPDATE project_payments
        SET vat_rate = :vat_rate,
            vat_amount = :vat_amount,
            amount = :amount
        WHERE id = :id
    ");

    foreach ($unpaidPayments as $payment) {
        $net_amount = (float)$payment['net_amount'];

        // Eski kayıtta net_amount boşsa güvenlik amaçlı amount'u baz al
        if ($net_amount <= 0) {
            $net_amount = (float)$payment['amount'];
        }

        $new_vat_amount = round($net_amount * $vat_rate / 100, 2);
        $new_amount = round($net_amount + $new_vat_amount, 2);

        $updateStmt->execute([
            'vat_rate' => $vat_rate,
            'vat_amount' => $new_vat_amount,
            'amount' => $new_amount,
            'id' => $payment['id']
        ]);
    }

    // Proje özet alanlarını yeniden hesapla
    $totalsStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(net_amount), 0) AS total_net,
            COALESCE(SUM(vat_amount), 0) AS total_vat,
            COALESCE(SUM(amount), 0) AS grand_total
        FROM project_payments
        WHERE project_id = :project_id
    ");
    $totalsStmt->execute(['project_id' => $project_id]);
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);

    $total_net = (float)($totals['total_net'] ?? 0);
    $total_vat = (float)($totals['total_vat'] ?? 0);
    $grand_total = (float)($totals['grand_total'] ?? 0);

    // Eğer projede en az bir taksitte KDV varsa has_vat = 1
    $has_vat = $grand_total > $total_net ? 1 : 0;

    $projectUpdateStmt = $pdo->prepare("
        UPDATE projects
        SET has_vat = :has_vat,
            vat_rate = :vat_rate,
            vat_amount = :vat_amount,
            grand_total = :grand_total,
            vat_applied_at = NOW()
        WHERE id = :id
    ");

    $projectUpdateStmt->execute([
        'has_vat' => $has_vat,
        'vat_rate' => $has_vat ? $vat_rate : null,
        'vat_amount' => $total_vat,
        'grand_total' => $grand_total,
        'id' => $project_id
    ]);

    $pdo->commit();

    header('Location: project_detail.php?id=' . $project_id . '&success=kalan_taksit_kdv_guncellendi');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: project_detail.php?id=' . $project_id . '&error=beklenmeyen_hata');
    exit;
}

require_once __DIR__ . '/../includes/footer.php';
