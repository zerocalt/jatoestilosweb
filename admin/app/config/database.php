<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'infopizza');
define('DB_USER', 'root'); // Ajuste conforme necessário
define('DB_PASS', ''); // Ajuste conforme necessário

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>