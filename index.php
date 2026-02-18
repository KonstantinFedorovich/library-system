<?php
declare(strict_types=1);

/**
 * index.php — Главная страница (Каталог / Админка).
 * * Функционал:
 * 1. Отображение списка книг.
 * 2. Поиск по названию или автору (фильтрация).
 * 3. Кнопки для удаления книг.
 */

require 'db.php';

// 1. Получаем поисковый запрос из URL (если есть)
// Оператор ?? '' защищает от ошибки, если параметра нет (аналог isset)
$search = $_GET['search'] ?? '';

// 2. Подготавливаем переменную для поиска с маской
// Символ % означает "любое количество символов" до и после фразы
$searchParam = "%$search%";

// 3. Выбираем логику запроса к БД
if ($search) {
    // ЕСЛИ ЕСТЬ ПОИСК:
    // Используем подготовленный запрос (prepare) для защиты от SQL-инъекций.
    // Ищем совпадения в заголовке ИЛИ авторе.
    $stmt = $pdo->prepare("SELECT * FROM books WHERE (title LIKE :title OR author LIKE :author) AND is_deleted = 0");
    $stmt->execute([
        'title' => $searchParam,
        'author' => $searchParam
    ]);
} else {
    // ЕСЛИ ПОИСКА НЕТ:
    // Просто выбираем все "живые" (не удаленные) книги.
    $stmt = $pdo->query("SELECT * FROM books WHERE is_deleted = 0");
}

// Получаем результат в виде ассоциативного массива
$books = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Моя Библиотека</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f4; max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #333; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .search-box { margin-bottom: 20px; display: flex; gap: 10px; }
        input[type="text"] { padding: 10px; width: 100%; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #218838; }

        .status-available { color: green; font-weight: bold; }
        .status-issued { color: red; font-weight: bold; }
        .status-restoration { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Каталог библиотеки</h1>

    <div class="search-box">
        <form action="" method="GET" style="display: flex; width: 100%; gap: 10px;">
            <input type="text" name="search" placeholder="Поиск книги или автора..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Найти</button>

            <?php if($search): ?>
                <a href="index.php" style="padding: 10px; text-decoration: none; color: #333; border: 1px solid #ccc; border-radius: 4px; background: white;">Сброс</a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Текст (начало)</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $book): ?>
                <tr>
                    <td><?php echo $book['id']; ?></td>

                    <td>
                        <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                    </td>

                    <td style="color: #666; font-size: 0.9em;">
                        <?php
                            // Обрезаем длинный текст до 100 символов для превью
                            $text = $book['content'] ?? '';
                            echo mb_strimwidth(htmlspecialchars($text), 0, 100, "...");
                        ?>
                    </td>

                    <td>
                        <a href="delete.php?id=<?php echo $book['id']; ?>"
                           class="btn-delete"
                           style="color: red; text-decoration: none; font-weight: bold;"
                           onclick="return confirm('Вы уверены, что хотите удалить эту книгу?');">
                           Удалить
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>