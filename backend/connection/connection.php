<?php
$host = 'localhost';
$db = 'foodcourtv1';
$user = 'root';
$pass = 'mipmopmap26PanQ';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión a BD']));
}
?>