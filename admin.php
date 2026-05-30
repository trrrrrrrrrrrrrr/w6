<?php
require_once 'db.php';

//HTTP Basic Authentication
$auth_login = $_SERVER['PHP_AUTH_USER'] ?? '';
$auth_pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if (empty($auth_login) || empty($auth_pass)) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1>Доступ запрещён</h1><p>Введите логин и пароль администратора.</p>';
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || !password_verify($auth_pass, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1>Неверный логин или пароль</h1>';
    exit;
}

//Обработка действий: удаление, редактирование
$message = '';
$edit_id = null;
$edit_data = null;

//Удаление записи
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM application_language WHERE application_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM application WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $message = "<div class='success'>Анкета №{$id} удалена.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='error'>Ошибка удаления: {$e->getMessage()}</div>";
    }
}

//Загрузка данных для редактирования
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_data) {
        // Загружаем языки
        $lang_stmt = $pdo->prepare("SELECT l.name FROM application_language al JOIN language l ON al.language_id = l.id WHERE al.application_id = ?");
        $lang_stmt->execute([$edit_id]);
        $edit_data['languages'] = $lang_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

//Обработка сохранения изменений (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $biography = trim($_POST['biography'] ?? '');
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];

    $allowed_languages = getAllowedLanguages();
    $allowed_genders = ['male', 'female'];

    $errors = [];
    //Валидация
    //ФИО
    if (empty($full_name)) {
        $errors['full_name'] = 'ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $form_data['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы и пробелы.';
    } elseif (strlen($form_data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    //Телефон
    if (empty($fphone)) {
        $errors['phone'] = 'Телефон обязателен.';
    } else {
        $digits = preg_replace('/\D/', '', $form_data['phone']);
        $digitCount = strlen($digits);
        if ($digitCount < 10 || $digitCount > 12) {
            $errors['phone'] = 'Номер телефона должен содержать от 10 до 12 цифр (например, +7 918 463-42-21).';
        }
    }

    //Email
    if (empty($email)) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email.';
    }

    //Дата рождения
    if (empty($birth_date)) {
        $errors['birth_date'] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $form_data['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $form_data['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата. Используйте формат ГГГГ-ММ-ДД.';
        } elseif ($date > new DateTime('today')) {
            $errors['birth_date'] = 'Дата рождения не может быть позже сегодняшнего дня.';
        }
    }

    //Пол
    if (empty($gender)) {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!in_array($form_data['gender'], $allowed_genders)) {
        $errors['gender'] = 'Недопустимое значение пола.';
    }

    //Языки
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($form_data['languages'] as $lang) {
            if (!in_array($lang, $allowed_languages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    //Биография
    if (strlen($biography) > 10000) {
        $errors['biography'] = 'Биография не должна превышать 10000 символов.';
    }

    //Чекбокс согласия
    if (!$contract_accepted) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE application SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, contract_accepted=? WHERE id=?");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_accepted, $id]);

            //Обновление языков
            $pdo->prepare("DELETE FROM application_language WHERE application_id = ?")->execute([$id]);
            $lang_map = [];
            $stmt = $pdo->query("SELECT id, name FROM language");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $lang_map[$row['name']] = $row['id'];
            $stmt = $pdo->prepare("INSERT INTO application_language (application_id, language_id) VALUES (?, ?)");
            foreach ($languages as $lang) {
                if (isset($lang_map[$lang])) $stmt->execute([$id, $lang_map[$lang]]);
            }
            $pdo->commit();
            $message = "<div class='success'>Анкета №{$id} обновлена.</div>";
            $edit_id = null; //выходим из режима редактирования
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='error'>Ошибка: {$e->getMessage()}</div>";
        }
    } else {
        $message = "<div class='error'>Исправьте ошибки в форме.</div>";
        //оказываем форму редактирования с ошибками (данные в $edit_data сохраняем)
        $edit_data = compact('id', 'full_name', 'phone', 'email', 'birth_date', 'gender', 'biography', 'contract_accepted', 'languages');
    }
}

//Получение списка всех анкет
$applications = [];
$stmt = $pdo->query("
    SELECT a.*, GROUP_CONCAT(l.name SEPARATOR ', ') AS languages
    FROM application a
    LEFT JOIN application_language al ON a.id = al.application_id
    LEFT JOIN language l ON al.language_id = l.id
    GROUP BY a.id
    ORDER BY a.id DESC
");
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

//Статистика по языкам
$stats = [];
$stmt = $pdo->query("
    SELECT l.name, COUNT(DISTINCT al.application_id) AS cnt
    FROM language l
    LEFT JOIN application_language al ON l.id = al.language_id
    GROUP BY l.id, l.name
    ORDER BY cnt DESC, l.name
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление анкетами</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 30px auto; background: white; border-radius: 32px; padding: 30px; box-shadow: 0 20px 30px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #1e3a5f; }
        .admin-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .admin-table th, .admin-table td { border: 1px solid #cbd5e1; padding: 12px; vertical-align: top; }
        .admin-table th { background: #eef2ff; }
        .actions a { margin-right: 10px; text-decoration: none; }
        .edit-link { color: #2563eb; }
        .delete-link { color: #dc2626; }
        .stats-table { margin-top: 30px; width: 50%; border-collapse: collapse; }
        .stats-table th, .stats-table td { border: 1px solid #cbd5e1; padding: 8px; }
        .success { background: #e0f2fe; padding: 10px; border-radius: 12px; margin: 10px 0; }
        .error { background: #fee2e2; padding: 10px; border-radius: 12px; margin: 10px 0; }
        .edit-form { background: #f8fafc; padding: 20px; border-radius: 24px; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border-radius: 12px; border: 1px solid #cbd5e1; }
        .btn { background: #1e3a5f; color: white; border: none; padding: 8px 20px; border-radius: 30px; cursor: pointer; }
        .btn-secondary { background: #64748b; }
        .back-link { margin-top: 30px; text-align: center; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1> управление анкетами</h1>
    <p>Авторизован как <strong><?= htmlspecialchars($auth_login) ?></strong></p>
    <?= $message ?>

    <!-- Форма редактирования -->
    <?php if ($edit_id && $edit_data): ?>
        <div class="edit-form">
            <h2>Редактирование анкеты №<?= $edit_id ?></h2>
            <form method="post">
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($edit_data['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($edit_data['phone'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Дата рождения *</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($edit_data['birth_date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Пол</label>
                    <select name="gender">
                        <option value="male" <?= ($edit_data['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Мужской</option>
                        <option value="female" <?= ($edit_data['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Женский</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Языки программирования</label>
                    <select name="languages[]" multiple size="6">
                        <?php
                        $all_langs = $pdo->query("SELECT name FROM language ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($all_langs as $lang):
                            $selected = in_array($lang, $edit_data['languages'] ?? []) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($lang) ?>" <?= $selected ?>><?= htmlspecialchars($lang) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" rows="4"><?= htmlspecialchars($edit_data['biography'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="contract_accepted" value="1" <?= ($edit_data['contract_accepted'] ?? 0) ? 'checked' : '' ?>> Согласие с контрактом</label>
                </div>
                <button type="submit" class="btn">Сохранить изменения</button>
                <a href="admin.php" class="btn btn-secondary">Отмена</a>
            </form>
        </div>
    <?php endif; ?>

    <!-- Таблица всех анкет -->
    <h2>Все анкеты пользователей</h2>
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рожд.</th>
                    <th>Пол</th><th>Биография</th><th>Согласие</th><th>Языки</th><th>Логин</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?= $app['id'] ?></td>
                    <td><?= htmlspecialchars($app['full_name']) ?></td>
                    <td><?= htmlspecialchars($app['phone']) ?></td>
                    <td><?= htmlspecialchars($app['email']) ?></td>
                    <td><?= htmlspecialchars($app['birth_date']) ?></td>
                    <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                    <td style="max-width: 200px;"><?= nl2br(htmlspecialchars($app['biography'])) ?></td>
                    <td><?= $app['contract_accepted'] ? 'Да' : '' ?></td>
                    <td><?= htmlspecialchars($app['languages'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($app['login']) ?></td>
                    <td class="actions">
                        <a href="?edit=<?= $app['id'] ?>" class="edit-link"> Редактировать</a>
                        <a href="?delete=<?= $app['id'] ?>" class="delete-link" onclick="return confirm('Удалить анкету №<?= $app['id'] ?>?')"> Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
         </table>
    </div>

    <!-- Статистика по языкам -->
    <h2> Статистика: любимые языки программирования</h2>
    <table class="stats-table">
        <thead>
            <tr><th>Язык</th><th>Количество пользователей</th></tr>
        </thead>
        <tbody>
            <?php foreach ($stats as $stat): ?>
            <tr>
                <td><?= htmlspecialchars($stat['name']) ?></td>
                <td><strong><?= $stat['cnt'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="back-link">
        <a href="index.php">← Вернуться на главную (анкета)</a>
    </div>
</div>
</body>
</html>