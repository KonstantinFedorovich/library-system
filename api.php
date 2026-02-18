<?php
declare(strict_types=1);

/**
 * api.php — Основной контроллер (Entry Point) API.
 * * Отвечает за:
 * 1. Прием HTTP-запросов и парсинг входных данных (JSON/POST).
 * 2. Аутентификацию пользователя (Bearer Token).
 * 3. Маршрутизацию запросов (Router).
 * 4. Формирование JSON-ответа.
 */

// Устанавливаем заголовок ответа (всегда JSON)
header('Content-Type: application/json; charset=utf-8');

// Подключение зависимостей
require_once 'db.php';
require_once 'Service.php';

// --- 1. ИНИЦИАЛИЗАЦИЯ И ВХОДНЫЕ ДАННЫЕ ---

try {
    // Инициализируем сервисный слой (Бизнес-логика)
    $service = new LibraryService($pdo);

    // Читаем тело запроса (для JSON)
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true) ?? [];

    // Определяем метод API (например: ?method=login)
    $method = $_GET['method'] ?? '';

    // --- 2. АВТОРИЗАЦИЯ (BEARER TOKEN) ---

    // Пытаемся извлечь токен из заголовков (Apache/Nginx могут называть их по-разному)
    $headers = getallheaders();
    $authHeader = $headers['Authorization']
               ?? $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? '';

    // Если в заголовках пусто, проверяем GET-параметр (для удобства тестов)
    if (!$authHeader && isset($_GET['token'])) {
        $authHeader = 'Bearer ' . $_GET['token'];
    }

    // Определяем текущего пользователя (если токен валиден)
    // Метод вернет ID пользователя или null, если токен неверный/отсутствует
    $currentUserId = $service->getUserByToken($authHeader);

    // Заготовка ответа по умолчанию
    $response = ['status' => 'error', 'message' => 'Unknown method'];

    // --- 3. РОУТИНГ ЗАПРОСОВ ---

    switch ($method) {
        // ====================================================================
        // СЕКЦИЯ: ПОЛЬЗОВАТЕЛИ
        // ====================================================================

        case 'register':
            /**
             * Регистрация нового пользователя.
             * Создает запись в БД с безопасным хешем пароля.
             */
            $login = trim($input['login'] ?? '');
            $pass  = trim($input['password'] ?? '');

            if (!$login || !$pass) {
                $response = ['status' => 'error', 'message' => 'Введите логин и пароль'];
            } else {
                // Проверка на уникальность логина
                $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
                $stmt->execute([$login]);

                if ($stmt->fetch()) {
                    $response = ['status' => 'error', 'message' => 'Пользователь уже существует'];
                } else {
                    // Хешируем пароль и генерируем токен
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));

                    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, api_token) VALUES (?, ?, ?)");
                    if ($stmt->execute([$login, $hash, $token])) {
                        $response = [
                            'status' => 'success',
                            'token' => $token,
                            'user_id' => $pdo->lastInsertId()
                        ];
                    }
                }
            }
            break;

        case 'login':
            /**
             * Вход в систему.
             * Проверяет хеш пароля и выдает новый токен.
             */
            $login = trim($input['login'] ?? '');
            $pass  = trim($input['password'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($pass, $user['password_hash'])) {
                // Генерируем новый токен безопасности при каждом входе
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
            break;

        case 'get_users':
            /**
             * Получение списка всех пользователей.
             * Используется для поиска друзей (ID).
             */
            $stmt = $pdo->query("SELECT id, login FROM users ORDER BY id ASC");
            $response = ['status' => 'success', 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        // ====================================================================
        // СЕКЦИЯ: КНИГИ
        // ====================================================================

        case 'create_book':
            /**
             * Создание книги.
             * Поддерживает JSON (текст) и Multipart Form (загрузка файла .txt).
             */
            if (!$currentUserId) {
                $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
                break;
            }

            // Получаем данные из POST (форма) или JSON
            $title = $_POST['title'] ?? $input['title'] ?? '';
            $text  = $_POST['text'] ?? $input['text'] ?? '';

            // Обработка загрузки файла
            if (isset($_FILES['book_file']) && $_FILES['book_file']['error'] === UPLOAD_ERR_OK) {
                $text = file_get_contents($_FILES['book_file']['tmp_name']);
            }

            if (!$title) {
                $response = ['status' => 'error', 'message' => 'Название обязательно'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO books (user_id, title, content) VALUES (?, ?, ?)");
                if ($stmt->execute([$currentUserId, $title, $text])) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Книга успешно создана',
                        'book_id' => $pdo->lastInsertId()
                    ];
                }
            }
            break;

        case 'get_books':
            /**
             * Получение списка "Мои книги".
             * Возвращает только активные (не удаленные) книги текущего пользователя.
             */
            if (!$currentUserId) {
                $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
                break;
            }

            $stmt = $pdo->prepare("SELECT id, title FROM books WHERE user_id = ? AND is_deleted = 0");
            $stmt->execute([$currentUserId]);
            $response = ['status' => 'success', 'books' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'get_book':
            /**
             * Получение полной информации о книге (чтение).
             * Включает проверку прав доступа (через Service Layer).
             */
            $bookId = $_GET['id'] ?? null;

            if (!$currentUserId) {
                $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
            } elseif ($bookId) {
                $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                $stmt->execute([$bookId]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);

                // ACL Check: Это моя книга ИЛИ у меня есть доступ к библиотеке автора?
                if ($book && $service->checkAccess((int)$book['user_id'], (int)$currentUserId)) {
                    $response = ['status' => 'success', 'book' => $book];
                } else {
                    $response = ['status' => 'error', 'message' => 'Доступ запрещен или книга не найдена'];
                }
            }
            break;

        // ====================================================================
        // СЕКЦИЯ: ИНТЕГРАЦИИ И ДОСТУПЫ
        // ====================================================================

        case 'search_external':
            /**
             * Поиск во внешнем API (Google Books).
             * Логика полностью инкапсулирована в Service.php.
             */
            $q = $_GET['q'] ?? '';
            $response = $service->searchGoogleBooks($q);
            break;

        case 'grant_access':
            /**
             * Предоставление доступа к своей библиотеке другому пользователю.
             */
            $guestId = $input['guest_id'] ?? $_POST['guest_id'] ?? null;

            if (!$currentUserId) {
                $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
            } elseif ($guestId && $guestId != $currentUserId) {
                // INSERT IGNORE позволяет избежать дублей без лишних проверок
                $stmt = $pdo->prepare("INSERT IGNORE INTO access_rights (owner_id, guest_id) VALUES (?, ?)");
                $stmt->execute([$currentUserId, $guestId]);
                $response = ['status' => 'success', 'message' => "Доступ открыт для пользователя ID $guestId"];
            } else {
                $response = ['status' => 'error', 'message' => 'Некорректный ID гостя'];
            }
            break;

        case 'get_other_books':
            /**
             * Просмотр библиотеки другого пользователя.
             * Требует наличия записи в таблице access_rights.
             */
            $ownerId = $_GET['owner_id'] ?? null;

            if (!$currentUserId) {
                $response = ['status' => 'error', 'message' => 'Ошибка авторизации'];
            } elseif ($service->checkAccess((int)$ownerId, (int)$currentUserId)) {
                $stmt = $pdo->prepare("SELECT id, title FROM books WHERE user_id = ? AND is_deleted = 0");
                $stmt->execute([$ownerId]);
                $response = [
                    'status' => 'success',
                    'owner_id' => $ownerId,
                    'books' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'Нет доступа к библиотеке этого пользователя'];
            }
            break;
    }

} catch (Exception $e) {
    // Глобальная обработка ошибок (возвращаем JSON вместо падения скрипта)
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Отправляем финальный JSON-ответ
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>