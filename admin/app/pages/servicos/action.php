<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$id   = $_GET['id']   ?? '';

if (empty($estabelecimento_id)) { header("Location: ../../login.php"); exit; }
if (empty($id) || empty($acao)) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND estabelecimento_id = ? LIMIT 1");
$stmt->execute([$id, $estabelecimento_id]);
$servico = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$servico) { header("Location: index.php"); exit; }

// Verifica se tem agendamentos vinculados
$stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamento_servicos WHERE servico_id = ?");
$stmt->execute([$id]);
$total = (int)$stmt->fetchColumn();
$tem_registros = $total > 0;

$resultado = ['sucesso' => false, 'mensagem' => '', 'tipo' => ''];

try {
    switch ($acao) {

        case 'excluir':
            if (!$tem_registros) {
                // Sem histórico — exclui fisicamente
                $pdo->prepare("DELETE FROM profissional_servicos WHERE servico_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM imagens WHERE servico_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM servicos WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
                header("Location: index.php?msg=excluido&nome=" . urlencode($servico['nome']));
                exit;
            } else {
                // Tem histórico — inativa
                $pdo->prepare("UPDATE servicos SET ativo = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
                $resultado = ['sucesso' => true, 'mensagem' => "Serviço <strong>".htmlspecialchars($servico['nome'])."</strong> possui {$total} agendamento(s). Foi <strong>inativado</strong> para preservar o histórico.", 'tipo' => 'warning'];
            }
            break;

        case 'inativar':
            $pdo->prepare("UPDATE servicos SET ativo = 0, updated_at = NOW() WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
            header("Location: index.php?msg=inativado&nome=" . urlencode($servico['nome']));
            exit;

        case 'ativar':
            $pdo->prepare("UPDATE servicos SET ativo = 1, deleted_at = NULL, updated_at = NOW() WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
            header("Location: index.php?msg=ativado&nome=" . urlencode($servico['nome']));
            exit;

        default:
            header("Location: index.php"); exit;
    }
} catch (PDOException $e) {
    $resultado = ['sucesso' => false, 'mensagem' => "Erro: " . $e->getMessage(), 'tipo' => 'danger'];
}

require_once("../../top/topo.php");
$active_menu = 'servicos';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header"><div class="container-fluid"><h3 class="mb-0">Serviços</h3></div></div>
    <div class="app-content"><div class="container-fluid"><div class="card"><div class="card-body text-center py-5">
        <?php $icone = match($resultado['tipo']) { 'success'=>'bi-check-circle-fill text-success', 'warning'=>'bi-slash-circle-fill text-warning', default=>'bi-exclamation-circle-fill text-danger' }; ?>
        <i class="bi <?php echo $icone; ?>" style="font-size:3rem;"></i>
        <h4 class="mt-3"><?php echo $resultado['mensagem']; ?></h4>
        <div class="mt-4"><a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Voltar para Serviços</a></div>
    </div></div></div></div>
</main>
<?php require_once("../../layout/footer.php"); ?>
