<?php
$host = 'MySQL-8.0';
$port = '3306';
$db   = 'library_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     $dsn2 = "mysql:host=127.0.0.1;port=$port;dbname=$db;charset=$charset";
     try {
        $pdo = new PDO($dsn2, $user, $pass, $options);
     } catch (\PDOException $e2) {
        throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
     }
}