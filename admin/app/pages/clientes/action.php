<?php
// ═══════════════════════════════════════════════
// session_start() ANTES DE TUDO — corrige o bug
// ═══════════════════════════════════════════════
session_start();

require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'] ?? '';
$acao   = $_GET['acao']   ?? '';
$id     = $_GET['id']     ?? '';
$motivo = $_GET['motivo'] ?? '';

if (empty($estabelecimento_id)) {
    header("Location: ../../login.php");
    exit;
}

if (empty($id) || empty($acao)) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ? AND estabelecimento_id = ? LIMIT 1");
$stmt->execute([$id, $estabelecimento_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE cliente_id = ?");
$stmt->execute([$id]);
$total_agendamentos = (int)$stmt->fetchColumn();
$tem_registros      = $total_agendamentos > 0;

$resultado = ['sucesso' => false, 'mensagem' => '', 'tipo' => ''];

try {
    switch ($acao) {

        case 'excluir':
            if (!$tem_registros) {
                $pdo->prepare("DELETE FROM tags_cliente WHERE cliente_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM clientes WHERE id = ? AND estabelecimento_id = ?")->execute([$id, $estabelecimento_id]);
                $foto = $_SERVER['DOCUMENT_ROOT'] . "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/clientes/{$id}.jpg";
                if (file_exists($foto)) unlink($foto);
                header("Location: index.php?msg=excluido&nome=" . urlencode($cliente['nome']));
                exit;
            } else {
                $pdo->prepare("UPDATE clientes SET bloqueado=1, motivo_bloqueio='Excluído pelo administrador (mantido por ter registros)', bloqueado_em=NOW(), deleted_at=NOW(), updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
                $resultado = ['sucesso'=>true, 'mensagem'=>"Cliente <strong>".htmlspecialchars($cliente['nome'])."</strong> possui {$total_agendamentos} agendamento(s). Foi <strong>bloqueado</strong> para preservar o histórico.", 'tipo'=>'warning'];
            }
            break;

        case 'bloquear':
            $motivoFinal = !empty($motivo) ? $motivo : 'Bloqueado pelo administrador';
            $pdo->prepare("UPDATE clientes SET bloqueado=1, motivo_bloqueio=?, bloqueado_em=NOW(), deleted_at=NOW(), updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$motivoFinal, $id, $estabelecimento_id]);
            $resultado = ['sucesso'=>true, 'mensagem'=>"Cliente <strong>".htmlspecialchars($cliente['nome'])."</strong> bloqueado. O histórico foi preservado.", 'tipo'=>'warning'];
            break;

        case 'desbloquear':
            $pdo->prepare("UPDATE clientes SET bloqueado=0, motivo_bloqueio=NULL, bloqueado_em=NULL, deleted_at=NULL, updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
            header("Location: index.php?msg=desbloqueado&nome=" . urlencode($cliente['nome']));
            exit;

        case 'anonimizar':
            $pdo->prepare("UPDATE clientes SET nome='Anônimo', telefone=NULL, email=NULL, cpf=NULL, data_nascimento=NULL, observacoes='Dados removidos a pedido do titular (LGPD)', bloqueado=1, deleted_at=NOW(), updated_at=NOW() WHERE id=? AND estabelecimento_id=?")->execute([$id, $estabelecimento_id]);
            if (!empty($cliente['usuario_id'])) {
                $pdo->prepare("UPDATE usuarios SET nome='Usuário Removido', email=NULL, telefone=NULL, senha_hash=NULL, foto_url=NULL, ativo=0, deleted_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$cliente['usuario_id']]);
            }
            $foto = $_SERVER['DOCUMENT_ROOT'] . "/jatoestilos/uploads/estabelecimentos/{$estabelecimento_id}/clientes/{$id}.jpg";
            if (file_exists($foto)) unlink($foto);
            $resultado = ['sucesso'=>true, 'mensagem'=>"Dados pessoais removidos conforme solicitação LGPD. Os registros foram preservados como 'Anônimo'.", 'tipo'=>'info'];
            break;

        default:
            header("Location: index.php");
            exit;
    }
} catch (PDOException $e) {
    $resultado = ['sucesso'=>false, 'mensagem'=>"Erro: ".$e->getMessage(), 'tipo'=>'danger'];
}

// HTML somente após o processamento
require_once("../../top/topo.php");
$active_menu = 'clientes';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Clientes</h3></div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body text-center py-5">
                    <?php $icone = match($resultado['tipo']) {
                        'success' => 'bi-check-circle-fill text-success',
                        'warning' => 'bi-slash-circle-fill text-warning',
                        'info'    => 'bi-info-circle-fill text-info',
                        default   => 'bi-exclamation-circle-fill text-danger',
                    }; ?>
                    <i class="bi <?php echo $icone; ?>" style="font-size:3rem;"></i>
                    <h4 class="mt-3"><?php echo $resultado['mensagem']; ?></h4>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Voltar para Clientes</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once("../../layout/footer.php"); ?>