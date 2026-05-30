<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        $error = 'Заполните оба поля.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM application WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-wrapper { max-width: 450px; margin: 80px auto; background: white; border-radius: 32px; padding: 30px; box-shadow: 0 20px 35px rgba(0,0,0,0.1); }
        .login-wrapper h2 { text-align: center; color: #1e3a5f; }
        .login-wrapper .input-group { margin-bottom: 20px; }
        .login-wrapper input { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #cbd5e1; }
        .login-wrapper button { width: 100%; padding: 12px; background: #1e3a5f; color: white; border: none; border-radius: 30px; font-weight: bold; cursor: pointer; }
        .login-wrapper button:hover { background: #0f2c45; }
        .back-link { text-align: center; margin-top: 20px; }
        .error { background: #fee2e2; padding: 10px; border-radius: 12px; color: #b91c1c; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="login-wrapper">
    <h2> Вход</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="input-group">
            <label>Логин</label>
            <input type="text" name="login" required>
        </div>
        <div class="input-group">
            <label>Пароль</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Войти</button>
    </form>
    <div class="back-link">
        <a href="index.php">← Вернуться к анкете</a>
    </div>
</div>
</body>
</html>