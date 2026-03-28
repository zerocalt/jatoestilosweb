<?php
// admin/app/includes/auth_logic.php
require_once("../config/database.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../../admin/login.php");
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header("Location: ../../../admin/login.php?error=1");
    exit;
}

try {
    // Busca usuário com perfil admin, profissional ou atendente
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM usuarios u
        WHERE u.email = ? AND u.ativo = 1 AND u.deleted_at IS NULL
          AND u.perfil IN ('admin','profissional','atendente')
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['senha_hash'])) {
        header("Location: ../../../admin/login.php?error=1");
        exit;
    }

    // Busca o estabelecimento vinculado
    $estabelecimento_id = null;
    $profissional_id    = null;

    if ($user['perfil'] === 'admin') {
        $stmt = $pdo->prepare("SELECT id FROM estabelecimentos WHERE admin_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$user['id']]);
        $est = $stmt->fetch(PDO::FETCH_ASSOC);
        $estabelecimento_id = $est['id'] ?? null;

    } else {
        // Profissional ou atendente — busca pelo profissional vinculado
        $stmt = $pdo->prepare("SELECT id, estabelecimento_id FROM profissionais WHERE usuario_id = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$user['id']]);
        $prof = $stmt->fetch(PDO::FETCH_ASSOC);
        $profissional_id    = $prof['id'] ?? null;
        $estabelecimento_id = $prof['estabelecimento_id'] ?? null;
    }

    if (!$estabelecimento_id) {
        header("Location: ../../../admin/login.php?error=2"); // sem estabelecimento
        exit;
    }

    // Salva a sessão
    $_SESSION['usuario_id']        = $user['id'];
    $_SESSION['nome']              = $user['nome'];
    $_SESSION['email']             = $user['email'];
    $_SESSION['perfil']            = $user['perfil'];
    $_SESSION['foto_url']          = $user['foto_url'];
    $_SESSION['estabelecimento_id']= $estabelecimento_id;
    $_SESSION['profissional_id']   = $profissional_id; // null para admin

    header("Location: ../pages/dashboard/index.php");
    exit;

} catch (PDOException $e) {
    die("Erro ao autenticar: " . $e->getMessage());
}
