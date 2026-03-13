<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Kullanıcı adı ve şifre zorunlu.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $error = "Geçersiz kullanıcı adı veya şifre.";
        } else {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        }

        .brand {
            font-weight: 700;
            letter-spacing: .2px;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 14px;
        }

        label {
            font-size: 14px;
            display: block;
            margin: 10px 0 6px;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #9ca3af;
        }

        .btn {
            width: 100%;
            background: #111827;
            color: #fff;
            border: 0;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 14px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 12px;
            border: 1px solid #fecaca;
        }
    </style>
</head>

<body>

    <div class="card">
        <div class="brand">Muhasebe Paneli</div>
        <div class="muted">Lütfen giriş yapın.</div>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="username">Kullanıcı Adı</label>
            <input type="text" id="username" name="username" autocomplete="username" required>

            <label for="password">Şifre</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button class="btn" type="submit">Giriş Yap</button>
        </form>
    </div>

</body>

</html>