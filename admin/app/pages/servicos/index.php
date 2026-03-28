<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

// Mensagem de retorno
$msg_retorno = '';
if (!empty($_GET['msg'])) {
    $nome = htmlspecialchars($_GET['nome'] ?? '');
    $msg_retorno = match($_GET['msg']) {
        'excluido' => "<strong>{$nome}</strong> excluído com sucesso.",
        'inativado'=> "<strong>{$nome}</strong> inativado com sucesso.",
        'ativado'  => "<strong>{$nome}</strong> reativado com sucesso.",
        default    => ''
    };
    $msg_tipo = $_GET['msg'] === 'excluido' ? 'success' : ($_GET['msg'] === 'ativado' ? 'success' : 'warning');
}

$stmt = $pdo->prepare("SELECT * FROM servicos WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL ORDER BY ativo DESC, nome ASC");
$stmt->execute(['estab_id' => $estabelecimento_id]);
$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once("../../top/topo.php");
$active_menu = 'servicos';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Serviços</h3></div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Serviço</a>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content"><div class="container-fluid">

        <?php if ($msg_retorno): ?>
            <div class="alert alert-<?php echo $msg_tipo ?? 'success'; ?> alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $msg_retorno; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Serviço salvo com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Catálogo de Serviços</h3></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Duração</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($servicos)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum serviço cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($servicos as $s): ?>
                                <tr class="<?php echo !$s['ativo'] ? 'text-muted' : ''; ?>">
                                    <td class="fw-bold"><?php echo htmlspecialchars($s['nome']); ?></td>
                                    <td><?php echo $s['duracao_minutos']; ?> min</td>
                                    <td><?php echo formatMoney($s['valor_centavos']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $s['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $s['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="form.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="bi bi-pencil"></i></a>

                                        <?php if ($s['ativo']): ?>
                                            <button class="btn btn-sm btn-warning" title="Inativar"
                                                    onclick="confirmarAcao('inativar','<?php echo $s['id']; ?>','<?php echo addslashes($s['nome']); ?>')">
                                                <i class="bi bi-pause-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="action.php?acao=ativar&id=<?php echo $s['id']; ?>"
                                               class="btn btn-sm btn-success" title="Reativar"
                                               onclick="return confirm('Reativar o serviço <?php echo addslashes($s['nome']); ?>?')">
                                                <i class="bi bi-play-circle"></i>
                                            </a>
                                        <?php endif; ?>

                                        <button class="btn btn-sm btn-danger" title="Excluir"
                                                onclick="confirmarAcao('excluir','<?php echo $s['id']; ?>','<?php echo addslashes($s['nome']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
</main>

<!-- Modal -->
<div class="modal fade" id="modalAcao" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header" id="modalHeader">
            <h5 class="modal-title" id="modalTitulo"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body"><p id="modalMensagem"></p></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn" id="btnConfirmar" onclick="executarAcao()">Confirmar</button>
        </div>
    </div></div>
</div>

<script>
let acaoAtual = '', idAtual = '';
function confirmarAcao(acao, id, nome) {
    acaoAtual = acao; idAtual = id;
    const cfg = {
        inativar: { titulo:'Inativar Serviço', msg:`Inativar "${nome}"? Ele não aparecerá para novos agendamentos, mas o histórico é preservado.`, btnClass:'btn btn-warning', btnTxt:'Inativar', hClass:'modal-header bg-warning' },
        excluir:  { titulo:'Excluir Serviço',  msg:`Excluir "${nome}"? Se houver histórico de agendamentos, será inativado. Caso contrário, excluído definitivamente.`, btnClass:'btn btn-danger', btnTxt:'Excluir', hClass:'modal-header bg-danger text-white' },
    };
    const c = cfg[acao];
    document.getElementById('modalTitulo').textContent   = c.titulo;
    document.getElementById('modalMensagem').textContent = c.msg;
    document.getElementById('btnConfirmar').className    = c.btnClass;
    document.getElementById('btnConfirmar').textContent  = c.btnTxt;
    document.getElementById('modalHeader').className     = c.hClass;
    new bootstrap.Modal(document.getElementById('modalAcao')).show();
}
function executarAcao() {
    window.location.href = `action.php?acao=${acaoAtual}&id=${idAtual}`;
}
</script>
<?php require_once("../../layout/footer.php"); ?>
