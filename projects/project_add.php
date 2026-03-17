<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Proje Ekle";

$error = '';
$success = '';
$showCustomerForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add_project';

    if ($action === 'add_customer') {
        $showCustomerForm = true;

        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');
        $customer_note = trim($_POST['customer_note'] ?? '');

        if ($customer_name === '') {
            $error = 'Yeni müşteri eklemek için müşteri adı zorunludur.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cari_accounts (
                    type,
                    name,
                    phone,
                    email,
                    address,
                    note,
                    is_active,
                    created_at
                ) VALUES (
                    :type,
                    :name,
                    :phone,
                    :email,
                    :address,
                    :note,
                    :is_active,
                    :created_at
                )
            ");

            $stmt->execute([
                'type' => 'musteri',
                'name' => $customer_name,
                'phone' => $customer_phone,
                'email' => $customer_email,
                'address' => $customer_address,
                'note' => $customer_note,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $newCustomerId = (int)$pdo->lastInsertId();

            header('Location: project_add.php?customer_added=' . $newCustomerId);
            exit;
        }
    }

    if ($action === 'add_project') {
        $name = trim($_POST['name'] ?? '');
        $total_amount = $_POST['total_amount'] ?? '';
        $installment_count = $_POST['installment_count'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $customer_id = $_POST['customer_id'] ?? '';
        $note = trim($_POST['note'] ?? '');
        $apply_vat = isset($_POST['apply_vat']) ? 1 : 0;

        if ($name === '' || $start_date === '' || $total_amount === '' || $installment_count === '') {
            $error = 'Bu değerleri boş bırakmayınız!';
        } elseif (!is_numeric($total_amount) || !is_numeric($installment_count)) {
            $error = 'Toplam tutar ve taksit değeri sayı olmalı.';
        } elseif ((float)$total_amount <= 0 || (int)$installment_count <= 0) {
            $error = 'Toplam tutar ve taksit sayısı sıfırdan büyük olmalı.';
        } elseif ($customer_id !== '' && !is_numeric($customer_id)) {
            $error = 'Geçersiz müşteri seçimi.';
        } else {
            try {
                $pdo->beginTransaction();

                $total_amount = (float)$total_amount;
                $installment_count = (int)$installment_count;
                $customer_id = ($customer_id !== '' && is_numeric($customer_id)) ? (int)$customer_id : null;

                $has_vat = 0;
                $project_vat_rate = null;
                $project_vat_amount = 0;
                $project_grand_total = $total_amount;
                $vat_applied_at = null;

                if ($apply_vat === 1) {
                    $settingsStmt = $pdo->query("SELECT default_vat_rate FROM settings ORDER BY id ASC LIMIT 1");
                    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

                    $defaultVatRate = isset($settings['default_vat_rate']) ? (float)$settings['default_vat_rate'] : 0;

                    if ($defaultVatRate <= 0) {
                        throw new Exception('Ayarlar kısmında geçerli bir KDV oranı bulunamadı.');
                    }

                    $has_vat = 1;
                    $project_vat_rate = $defaultVatRate;
                    $project_vat_amount = round($total_amount * $project_vat_rate / 100, 2);
                    $project_grand_total = round($total_amount + $project_vat_amount, 2);
                    $vat_applied_at = date('Y-m-d H:i:s');
                }

                $projectStmt = $pdo->prepare("
                    INSERT INTO projects (
                        name,
                        total_amount,
                        has_vat,
                        vat_rate,
                        vat_amount,
                        grand_total,
                        vat_applied_at,
                        installment_count,
                        start_date,
                        customer_id,
                        note,
                        is_active,
                        created_at
                    ) VALUES (
                        :name,
                        :total_amount,
                        :has_vat,
                        :vat_rate,
                        :vat_amount,
                        :grand_total,
                        :vat_applied_at,
                        :installment_count,
                        :start_date,
                        :customer_id,
                        :note,
                        :is_active,
                        :created_at
                    )
                ");

                $projectStmt->execute([
                    'name' => $name,
                    'total_amount' => $total_amount,
                    'has_vat' => $has_vat,
                    'vat_rate' => $project_vat_rate,
                    'vat_amount' => $project_vat_amount,
                    'grand_total' => $project_grand_total,
                    'vat_applied_at' => $vat_applied_at,
                    'installment_count' => $installment_count,
                    'start_date' => $start_date,
                    'customer_id' => $customer_id,
                    'note' => $note,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $projectId = $pdo->lastInsertId();

                $base_net_amount = round($total_amount / $installment_count, 2);
                $last_net_amount = round($total_amount - ($base_net_amount * ($installment_count - 1)), 2);

                $paymentStmt = $pdo->prepare("
                    INSERT INTO project_payments (
                        project_id,
                        installment_no,
                        due_date,
                        net_amount,
                        vat_rate,
                        vat_amount,
                        amount,
                        is_paid,
                        created_at
                    ) VALUES (
                        :project_id,
                        :installment_no,
                        :due_date,
                        :net_amount,
                        :vat_rate,
                        :vat_amount,
                        :amount,
                        :is_paid,
                        :created_at
                    )
                ");

                for ($i = 1; $i <= $installment_count; $i++) {
                    $installment_no = $i;
                    $monthOffset = $i - 1;
                    $due_date = date('Y-m-d', strtotime("+$monthOffset month", strtotime($start_date)));

                    $net_amount = ($i === $installment_count) ? $last_net_amount : $base_net_amount;

                    if ($apply_vat === 1) {
                        $vat_rate = $project_vat_rate;
                        $vat_amount = round($net_amount * $vat_rate / 100, 2);
                        $amount = round($net_amount + $vat_amount, 2);
                    } else {
                        $vat_rate = null;
                        $vat_amount = 0;
                        $amount = $net_amount;
                    }

                    $paymentStmt->execute([
                        'project_id' => $projectId,
                        'installment_no' => $installment_no,
                        'due_date' => $due_date,
                        'net_amount' => $net_amount,
                        'vat_rate' => $vat_rate,
                        'vat_amount' => $vat_amount,
                        'amount' => $amount,
                        'is_paid' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                $pdo->commit();

                header('Location: project_add.php?ok=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

$settingsStmt = $pdo->query("SELECT default_vat_rate FROM settings ORDER BY id ASC LIMIT 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$defaultVatRateText = isset($settings['default_vat_rate']) && (float)$settings['default_vat_rate'] > 0
    ? number_format((float)$settings['default_vat_rate'], 2, ',', '.')
    : '0,00';

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

$selectedCustomerId = $_POST['customer_id'] ?? '';

if (isset($_GET['customer_added']) && is_numeric($_GET['customer_added'])) {
    $selectedCustomerId = (string)(int)$_GET['customer_added'];
    $success = 'Yeni müşteri başarıyla eklendi ve seçildi.';
}

if (isset($_GET['ok'])) {
    $success = 'Proje başarıyla kaydedildi.';
}

if (isset($_GET['show_customer_form'])) {
    $showCustomerForm = true;
}

include_once __DIR__ . "/../includes/header.php";
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success" role="alert">
        <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="<?php echo $showCustomerForm ? 'col-12 col-xl-7' : 'col-12'; ?>">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Proje Ekle</h2>
                <p class="text-muted mb-0">Yeni proje oluşturun ve taksit planını otomatik üretin.</p>
            </div>

            <div class="card-body pt-4">
                <form method="POST">
                    <input type="hidden" name="action" value="add_project">

                    <div class="mb-3">
                        <label for="name" class="form-label">Proje Adı <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_amount" class="form-label">Toplam Tutar (KDV Hariç) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="total_amount"
                                id="total_amount"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['total_amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="installment_count" class="form-label">Taksit Sayısı <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                min="1"
                                name="installment_count"
                                id="installment_count"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['installment_count'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Proje Başlangıç Tarihi <span class="text-danger">*</span></label>
                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['start_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Müşteri</label>
                            <select name="customer_id" id="customer_id" class="form-select">
                                <option value="">Müşteri Seçiniz</option>
                                <?php foreach ($musteriler as $musteri): ?>
                                    <option value="<?php echo (int)$musteri['id']; ?>"
                                        <?php echo ((string)$selectedCustomerId === (string)$musteri['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($musteri['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="mt-2">
                                <a href="project_add.php?show_customer_form=1" class="small text-decoration-none">
                                    + Yeni Müşteri Ekle
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="note" class="form-label">Not</label>
                        <textarea
                            name="note"
                            id="note"
                            class="form-control"
                            rows="4"><?php echo htmlspecialchars($_POST['note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="form-check mb-4">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="apply_vat"
                            value="1"
                            id="apply_vat"
                            <?php echo isset($_POST['apply_vat']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="apply_vat">
                            Bu projeye varsayılan KDV uygula (<?php echo '%' . $defaultVatRateText; ?>)
                        </label>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($showCustomerForm): ?>
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h2 class="h4 mb-1">Hızlı Müşteri Ekle</h2>
                    <p class="text-muted mb-0">Projeden çıkmadan yeni müşteri oluşturun.</p>
                </div>

                <div class="card-body pt-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_customer">

                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Müşteri Adı <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="customer_name"
                                id="customer_name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="customer_phone" class="form-label">Telefon</label>
                            <input
                                type="text"
                                name="customer_phone"
                                id="customer_phone"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="customer_email" class="form-label">E-posta</label>
                            <input
                                type="text"
                                name="customer_email"
                                id="customer_email"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['customer_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="customer_address" class="form-label">Adres</label>
                            <textarea
                                name="customer_address"
                                id="customer_address"
                                class="form-control"
                                rows="3"><?php echo htmlspecialchars($_POST['customer_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="customer_note" class="form-label">Not</label>
                            <textarea
                                name="customer_note"
                                id="customer_note"
                                class="form-control"
                                rows="3"><?php echo htmlspecialchars($_POST['customer_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-success">Müşteriyi Kaydet</button>
                            <a href="project_add.php" class="btn btn-outline-secondary">Vazgeç</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>