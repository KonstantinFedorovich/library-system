<?php
header('Content-Type: application/json');
require 'db.php';

//ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ

function getAuthUserId($pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (!$authHeader) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }
    if (!$authHeader) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }
    $token = str_replace('Bearer ', '', $authHeader);
    if (!$token) return null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}

//ОБРАБОТКА ВХОДЯЩИХ ДАННЫХ

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true) ?? [];

$method = $_GET['method'] ?? '';

$response = ['status' => 'error', 'message' => 'Method not found'];

//РОУТЕР
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
                $response = [
                    'status' => 'success',
                    'message' => 'Вы успешно зарегистрированы',
                    'token' => $token,
                    'user_id' => $pdo->lastInsertId()
                ];
            }
        }
    }
}
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
        $response = [
            'status' => 'success',
            'token' => $newToken,
            'user_id' => $user['id']
        ];
    } else {
        $response = ['status' => 'error', 'message' => 'Неверный логин или пароль'];
    }
}
elseif ($method === 'get_users') {
    $stmt = $pdo->query("SELECT id, login FROM users");
    $response = [
        'status' => 'success',
        'users' => $stmt->fetchAll()
    ];
}

// 4. СОЗДАТЬ КНИГУ (CREATE BOOK)
elseif ($method === 'create_book') {
    $userId = getAuthUserId($pdo);

    if (!$userId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации. Токен не найден.'];
    } else {
        $title = $_POST['title'] ?? $input['title'] ?? '';
        $text  = $_POST['text'] ?? $input['text'] ?? '';
        // ЛОГИКА ЗАГРУЗКИ ФАЙЛА
        if (isset($_FILES['book_file']) && $_FILES['book_file']['error'] === UPLOAD_ERR_OK) {
            $text = file_get_contents($_FILES['book_file']['tmp_name']);
        }
        if (!$title) {
            $response = ['status' => 'error', 'message' => 'Название книги обязательно'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO books (user_id, title, content) VALUES (?, ?, ?)");
            if ($stmt->execute([$userId, $title, $text])) {
                $response = [
                    'status' => 'success',
                    'message' => 'Книга успешно создана!',
                    'book_id' => $pdo->lastInsertId()
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'Ошибка базы данных'];
            }
        }
    }
}
// 5. ПОЛУЧИТЬ СПИСОК СВОИХ КНИГ
if ($method === 'get_books') {
    $userId = getAuthUserId($pdo);
    
    if (!$userId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } else {
        // Выбираем только активные книги (не удаленные)
        $stmt = $pdo->prepare("SELECT id, title FROM books WHERE user_id = ? AND is_deleted = 0");
        $stmt->execute([$userId]);
        $books = $stmt->fetchAll();
        
        $response = [
            'status' => 'success', 
            'books' => $books
        ];
    }
}
// 6. ОТКРЫТЬ КНИГУ (ПОЛУЧИТЬ ПОЛНЫЙ ТЕКСТ)
elseif ($method === 'get_book') {
    $userId = getAuthUserId($pdo);
    $bookId = $_GET['id'] ?? null;
    
    if (!$userId) {
        $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
    } elseif (!$bookId) {
        $response = ['status' => 'error', 'message' => 'Не указан ID книги'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? AND user_id = ?");
        $stmt->execute([$bookId, $userId]);
        $book = $stmt->fetch();
        
        if ($book) {
            $response = ['status' => 'success', 'book' => $book];
        } else {
            $response = ['status' => 'error', 'message' => 'Книга не найдена или доступ запрещен'];
        }
    }
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>