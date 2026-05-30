<?php
require_once 'db.php';
session_start();

header('Content-Type: text/html; charset=UTF-8');

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

//Проверяем, есть ли временные данные для показа модалки
$show_modal = false;
$modal_login = '';
$modal_password = '';
if (isset($_SESSION['new_user_creds'])) {
    $show_modal = true;
    $modal_login = $_SESSION['new_user_creds']['login'];
    $modal_password = $_SESSION['new_user_creds']['password'];
    unset($_SESSION['new_user_creds']); // удаляем после чтения
}




$form_data = [
    'full_name' => '', 'phone' => '', 'email' => '', 'birth_date' => '',
    'gender' => '', 'biography' => '', 'contract_accepted' => false, 'languages' => []
];
$errors = [];
$success_message = '';


$allowed_languages = getAllowedLanguages();
$allowed_genders = getAllowedGenders();

//Если авторизован, загружаем данные из БД для отображения в форме
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $form_data['full_name'] = $user['full_name'];
        $form_data['phone'] = $user['phone'];
        $form_data['email'] = $user['email'];
        $form_data['birth_date'] = $user['birth_date'];
        $form_data['gender'] = $user['gender'];
        $form_data['biography'] = $user['biography'];
        $form_data['contract_accepted'] = (bool)$user['contract_accepted'];
        
        //Загрузка языков
        $stmt_lang = $pdo->prepare("SELECT l.name FROM application_language al JOIN language l ON al.language_id = l.id WHERE al.application_id = ?");
        $stmt_lang->execute([$user_id]);
        $form_data['languages'] = $stmt_lang->fetchAll(PDO::FETCH_COLUMN);
    }
}

//Обработка POST-запроса (сохранение или обновление)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение данных из формы
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['birth_date'] = trim($_POST['birth_date'] ?? '');
    $form_data['gender'] = $_POST['gender'] ?? '';
    $form_data['biography'] = trim($_POST['biography'] ?? '');
    $form_data['contract_accepted'] = isset($_POST['contract_accepted']);
    $form_data['languages'] = $_POST['languages'] ?? [];

    //Валидация
    //ФИО
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $form_data['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы и пробелы.';
    } elseif (strlen($form_data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    //Телефон
    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Телефон обязателен.';
    } else {
        $digits = preg_replace('/\D/', '', $form_data['phone']);
        $digitCount = strlen($digits);
        if ($digitCount < 10 || $digitCount > 12) {
            $errors['phone'] = 'Номер телефона должен содержать от 10 до 12 цифр (например, +7 918 463-42-21).';
        }
    }

    //Email
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email.';
    }

    //Дата рождения
    if (empty($form_data['birth_date'])) {
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
    if (empty($form_data['gender'])) {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!in_array($form_data['gender'], $allowed_genders)) {
        $errors['gender'] = 'Недопустимое значение пола.';
    }

    //Языки
    if (empty($form_data['languages'])) {
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
    if (strlen($form_data['biography']) > 10000) {
        $errors['biography'] = 'Биография не должна превышать 10000 символов.';
    }

    //Чекбокс согласия
    if (!$form_data['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    //Если ошибок нет сохраняем или обновляем
    if (empty($errors)) {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            if ($is_logged_in) {
                //Обновление существующей записи
                $stmt = $pdo->prepare("
                    UPDATE application 
                    SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, biography = ?, contract_accepted = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $form_data['full_name'],
                    $form_data['phone'],
                    $form_data['email'],
                    $form_data['birth_date'],
                    $form_data['gender'],
                    $form_data['biography'],
                    $form_data['contract_accepted'] ? 1 : 0,
                    $user_id
                ]);
                $app_id = $user_id;
                $success_message = 'Данные успешно обновлены!';
            } else {
                //Генерация логина и пароля
                $login = generateUniqueLogin($pdo);
                $plain_password = generatePassword();
                $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO application 
                    (full_name, phone, email, birth_date, gender, biography, contract_accepted, login, password_hash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $form_data['full_name'],
                    $form_data['phone'],
                    $form_data['email'],
                    $form_data['birth_date'],
                    $form_data['gender'],
                    $form_data['biography'],
                    $form_data['contract_accepted'] ? 1 : 0,
                    $login,
                    $password_hash
                ]);
                $app_id = $pdo->lastInsertId();
                $success_message = 'Данные успешно сохранены!';
                
                  //Сохраняем учетные данные в сессию для модального окна
                $_SESSION['new_user_creds'] = ['login' => $login, 'password' => $plain_password];
                
                //втоматически авторизуем пользователя
                $_SESSION['user_id'] = $app_id;
                $is_logged_in = true;
                $user_id = $app_id;
            }

            //Обновление языков (удаляем старые связи и вставляем новые)
            $pdo->prepare("DELETE FROM application_language WHERE application_id = ?")->execute([$app_id]);
            $lang_map = [];
            $stmt = $pdo->query("SELECT id, name FROM language");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['name']] = $row['id'];
            }
            $stmt = $pdo->prepare("INSERT INTO application_language (application_id, language_id) VALUES (?, ?)");
            foreach ($form_data['languages'] as $lang_name) {
                if (isset($lang_map[$lang_name])) {
                    $stmt->execute([$app_id, $lang_map[$lang_name]]);
                }
            }

            $pdo->commit();

            //Сохраняем успешные данные в cookies (как в 4-й лабе) для неавторизованных
            setcookie('full_name', $form_data['full_name'], time() + 365*24*3600, '/');
            setcookie('phone', $form_data['phone'], time() + 365*24*3600, '/');
            setcookie('email', $form_data['email'], time() + 365*24*3600, '/');
            setcookie('birth_date', $form_data['birth_date'], time() + 365*24*3600, '/');
            setcookie('gender', $form_data['gender'], time() + 365*24*3600, '/');
            setcookie('biography', $form_data['biography'], time() + 365*24*3600, '/');
            setcookie('contract_accepted', $form_data['contract_accepted'] ? '1' : '0', time() + 365*24*3600, '/');
            setcookie('languages', implode(',', $form_data['languages']), time() + 365*24*3600, '/');

            //Очищаем ошибки в cookies, если они были
            $error_fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'biography', 'contract_accepted'];
            foreach ($error_fields as $field) {
                setcookie($field . '_error', '', time() - 3600, '/');
                setcookie($field . '_value', '', time() - 3600, '/');
            }

            //Если это была первая отправка и сгенерированы учётные данные – покажем их
            if ($generated_creds) {
                $success_message .= " Ваш логин: {$generated_creds['login']}, пароль: {$generated_creds['password']}. Запомните их для входа.";
            }

            //Перенаправляем GET-запросом, чтобы избежать повторной отправки формы
             header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка БД: ' . $e->getMessage();
        }
    } else {
        //При ошибках сохраняем значения в cookies для отображения в форме
        foreach ($form_data as $field => $value) {
            if (is_array($value)) {
                setcookie($field . '_value', implode(',', $value), time() + 3600, '/');
            } else {
                setcookie($field . '_value', $value, time() + 3600, '/');
            }
        }
        foreach ($errors as $field => $error) {
            setcookie($field . '_error', $error, time() + 3600, '/');
        }
        header('Location: index.php');
        exit;
    }
}

//Чтение cookies для полей при GET-запросе (если не авторизован)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$is_logged_in) {
    $cookie_fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'biography', 'contract_accepted'];
    foreach ($cookie_fields as $field) {
        if (isset($_COOKIE[$field . '_value'])) {
            $form_data[$field] = $_COOKIE[$field . '_value'];
        }
    }
    if (isset($_COOKIE['languages_value'])) {
        $form_data['languages'] = explode(',', $_COOKIE['languages_value']);
    }
    //Чтение ошибок из cookies
    foreach ($cookie_fields as $field) {
        if (isset($_COOKIE[$field . '_error'])) {
            $errors[$field] = $_COOKIE[$field . '_error'];
        }
    }
    if (isset($_COOKIE['languages_error'])) {
        $errors['languages'] = $_COOKIE['languages_error'];
    }
    if (isset($_COOKIE['contract_accepted_error'])) {
        $errors['contract_accepted'] = $_COOKIE['contract_accepted_error'];
    }
}

//сли есть параметр success в URL, показываем сообщение об успехе
if (isset($_GET['success'])) {
    $success_message = 'Данные успешно сохранены!';
}

$languages_from_db = getDB()->query("SELECT name FROM language ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
if (empty($languages_from_db)) {
    $languages_from_db = $allowed_languages;
}

include 'form.php';
?>