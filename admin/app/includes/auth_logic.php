<?php
// admin/app/includes/auth_logic.php
session_start();
require_once("../config/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: ../../../admin/login.php?error=1");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT u.*, e.id as estabelecimento_id 
                               FROM usuarios u 
                               LEFT JOIN estabelecimentos e ON e.admin_id = u.id
                               WHERE u.email = :email AND u.perfil = 'admin' AND u.ativo = 1 
                               LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['senha_hash'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_nome'] = $user['nome'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['estabelecimento_id'] = $user['estabelecimento_id'];
            
            header("Location: ../pages/dashboard/index.php");
            exit;
        } else {
            header("Location: ../../../admin/login.php?error=1");
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao autenticar: " . $e->getMessage());
    }
} else {
    header("Location: ../../../admin/login.php");
    exit;
}
?>