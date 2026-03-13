<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$pageTitle = 'Ayarlar';
$error = '';
$success = '';

// Ayar kaydını çek
$stmt = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Eğer hiç kayıt yoksa otomatik oluştur
if (!$settings) {
    $pdo->exec("
        INSERT INTO settings (
            company_name,
            company_phone,
            company_email,
            company_address,
            company_tax_office,
            company_tax_number,
            company_logo,
            default_vat_rate
        ) VALUES (
            'Firma Adı',
            '',
            '',
            '',
            '',
            '',
            '',
            20.00
        )
    ");

    $stmt = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $company_phone = trim($_POST['company_phone'] ?? '');
    $company_email = trim($_POST['company_email'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');
    $company_tax_office = trim($_POST['company_tax_office'] ?? '');
    $company_tax_number = trim($_POST['company_tax_number'] ?? '');
    $default_vat_rate = trim($_POST['default_vat_rate'] ?? '');
    $company_logo = $settings['company_logo'] ?? '';

    if ($company_name === '') {
        $error = 'Firma adı boş olamaz.';
    } elseif ($default_vat_rate === '' || !is_numeric($default_vat_rate)) {
        $error = 'Geçerli bir KDV oranı giriniz.';
    } else {

        if (isset($_FILES['company_logo']) && !empty($_FILES['company_logo']['name'])) {
            $uploadDir = __DIR__ . '/uploads/settings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $tmpName = $_FILES['company_logo']['tmp_name'];
            $originalName = $_FILES['company_logo']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $error = 'Logo dosyası sadece jpg, jpeg, png veya webp olabilir.';
            } else {
                $newFileName = 'logo_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $company_logo = 'uploads/settings/' . $newFileName;
                } else {
                    $error = 'Logo yüklenemedi.';
                }
            }
        }

        if ($error === '') {
            $update = $pdo->prepare("
                UPDATE settings SET
                    company_name = :company_name,
                    company_phone = :company_phone,
                    company_email = :company_email,
                    company_address = :company_address,
                    company_tax_office = :company_tax_office,
                    company_tax_number = :company_tax_number,
                    company_logo = :company_logo,
                    default_vat_rate = :default_vat_rate
                WHERE id = :id
            ");

            $update->execute([
                'company_name' => $company_name,
                'company_phone' => $company_phone,
                'company_email' => $company_email,
                'company_address' => $company_address,
                'company_tax_office' => $company_tax_office,
                'company_tax_number' => $company_tax_number,
                'company_logo' => $company_logo,
                'default_vat_rate' => $default_vat_rate,
                'id' => $settings['id']
            ]);

            $success = 'Ayarlar başarıyla güncellendi.';

            $stmt = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}
?>

<style>

</style>

<div class="container-fluid settings-page">
    <div class="settings-page-title">
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm settings-card">
        <div class="card-header">
            <h3>Genel Firma Bilgileri</h3>
        </div>

        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="settings-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Firma Adı</label>
                            <input
                                type="text"
                                name="company_name"
                                class="form-control"
                                value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"
                                required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Telefon</label>
                            <input
                                type="text"
                                name="company_phone"
                                class="form-control"
                                value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">E-posta</label>
                            <input
                                type="email"
                                name="company_email"
                                class="form-control"
                                value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Varsayılan KDV Oranı (%)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="default_vat_rate"
                                class="form-control"
                                value="<?= htmlspecialchars($settings['default_vat_rate'] ?? '20.00') ?>"
                                required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Vergi Dairesi</label>
                            <input
                                type="text"
                                name="company_tax_office"
                                class="form-control"
                                value="<?= htmlspecialchars($settings['company_tax_office'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Vergi Numarası</label>
                            <input
                                type="text"
                                name="company_tax_number"
                                class="form-control"
                                value="<?= htmlspecialchars($settings['company_tax_number'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group">
                            <label class="form-label">Adres</label>
                            <textarea
                                name="company_address"
                                class="form-control"
                                rows="4"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Firma Logosu</label>
                            <input
                                type="file"
                                name="company_logo"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Mevcut Logo</label>
                            <div class="settings-logo-preview">
                                <?php if (!empty($settings['company_logo'])): ?>
                                    <img src="<?= htmlspecialchars($settings['company_logo']) ?>" alt="Logo">
                                <?php else: ?>
                                    <span class="text-muted">Henüz logo yüklenmedi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="settings-actions">
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>