<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Geçersiz istek');
}

$payment_id = $_POST['payment_id'] ?? '';
$project_id = $_POST['project_id'] ?? '';

if (!is_numeric($payment_id) || !is_numeric($project_id)) {
    exit('Geçersiz veri');
}

$payment_id = (int)$payment_id;
$project_id = (int)$project_id;

$stmt = $pdo->prepare('SELECT * FROM project_payments WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $payment_id]);
$odeme = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$odeme) {
    exit('Ödeme kaydı bulunamadı');
}

$sorgu = $pdo->prepare('SELECT * FROM projects WHERE id =:id LIMIT 1 ');
$sorgu->execute(['id' => $project_id]);
$proje = $sorgu->fetch(PDO::FETCH_ASSOC);
if (!$proje) {
    exit('Proje bulunamadı');
}
$kategoriStmt = $pdo->prepare("SELECT id FROM income_categories WHERE name = :name AND is_active = 1 LIMIT 1");
$kategoriStmt->execute(['name' => 'Proje Tahsilatı']);
$gelirKategori = $kategoriStmt->fetch(PDO::FETCH_ASSOC);

if (!$gelirKategori) {
    exit('Proje Tahsilatı kategorisi bulunamadı');
}

if ($odeme['is_paid'] == 0) {
    $stmt = $pdo->prepare('UPDATE project_payments SET is_paid = :is_paid, paid_at = :paid_at WHERE id = :id');
    $stmt->execute([
        'is_paid' => 1,
        'paid_at' => date('Y-m-d H:i:s'),
        'id' => $payment_id

    ]);
    $kontrolStmt = $pdo->prepare("SELECT id FROM income WHERE payment_id = :payment_id LIMIT 1");
    $kontrolStmt->execute(['payment_id' => $payment_id]);
    $mevcutGelir = $kontrolStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mevcutGelir) {
        $incomeStmt = $pdo->prepare("INSERT INTO income (category_id, project_id, payment_id, date, amount, description, created_at)
    VALUES (:category_id, :project_id, :payment_id, :date, :amount, :description, :created_at)");

        $incomeStmt->execute([
            'category_id' => $gelirKategori['id'],
            'project_id' => $project_id,
            'payment_id' => $payment_id,
            'date' => date('Y-m-d'),
            'amount' => $odeme['amount'],
            'description' => $proje['name'] . ' - ' . $odeme['installment_no'] . '. taksit tahsilatı',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
} else {
    $stmt = $pdo->prepare('UPDATE project_payments SET is_paid = :is_paid, paid_at = :paid_at WHERE id = :id');
    $stmt->execute([
        'is_paid' => 0,
        'paid_at' => null,
        'id' => $payment_id
    ]);
    $deleteStmt = $pdo->prepare("DELETE FROM income WHERE payment_id = :payment_id");
    $deleteStmt->execute(['payment_id' => $payment_id]);
}


header('Location: project_detail.php?id=' . $project_id);
exit;

require_once __DIR__ . '/../includes/footer.php';
