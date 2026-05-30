<?php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $db_host = 'localhost';
        $db_user = 'u82311';
        $db_pass = '6649813';
        $db_name = 'u82311';
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    return $pdo;
}

function getAllowedLanguages() {
    return [
        'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
        'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
    ];
}

function getAllowedGenders() {
    return ['male', 'female'];
}


function generateUniqueLogin($pdo) {
    do {
        $login = 'user_' . substr(md5(uniqid()), 0, 8);
        $stmt = $pdo->prepare("SELECT id FROM application WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

//Генерация случайного пароля (8 символов)
function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, 8);
}
?>