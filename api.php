<?php
header('Content-Type: application/json');
require 'db.php';
require 'Service.php';

// Инициализация сервиса
$service = new LibraryService($pdo);

//ПОЛУЧЕНИЕ ДАННЫХ
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true) ?? [];
$method = $_GET['method'] ?? '';

//АВТОРИЗАЦИЯ
// Пытаемся найти токен в заголовках или GET-параметрах
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader && isset($_GET['token'])) {
    $authHeader = 'Bearer ' . $_GET['token'];
}

// Получаем ID текущего юзера
$currentUserId = $service->getUserByToken($authHeader);
$response = ['status' => 'error', 'message' => 'Method not found'];

//РОУТЕР
// 1. РЕГИСТРАЦИЯ
if ($method === 'register') {
    $login = $input['login'] ?? '';
    $pass  = $input['password'] ?? '';

    if (!$login || !$pass) {
        $response = ['status' => 'error', 'message' => 'Введите логин и пароль'];
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            $response = ['status' => 'error', 'message' => 'Пользователь уже существует'];
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, api_token) VALUES (?, ?, ?)");
            if ($stmt->execute([$login, $hash, $token])) {
                $response = ['status' => 'success', 'token' => $token, 'user_id' => $pdo->lastInsertId()];
            }
        }
    }
}

// 2. ЛОГИН
elseif ($method === 'login') {
    $login = $input['login'] ?? '';
    $pass  = $input['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        $newToken = bin2hex(random_bytes(32));
        $update = $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?");
        $update->execute([$newToken, $user['id']]);
        $response = ['status' => 'success', 'token' => $newToken, 'user_id' => $user['id']];
    } else {
        $response = ['status' => 'error', 'message' => 'Неверный логин или пароль'];
    }
}
// 3. СПИСОК ЮЗЕРОВ
elseif ($method === 'get_users') {
    $stmt = $pdo->query("SELECT id, login FROM users");
    $response = ['status' => 'success', 'users' => $stmt->fetchAll()];
}
// 4. СОЗДАТЬ КНИГУ
elseif ($method === 'create_book') {
    if (!$currentUserId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } else {
        $title = $_POST['title'] ?? $input['title'] ?? '';
        $text  = $_POST['text'] ?? $input['text'] ?? '';

        if (isset($_FILES['book_file']) && $_FILES['book_file']['error'] === UPLOAD_ERR_OK) {
            $text = file_get_contents($_FILES['book_file']['tmp_name']);
        }
        if (!$title) {
            $response = ['status' => 'error', 'message' => 'Название обязательно'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO books (user_id, title, content) VALUES (?, ?, ?)");
            if ($stmt->execute([$currentUserId, $title, $text])) {
                $response = ['status' => 'success', 'message' => 'Книга создана', 'book_id' => $pdo->lastInsertId()];
            }
        }
    }
}
// 5. МОИ КНИГИ
elseif ($method === 'get_books') {
    if (!$currentUserId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } else {
        $stmt = $pdo->prepare("SELECT id, title FROM books WHERE user_id = ? AND is_deleted = 0");
        $stmt->execute([$currentUserId]);
        $response = ['status' => 'success', 'books' => $stmt->fetchAll()];
    }
}
// 6. ОТКРЫТЬ КНИГУ
elseif ($method === 'get_book') {
    $bookId = $_GET['id'] ?? null;
    if (!$currentUserId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } elseif ($bookId) {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        // Проверка: это моя книга ИЛИ мне дали доступ к автору этой книги?
        if ($book && $service->checkAccess($book['user_id'], $currentUserId)) {
            $response = ['status' => 'success', 'book' => $book];
        } else {
            $response = ['status' => 'error', 'message' => 'Доступ запрещен или книга не найдена'];
        }
    }
}

// 7. ПОИСК В GOOGLE (Через сервис!)
elseif ($method === 'search_external') {
    $q = $_GET['q'] ?? '';
    // Вся сложная логика ушла в Service.php, тут чистота!
    $response = $service->searchGoogleBooks($q);
}

// 8. ДАТЬ ДОСТУП
elseif ($method === 'grant_access') {
    $guestId = $input['guest_id'] ?? $_POST['guest_id'] ?? null;
    if (!$currentUserId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } elseif ($guestId && $guestId != $currentUserId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO access_rights (owner_id, guest_id) VALUES (?, ?)");
        $stmt->execute([$currentUserId, $guestId]);
        $response = ['status' => 'success', 'message' => "Доступ открыт для ID $guestId"];
    } else {
        $response = ['status' => 'error', 'message' => 'Некорректный ID гостя'];
    }
}

// 9. ЧУЖАЯ БИБЛИОТЕКА (С проверкой через Сервис)
elseif ($method === 'get_other_books') {
    $ownerId = $_GET['owner_id'] ?? null;
    if (!$currentUserId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } elseif ($service->checkAccess($ownerId, $currentUserId)) {
        $stmt = $pdo->prepare("SELECT id, title FROM books WHERE user_id = ? AND is_deleted = 0");
        $stmt->execute([$ownerId]);
        $response = ['status' => 'success', 'owner_id' => $ownerId, 'books' => $stmt->fetchAll()];
    } else {
        $response = ['status' => 'error', 'message' => 'Нет доступа к библиотеке этого пользователя'];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>