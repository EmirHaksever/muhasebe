<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = "Müşteri Ekle";

$error = '';

$type = $_POST['type'] ?? '';
$name = $_POST['name'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$note = $_POST['note'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === '' || trim($name) === '') {
        $error = 'Cari tipi ve cari adı zorunludur.';
    } elseif ($type !== 'musteri' && $type !== 'tedarikci') {
        $error = 'Geçersiz cari tipi.';
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
            'type' => $type,
            'name' => trim($name),
            'phone' => trim($phone),
            'email' => trim($email),
            'address' => trim($address),
            'note' => trim($note),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        header('Location: cari_add.php?ok=1');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success" role="alert">
        Müşteri hesap başarıyla eklendi.
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Müşteri Hesap Ekle</h2>
                <p class="text-muted mb-0">Yeni müşteri veya tedarikçi kartı oluşturun.</p>
            </div>

            <div class="card-body pt-4">
                <form method="POST">
                    <div class="mb-3">
                        <label for="type" class="form-label">Müşteri Tipi <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <option value="musteri" <?php echo ($type === 'musteri') ? 'selected' : ''; ?>>Müşteri</option>
                            <option value="tedarikci" <?php echo ($type === 'tedarikci') ? 'selected' : ''; ?>>Tedarikçi</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Müşteri Adı <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="form-control"
                            value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                            required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input
                                type="text"
                                name="phone"
                                id="phone"
                                class="form-control"
                                value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input
                                type="text"
                                name="email"
                                id="email"
                                class="form-control"
                                value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Adres</label>
                        <textarea
                            name="address"
                            id="address"
                            rows="4"
                            class="form-control"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="note" class="form-label">Not</label>
                        <textarea
                            name="note"
                            id="note"
                            rows="4"
                            class="form-control"><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="cari_list.php" class="btn btn-outline-secondary">Müşteri Listesi</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>