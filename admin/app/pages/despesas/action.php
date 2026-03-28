<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$id   = $_GET['id']   ?? '';

if (empty($estabelecimento_id)) { header("Location: ../../login.php"); exit; }
if (empty($id) || empty($acao)) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM despesas WHERE id = ? AND estabelecimento_id = ? LIMIT 1");
$stmt->execute([$id, $estabelecimento_id]);
$despesa = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$despesa) { header("Location: index.php"); exit; }

try {
    switch ($acao) {

        case 'excluir':
            // Despesas podem sempre ser excluídas fisicamente
            // (não há FK de outras tabelas apontando para despesas)
            $pdo->prepare("DELETE FROM despesas WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
            header("Location: index.php?msg=excluido&nome=" . urlencode($despesa['nome']));
            exit;

        case 'pagar':
            // Marca como paga com data de hoje
            $pdo->prepare("UPDATE despesas SET status='pago', pago_em=CURDATE(), updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
            header("Location: index.php?msg=pago&nome=" . urlencode($despesa['nome']));
            exit;

        case 'cancelar':
            // Cancela uma despesa recorrente (inativa sem excluir)
            $pdo->prepare("UPDATE despesas SET status='cancelado', updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
            header("Location: index.php?msg=cancelado&nome=" . urlencode($despesa['nome']));
            exit;

        default:
            header("Location: index.php"); exit;
    }
} catch (PDOException $e) {
    // Em caso de erro mostra mensagem
    require_once("../../top/topo.php");
    $active_menu = 'despesas';
    require_once("../../menu/menu.php");
    echo '<main class="app-main"><div class="app-content"><div class="container-fluid">';
    echo '<div class="alert alert-danger mt-3">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<a href="index.php" class="btn btn-primary">Voltar</a>';
    echo '</div></div></main>';
    require_once("../../layout/footer.php");
}
