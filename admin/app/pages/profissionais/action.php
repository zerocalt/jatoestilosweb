<?php
session_start();
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'] ?? '';
$acao = $_GET['acao'] ?? '';
$id   = $_GET['id']   ?? '';

if (empty($estabelecimento_id)) { header("Location: ../../login.php"); exit; }
if (empty($id) || empty($acao)) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT p.*, u.nome, u.email FROM profissionais p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? AND p.estabelecimento_id = ? LIMIT 1");
$stmt->execute([$id, $estabelecimento_id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prof) { header("Location: index.php"); exit; }

// Verifica registros vinculados
$stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE profissional_id = ?");
$stmt->execute([$id]);
$total = (int)$stmt->fetchColumn();
$tem_registros = $total > 0;

$resultado = ['sucesso' => false, 'mensagem' => '', 'tipo' => ''];

try {
    switch ($acao) {

        case 'excluir':
            if (!$tem_registros) {
                // Sem histórico — exclui fisicamente
                $pdo->prepare("DELETE FROM profissional_servicos WHERE profissional_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM horarios_funcionamento WHERE profissional_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM bloqueios_agenda WHERE profissional_id = ?")->execute([$id]);
                $user_id = $prof['usuario_id'];
                $pdo->prepare("DELETE FROM profissionais WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
                // Remove conta de usuário se não é admin de outro estabelecimento
                $outros = $pdo->prepare("SELECT COUNT(*) FROM estabelecimentos WHERE admin_id = ?");
                $outros->execute([$user_id]);
                if ($outros->fetchColumn() == 0) {
                    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$user_id]);
                }
                // Remove foto
                $foto = $_SERVER['DOCUMENT_ROOT'] . "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/profissionais/{$id}.jpg";
                if (file_exists($foto)) unlink($foto);
                header("Location: index.php?msg=excluido&nome=" . urlencode($prof['nome']));
                exit;
            } else {
                // Tem histórico — inativa
                $pdo->prepare("UPDATE profissionais SET ativo=0, updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
                $pdo->prepare("UPDATE usuarios SET ativo=0, updated_at=NOW() WHERE id=?")->execute([$prof['usuario_id']]);
                $resultado = ['sucesso'=>true, 'mensagem'=>"Profissional <strong>".htmlspecialchars($prof['nome'])."</strong> possui {$total} agendamento(s). Foi <strong>inativado</strong> para preservar o histórico.", 'tipo'=>'warning'];
            }
            break;

        case 'inativar':
            $pdo->prepare("UPDATE profissionais SET ativo=0, updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
            $pdo->prepare("UPDATE usuarios SET ativo=0, updated_at=NOW() WHERE id=?")->execute([$prof['usuario_id']]);
            header("Location: index.php?msg=inativado&nome=" . urlencode($prof['nome']));
            exit;

        case 'ativar':
            $pdo->prepare("UPDATE profissionais SET ativo=1, updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
            $pdo->prepare("UPDATE usuarios SET ativo=1, updated_at=NOW() WHERE id=?")->execute([$prof['usuario_id']]);
            header("Location: index.php?msg=ativado&nome=" . urlencode($prof['nome']));
            exit;

        default:
            header("Location: index.php"); exit;
    }
} catch (PDOException $e) {
    $resultado = ['sucesso'=>false, 'mensagem'=>"Erro: ".$e->getMessage(), 'tipo'=>'danger'];
}

require_once("../../top/topo.php");
$active_menu = 'profissionais';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header"><div class="container-fluid"><h3 class="mb-0">Profissionais</h3></div></div>
    <div class="app-content"><div class="container-fluid"><div class="card"><div class="card-body text-center py-5">
        <?php $icone = match($resultado['tipo']) { 'success'=>'bi-check-circle-fill text-success', 'warning'=>'bi-slash-circle-fill text-warning', default=>'bi-exclamation-circle-fill text-danger' }; ?>
        <i class="bi <?php echo $icone; ?>" style="font-size:3rem;"></i>
        <h4 class="mt-3"><?php echo $resultado['mensagem']; ?></h4>
        <div class="mt-4"><a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Voltar para Profissionais</a></div>
    </div></div></div></div>
</main>
<?php require_once("../../layout/footer.php"); ?>
