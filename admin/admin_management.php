<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Kullanıcı Yönetimi';

$error = '';
$success = '';
$editUser = null;

/*
|--------------------------------------------------------------------------
| DÜZENLENECEK KULLANICIYI GETİR
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editUser) {
        $error = 'Düzenlenecek kullanıcı bulunamadı.';
    }
}

/*
|--------------------------------------------------------------------------
| FORM İŞLEMLERİ
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | KULLANICI EKLE
    |--------------------------------------------------------------------------
    */
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if ($username === '' || $password === '' || $password_confirm === '') {
            $error = 'Tüm alanları doldurun.';
        } elseif (mb_strlen($username) < 3) {
            $error = 'Kullanıcı adı en az 3 karakter olmalıdır.';
        } elseif (strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($password !== $password_confirm) {
            $error = 'Şifreler uyuşmuyor.';
        } else {
            $kontrol = $pdo->prepare("SELECT id FROM admin WHERE username = :username LIMIT 1");
            $kontrol->execute(['username' => $username]);

            if ($kontrol->fetch()) {
                $error = 'Bu kullanıcı adı zaten kayıtlı.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO admin (
                        username,
                        password_hash,
                        is_active,
                        created_at
                    ) VALUES (
                        :username,
                        :password_hash,
                        :is_active,
                        :created_at
                    )
                ");

                $stmt->execute([
                    'username' => $username,
                    'password_hash' => $passwordHash,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                header('Location: admin_management.php?ok=1');
                exit;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KULLANICI GÜNCELLE
    |--------------------------------------------------------------------------
    */
    if ($action === 'edit') {
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            $error = 'Geçersiz kullanıcı ID.';
        } else {
            $id = (int)$_POST['id'];
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if ($username === '') {
                $error = 'Kullanıcı adı boş bırakılamaz.';
            } else {
                $kontrol = $pdo->prepare("
                    SELECT id 
                    FROM admin 
                    WHERE username = :username
                      AND id != :id
                    LIMIT 1
                ");
                $kontrol->execute([
                    'username' => $username,
                    'id' => $id
                ]);

                if ($kontrol->fetch()) {
                    $error = 'Bu kullanıcı adı başka bir kullanıcıda kayıtlı.';
                } else {
                    if ($password !== '' || $password_confirm !== '') {
                        if (strlen($password) < 6) {
                            $error = 'Yeni şifre en az 6 karakter olmalıdır.';
                        } elseif ($password !== $password_confirm) {
                            $error = 'Şifreler uyuşmuyor.';
                        }
                    }

                    if ($error === '') {
                        if ($password !== '') {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                            $stmt = $pdo->prepare("
                                UPDATE admin
                                SET username = :username,
                                    password_hash = :password_hash,
                                    updated_at = :updated_at
                                WHERE id = :id
                            ");

                            $stmt->execute([
                                'username' => $username,
                                'password_hash' => $passwordHash,
                                'updated_at' => date('Y-m-d H:i:s'),
                                'id' => $id
                            ]);
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE admin
                                SET username = :username,
                                    updated_at = :updated_at
                                WHERE id = :id
                            ");

                            $stmt->execute([
                                'username' => $username,
                                'updated_at' => date('Y-m-d H:i:s'),
                                'id' => $id
                            ]);
                        }

                        header('Location: admin_management.php?updated=1');
                        exit;
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF / PASİF YAP
    |--------------------------------------------------------------------------
    */
    if ($action === 'toggle') {
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            $error = 'Geçersiz kullanıcı ID.';
        } else {
            $id = (int)$_POST['id'];

            // İstersen kendi hesabını pasif yapmayı engelle
            if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $id) {
                $error = 'Kendi hesabınızı pasif yapamazsınız.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE admin
                    SET is_active = NOT is_active,
                        updated_at = :updated_at
                    WHERE id = :id
                ");
                $stmt->execute([
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $id
                ]);

                header('Location: admin_management.php?toggled=1');
                exit;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| MESAJLAR
|--------------------------------------------------------------------------
*/
if (isset($_GET['ok'])) {
    $success = 'Kullanıcı başarıyla eklendi.';
}
if (isset($_GET['updated'])) {
    $success = 'Kullanıcı başarıyla güncellendi.';
}
if (isset($_GET['toggled'])) {
    $success = 'Kullanıcı durumu güncellendi.';
}

/*
|--------------------------------------------------------------------------
| KULLANICI LİSTESİ
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT * FROM admin ORDER BY id DESC");
$stmt->execute();
$kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
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
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <?php if ($editUser): ?>
                    <h2 class="h4 mb-1">Kullanıcı Düzenle</h2>
                    <p class="text-muted mb-0">Kullanıcı adı ve şifreyi güncelleyin.</p>
                <?php else: ?>
                    <h2 class="h4 mb-1">Kullanıcı Ekle</h2>
                    <p class="text-muted mb-0">Yeni yönetici kullanıcısı oluşturun.</p>
                <?php endif; ?>
            </div>

            <div class="card-body pt-4">
                <form method="POST">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo (int)$editUser['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="username" class="form-label">Kullanıcı Adı</label>
                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="form-control"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ($editUser['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label">
                                <?php echo $editUser ? 'Yeni Şifre' : 'Şifre'; ?>
                            </label>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control">
                            <?php if ($editUser): ?>
                                <small class="text-muted">Değiştirmek istemiyorsanız boş bırakın.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="password_confirm" class="form-label">
                                <?php echo $editUser ? 'Yeni Şifre Tekrar' : 'Şifre Tekrar'; ?>
                            </label>
                            <input
                                type="password"
                                name="password_confirm"
                                id="password_confirm"
                                class="form-control">
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <?php if ($editUser): ?>
                            <button type="submit" class="btn btn-primary">Güncelle</button>
                            <a href="admin_management.php" class="btn btn-outline-secondary">İptal</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">Kullanıcı Ekle</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Kullanıcı Listesi</h2>
                <p class="text-muted mb-0">Panel kullanıcılarını görüntüleyin ve yönetin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sıra</th>
                                <th>Kullanıcı Adı</th>
                                <th>Durum</th>
                                <th>Oluşturulma</th>
                                <th>Güncellenme</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kullanicilar)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Kayıtlı kullanıcı bulunamadı.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kullanicilar as $index => $kullanici): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($kullanici['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ((int)$kullanici['is_active'] === 1): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($kullanici['created_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($kullanici['updated_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                                <a href="admin_management.php?edit_id=<?php echo (int)$kullanici['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    Düzenle
                                                </a>

                                                <form method="POST" class="m-0">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int)$kullanici['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        <?php echo (int)$kullanici['is_active'] === 1 ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>