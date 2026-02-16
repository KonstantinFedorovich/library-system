<?php
class LibraryService {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    // Логика поиска в Google
    public function searchGoogleBooks($query) {
        if (!$query) return ['status' => 'error', 'message' => 'Пустой запрос'];

        $url = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($query);
        $googleJson = @file_get_contents($url);

        if ($googleJson === false) {
            return ['status' => 'error', 'message' => 'Ошибка связи с Google API'];
        }

        $data = json_decode($googleJson, true);
        $results = [];

        foreach ($data['items'] ?? [] as $item) {
            $info = $item['volumeInfo'];
            $results[] = [
                'google_id' => $item['id'],
                'title' => $info['title'] ?? 'Без названия',
                'authors' => $info['authors'] ?? ['Неизвестен'],
                'description' => $info['description'] ?? 'Описание отсутствует'
            ];
        }
        return ['status' => 'success', 'items' => $results];
    }
    // Логика проверки доступа
    public function checkAccess($ownerId, $guestId) {
        if ($ownerId == $guestId) return true;
        $stmt = $this->pdo->prepare("SELECT id FROM access_rights WHERE owner_id = ? AND guest_id = ?");
        $stmt->execute([$ownerId, $guestId]);
        return (bool)$stmt->fetch();
    }
    // Вспомогательный метод получения ID юзера по токену
    public function getUserByToken($token) {
        $token = str_replace('Bearer ', '', $token);
        if (!$token) return null;
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE api_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        return $user ? $user['id'] : null;
    }
}