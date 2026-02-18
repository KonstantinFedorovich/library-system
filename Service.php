<?php
declare(strict_types=1);

/**
 * Service.php — Слой бизнес-логики (Service Layer).
 * * Этот класс изолирует сложную логику от контроллера (api.php).
 * Задачи класса:
 * 1. Проверка токенов авторизации.
 * 2. Взаимодействие с внешними API (Google Books).
 * 3. Проверка прав доступа (ACL/RBAC).
 */

class LibraryService
{
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор сервиса.
     * Принимает готовое подключение к БД (Dependency Injection).
     *
     * @param PDO $pdo Объект подключения PDO
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Получает ID пользователя по Bearer токену.
     * Парсит заголовок Authorization и ищет пользователя в БД.
     *
     * @param string $tokenHeader Полная строка заголовка (например "Bearer abc123...")
     * @return int|null Возвращает ID пользователя или null, если токен не найден
     */
    public function getUserByToken(string $tokenHeader): ?int
    {
        // Проверяем формат заголовка через регулярное выражение
        if (preg_match('/Bearer\s(\S+)/', $tokenHeader, $matches)) {
            $token = $matches[1];

            // Ищем пользователя с таким токеном
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE api_token = ?");
            $stmt->execute([$token]);

            // Получаем результат (ID)
            $userId = $stmt->fetchColumn();

            // Если пользователь найден, возвращаем ID как число (int)
            return $userId ? (int)$userId : null;
        }

        return null;
    }

    /**
     * Поиск книг во внешнем сервисе Google Books API.
     * Выполняет HTTP-запрос и форматирует результат для нашего приложения.
     *
     * @param string $query Поисковый запрос (название книги)
     * @return array Список найденных книг
     */
    public function searchGoogleBooks(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        // Формируем URL для запроса (обязательно кодируем пробелы и спецсимволы)
        $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($query);

        // Выполняем GET-запрос к API Google
        // В продакшене лучше использовать cURL или Guzzle, но file_get_contents допустим для учебного проекта
        $json = @file_get_contents($url);

        if ($json === false) {
            return []; // Ошибка сети или API недоступен
        }

        $data = json_decode($json, true);
        $books = [];

        // Парсим сложный ответ Google API и берем только нужное
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $info = $item['volumeInfo'];

                $books[] = [
                    'title'       => $info['title'] ?? 'Без названия',
                    'authors'     => $info['authors'] ?? ['Неизвестный автор'],
                    'description' => $info['description'] ?? 'Описание отсутствует',
                    // Можно добавить ссылку на обложку или дату
                ];
            }
        }

        return $books;
    }

    /**
     * Проверка прав доступа (RBAC).
     * Определяет, имеет ли право $userId просматривать библиотеку $ownerId.
     *
     * Правила доступа:
     * 1. Владелец всегда имеет доступ к своим книгам.
     * 2. Гость имеет доступ, если есть запись в таблице access_rights.
     *
     * @param int|null $ownerId ID владельца библиотеки
     * @param int|null $userId ID текущего пользователя (кто смотрит)
     * @return bool True, если доступ разрешен
     */
    public function checkAccess(?int $ownerId, ?int $userId): bool
    {
        // 1. Если ID не переданы — доступ запрещен
        if (!$ownerId || !$userId) {
            return false;
        }

        // 2. Если я смотрю свою библиотеку — доступ разрешен
        if ($ownerId === $userId) {
            return true;
        }

        // 3. Проверяем таблицу прав доступа (access_rights)
        $stmt = $this->pdo->prepare("SELECT id FROM access_rights WHERE owner_id = ? AND guest_id = ?");
        $stmt->execute([$ownerId, $userId]);

        // Если запись найдена (fetchColumn вернет id), значит доступ есть
        return (bool)$stmt->fetchColumn();
    }
}
?>