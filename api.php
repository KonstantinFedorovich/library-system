<?php

header('Content-Type: application/json');
require 'db.php';
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$method = $_GET['method'] ?? '';
$response = ['status' => 'error', 'message' => 'Method not found'];
//РОУТЕР
if ($method === 'register') {
    $login = $input['login'] ?? '';
    $pass  = $input['password'] ?? '';

    if (!$login || !$pass) {
        $response = ['status' => 'error', 'message' => 'Введите логин и пароль'];
    } else {
        // Проверяем, есть ли такой юзер
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
                    'token' => $token, // Возвращаем токен приложению
                    'user_id' => $pdo->lastInsertId()
                ];
            }
        }
    }
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>