<?php
require 'db.php';

// Логика поиска
$search = $_GET['search'] ?? '';
$searchParam = "%$search%";

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE (title LIKE :title OR author LIKE :author) AND is_deleted = 0");
    $stmt->execute([
        'title' => $searchParam,
        'author' => $searchParam
    ]);
} else {
    $stmt = $pdo->query("SELECT * FROM books WHERE is_deleted = 0");
}
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
                <th>Текст (начало)</th> <th>Действия</th>
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
                            $text = $book['content'] ?? '';
                            echo mb_strimwidth(htmlspecialchars($text), 0, 100, "...");
                        ?>
                    </td>

                    <td>
                        <a href="delete.php?id=<?php echo $book['id']; ?>"
                           class="btn-delete"
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