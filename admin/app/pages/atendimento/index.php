<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../config/permissions.php");
exigirLogin();

$estabelecimento_id = $_SESSION['estabelecimento_id'];

// Verificar se existe caixa aberto
$stmt = $pdo->prepare("SELECT id FROM caixas WHERE estabelecimento_id = :estab_id AND status = 'aberto' LIMIT 1");
$stmt->execute(['estab_id' => $estabelecimento_id]);
$caixa_aberto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caixa_aberto) {
    header("Location: ../caixa/index.php");
    exit;
}

// Redirecionar automaticamente para o atendimento rápido
header("Location: rapido.php");
exit;
?>