<?php
// ─────────────────────────────────────────────
// includes/verificar_senha.php
// Endpoint AJAX — verifica senha do admin logado
// ─────────────────────────────────────────────
session_start();
require_once("../config/database.php");

header('Content-Type: application/json');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false]);
    exit;
}

// Precisa estar logado como admin
$usuario_id = $_SESSION['usuario_id'] ?? null;
$perfil     = $_SESSION['perfil']     ?? '';

if (!$usuario_id || $perfil !== 'admin') {
    echo json_encode(['ok' => false, 'erro' => 'Não autorizado']);
    exit;
}

$senha = $_POST['senha'] ?? '';
if (empty($senha)) {
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ? LIMIT 1");
$stmt->execute([$usuario_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && password_verify($senha, $row['senha_hash'])) {
    echo json_encode(['ok' => true]);
} else {
    // Pequeno delay para dificultar brute force
    usleep(500000); // 0.5 segundo
    echo json_encode(['ok' => false]);
}
