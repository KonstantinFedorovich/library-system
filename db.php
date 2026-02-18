<?php
declare(strict_types=1);

/**
 * db.php — Слой подключения к Базе Данных.
 * * Отвечает за:
 * 1. Настройку параметров подключения (DSN).
 * 2. Создание экземпляра PDO (PHP Data Objects).
 * 3. Настройку режима обработки ошибок (Throw Exceptions).
 * * @var PDO $pdo Глобальный объект подключения, который используется в api.php и Service.php
 */

// В реальном проекте эти данные хранятся в файле .env и не попадают в репозиторий.
// Для учебного проекта допустимо оставить их здесь.
$host = 'MySQL-8.0'; // Хост для Open Server
$port = '3306';
$db   = 'library_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Опции драйвера PDO
$options = [
    // Выбрасывать исключения при ошибках SQL (вместо молчания)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Возвращать строки как ассоциативные массивы ['id' => 1]
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Использовать нативные подготовленные запросы (безопасность)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Попытка 1: Подключение через именованный хост (специфика Open Server)
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (\PDOException $e) {
    // Попытка 2: Если не вышло, пробуем стандартный IP (localhost)
    // Это делает код переносимым между разными компьютерами
    $dsn2 = "mysql:host=127.0.0.1;port=$port;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn2, $user, $pass, $options);
    } catch (\PDOException $e2) {
        // Если и тут ошибка — останавливаем скрипт и выводим проблему
        // (В продакшене тут должно быть логирование в файл, а не вывод на экран)
        throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
    }
}
?>