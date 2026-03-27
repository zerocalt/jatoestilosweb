<?php
session_start();
// admin/app/config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'jatoestilos');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>