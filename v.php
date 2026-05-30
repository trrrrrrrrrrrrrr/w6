<?php
require_once 'db.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT a.*, GROUP_CONCAT(l.name SEPARATOR ', ') AS languages
        FROM application a
        LEFT JOIN application_language al ON a.id = al.application_id
        LEFT JOIN language l ON al.language_id = l.id
        GROUP BY a.id
        ORDER BY a.id DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лабораторная №5</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container {
            max-width: 1300px;
            margin: 30px auto;
            background: white;
            border-radius: 32px;
            padding: 20px;
            box-shadow: 0 20px 30px -10px rgba(0,0,0,0.2);
        }
        .subtitle {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 10px;
            vertical-align: top;
        }
        th {
            background: #eef2ff;
            color: #1e3a8a;
        }
        tr:hover {
            background: #f8fafc;
        }
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        .back-link a {
            background: #2d3e50;
            color: white;
            padding: 10px 25px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 style="text-align:center;"> Сохранённые анкеты</h1>
    <p class="subtitle">Всего записей: <?= count($applications) ?></p>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th>
                    <th>Дата рожд.</th><th>Пол</th><th>Биография</th>
                    <th>Согласие</th><th>Языки</th><th>Дата создания</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['id']) ?></td>
                    <td><?= htmlspecialchars($app['full_name']) ?></td>
                    <td><?= htmlspecialchars($app['phone']) ?></td>
                    <td><?= htmlspecialchars($app['email']) ?></td>
                    <td><?= htmlspecialchars($app['birth_date']) ?></td>
                    <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                    <td><?= nl2br(htmlspecialchars($app['biography'])) ?></td>
                    <td><?= $app['contract_accepted'] ? ' Да' : '' ?></td>
                    <td><?= htmlspecialchars($app['languages']) ?></td>
                    <td><?= htmlspecialchars($app['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="back-link">
        <a href="index.php">← Вернуться к заполнению анкеты</a>
    </div>
</div>
</body>
</html>