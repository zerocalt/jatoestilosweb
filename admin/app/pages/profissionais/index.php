<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

// Mensagem de retorno
$msg_retorno = $msg_tipo = '';
if (!empty($_GET['msg'])) {
    $nome = htmlspecialchars($_GET['nome'] ?? '');
    [$msg_retorno, $msg_tipo] = match($_GET['msg']) {
        'excluido' => ["<strong>{$nome}</strong> excluído com sucesso.", 'success'],
        'inativado'=> ["<strong>{$nome}</strong> inativado com sucesso.", 'warning'],
        'ativado'  => ["<strong>{$nome}</strong> reativado com sucesso.", 'success'],
        default    => ['', 'info']
    };
}

$stmt = $pdo->prepare("SELECT p.*, u.nome, u.email, u.telefone, u.foto_url AS user_foto, u.perfil AS usuario_perfil
                       FROM profissionais p
                       JOIN usuarios u ON u.id = p.usuario_id
                       WHERE p.estabelecimento_id = :estab_id
                       ORDER BY p.ativo DESC, u.nome ASC");
$stmt->execute(['estab_id' => $estabelecimento_id]);
$profissionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once("../../top/topo.php");
$active_menu = 'profissionais';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Profissionais</h3></div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Profissional</a>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content"><div class="container-fluid">

        <?php if ($msg_retorno): ?>
            <div class="alert alert-<?php echo $msg_tipo; ?> alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $msg_retorno; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> Profissional salvo com sucesso!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($profissionais)): ?>
            <div class="alert alert-info">Nenhum profissional cadastrado.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($profissionais as $prof): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card shadow-sm <?php echo !$prof['ativo'] ? 'border-secondary opacity-75' : ''; ?>">
                            <div class="widget-user-header <?php echo $prof['ativo'] ? 'bg-primary' : 'bg-secondary'; ?>" style="border-radius:8px 8px 0 0; padding:20px; position:relative; min-height:80px;">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo $prof['user_foto'] ?: '../../assets/img/user2-160x160.jpg'; ?>"
                                         style="width:55px;height:55px;border-radius:50%;border:2px solid rgba(255,255,255,0.5);object-fit:cover;" alt="">
                                    <div>
                                        <h5 class="mb-0 text-white fw-bold"><?php echo htmlspecialchars($prof['nome']); ?></h5>
                                        <small class="text-white opacity-75"><?php echo htmlspecialchars($prof['cargo'] ?: 'Sem cargo'); ?></small>
                                    </div>
                                </div>
                                <?php if (!$prof['ativo']): ?>
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">Inativo</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Comissão</span>
                                        <span class="badge bg-primary"><?php echo $prof['comissao_percentual']; ?>%</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Telefone</span>
                                        <span class="small"><?php echo htmlspecialchars($prof['telefone'] ?: '—'); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Acesso ao sistema</span>
                                        <?php if ($prof['email'] && in_array($prof['usuario_perfil'], ['atendente','profissional'])): ?>
                                            <span class="badge bg-info"><?php echo ucfirst($prof['usuario_perfil']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Não</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-footer text-center py-2">
                                <a href="form.php?id=<?php echo $prof['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                                <a href="../relatorios/comissoes.php?profissional_id=<?php echo $prof['id']; ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-cash"></i> Comissões</a>

                                <?php if ($prof['ativo']): ?>
                                    <button class="btn btn-sm btn-outline-warning" title="Inativar"
                                            onclick="confirmarAcao('inativar','<?php echo $prof['id']; ?>','<?php echo addslashes($prof['nome']); ?>')">
                                        <i class="bi bi-pause-circle"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="action.php?acao=ativar&id=<?php echo $prof['id']; ?>"
                                       class="btn btn-sm btn-outline-success"
                                       onclick="return confirm('Reativar <?php echo addslashes($prof['nome']); ?>?')">
                                        <i class="bi bi-play-circle"></i>
                                    </a>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-outline-danger" title="Excluir"
                                        onclick="confirmarAcao('excluir','<?php echo $prof['id']; ?>','<?php echo addslashes($prof['nome']); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
        inativar: { titulo:'Inativar Profissional', msg:`Inativar "${nome}"? Ele não aparecerá na agenda, mas o histórico é preservado.`, btnClass:'btn btn-warning', btnTxt:'Inativar', hClass:'modal-header bg-warning' },
        excluir:  { titulo:'Excluir Profissional',  msg:`Excluir "${nome}"? Se houver agendamentos, será inativado. Caso contrário, excluído definitivamente junto com a conta de acesso.`, btnClass:'btn btn-danger', btnTxt:'Excluir', hClass:'modal-header bg-danger text-white' },
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
