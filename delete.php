<?php
require 'db.php';
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE books SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: index.php');
exit;