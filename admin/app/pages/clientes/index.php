<?php
session_start();
require_once("../../config/database.php");
require_once("../../config/functions.php");

$search             = $_GET['search'] ?? '';
$estabelecimento_id = $_SESSION['estabelecimento_id'];

// ── Mensagens de retorno do action.php ─────────
$msg_retorno = '';
if (!empty($_GET['msg'])) {
    $nome = htmlspecialchars($_GET['nome'] ?? '');
    $msg_retorno = match($_GET['msg']) {
        'excluido'     => "<strong>{$nome}</strong> excluído com sucesso.",
        'desbloqueado' => "<strong>{$nome}</strong> desbloqueado com sucesso.",
        default        => ''
    };
}

// ── Clientes ativos ────────────────────────────
$query  = "SELECT * FROM clientes WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL AND bloqueado = 0";
$params = ['estab_id' => $estabelecimento_id];

if (!empty($search)) {
    $query .= " AND (nome LIKE :search OR telefone LIKE :search OR email LIKE :search OR cpf LIKE :search)";
    $params['search'] = "%$search%";
}
$query .= " ORDER BY nome ASC";

$stmt    = $pdo->prepare($query);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Clientes bloqueados ────────────────────────
$queryB  = "SELECT * FROM clientes WHERE estabelecimento_id = :estab_id AND bloqueado = 1";
$paramsB = ['estab_id' => $estabelecimento_id];

if (!empty($search)) {
    $queryB .= " AND (nome LIKE :search OR telefone LIKE :search OR email LIKE :search OR cpf LIKE :search)";
    $paramsB['search'] = "%$search%";
}
$queryB .= " ORDER BY nome ASC";

$stmtB          = $pdo->prepare($queryB);
$stmtB->execute($paramsB);
$clientes_bloqueados = $stmtB->fetchAll(PDO::FETCH_ASSOC);

// HTML somente aqui
require_once("../../top/topo.php");
$active_menu = 'clientes';
require_once("../../menu/menu.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Clientes</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Cliente</a>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <?php if ($msg_retorno): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill"></i> <?php echo $msg_retorno; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ── Clientes Ativos ── -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-people-fill me-2"></i>Clientes Ativos
                        <span class="badge bg-primary ms-2"><?php echo count($clientes); ?></span>
                    </h3>
                    <div class="card-tools">
                        <form action="index.php" method="get" class="input-group input-group-sm" style="width:250px;">
                            <input type="text" name="search" class="form-control" placeholder="Buscar..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-default"><i class="bi bi-search"></i></button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>E-mail</th>
                                <th>CPF</th>
                                <th>Nascimento</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum cliente encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $c): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($c['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($c['telefone']); ?></td>
                                        <td><?php echo htmlspecialchars($c['email']); ?></td>
                                        <td><?php echo htmlspecialchars($c['cpf']); ?></td>
                                        <td><?php echo formatDate($c['data_nascimento']); ?></td>
                                        <td class="text-end">
                                            <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info" title="Ver Perfil"><i class="bi bi-eye"></i></a>
                                            <a href="form.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <button class="btn btn-sm btn-warning" title="Bloquear"
                                                    onclick="confirmarAcao('bloquear','<?php echo $c['id']; ?>','<?php echo addslashes($c['nome']); ?>')">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" title="Excluir"
                                                    onclick="confirmarAcao('excluir','<?php echo $c['id']; ?>','<?php echo addslashes($c['nome']); ?>')">
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

            <!-- ── Clientes Bloqueados ── -->
            <?php if (!empty($clientes_bloqueados)): ?>
            <div class="card mt-4 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h3 class="card-title">
                        <i class="bi bi-slash-circle-fill me-2"></i>Clientes Bloqueados
                        <span class="badge bg-dark ms-2"><?php echo count($clientes_bloqueados); ?></span>
                    </h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-warning">
                            <tr>
                                <th>Nome</th>
                                <th>Telefone</th>
                                <th>Motivo do Bloqueio</th>
                                <th>Bloqueado em</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes_bloqueados as $c): ?>
                                <tr class="text-muted">
                                    <td class="fw-bold"><?php echo htmlspecialchars($c['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($c['telefone']); ?></td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <?php echo htmlspecialchars($c['motivo_bloqueio'] ?? 'Sem motivo'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $c['bloqueado_em'] ? formatDateTime($c['bloqueado_em']) : '-'; ?></td>
                                    <td class="text-end">
                                        <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info" title="Ver Perfil"><i class="bi bi-eye"></i></a>
                                        <a href="action.php?acao=desbloquear&id=<?php echo $c['id']; ?>"
                                           class="btn btn-sm btn-success" title="Desbloquear"
                                           onclick="return confirm('Desbloquear <?php echo addslashes($c['nome']); ?>?')">
                                            <i class="bi bi-check-circle"></i> Desbloquear
                                        </a>
                                        <button class="btn btn-sm btn-secondary" title="Anonimizar (LGPD)"
                                                onclick="confirmarAcao('anonimizar','<?php echo $c['id']; ?>','<?php echo addslashes($c['nome']); ?>')">
                                            <i class="bi bi-person-slash"></i> LGPD
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<!-- Modal de confirmação -->
<div class="modal fade" id="modalAcao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader">
                <h5 class="modal-title" id="modalTitulo"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="modalMensagem"></p>
                <div id="campoMotivo" style="display:none;">
                    <label class="form-label">Motivo do bloqueio</label>
                    <input type="text" id="inputMotivo" class="form-control" placeholder="Ex: Cliente problemático, duplicado...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn" id="btnConfirmar" onclick="executarAcao()">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
let acaoAtual = '', idAtual = '';

function confirmarAcao(acao, id, nome) {
    acaoAtual = acao; idAtual = id;

    const configs = {
        bloquear:   { titulo: 'Bloquear Cliente',     msg: `Bloquear "${nome}"? O histórico será preservado.`,                          motivo: true,  btnClass: 'btn btn-warning',         btnTxt: 'Bloquear',   headerClass: 'modal-header bg-warning' },
        excluir:    { titulo: 'Excluir Cliente',       msg: `Excluir "${nome}"? Se tiver registros, será bloqueado. Caso contrário, excluído definitivamente.`, motivo: false, btnClass: 'btn btn-danger',          btnTxt: 'Excluir',    headerClass: 'modal-header bg-danger text-white' },
        anonimizar: { titulo: 'Anonimizar (LGPD)',     msg: `Remover todos os dados pessoais de "${nome}"? Os registros de atendimentos serão mantidos como "Anônimo".`, motivo: false, btnClass: 'btn btn-secondary',       btnTxt: 'Anonimizar', headerClass: 'modal-header bg-secondary text-white' },
    };

    const c = configs[acao];
    document.getElementById('modalTitulo').textContent    = c.titulo;
    document.getElementById('modalMensagem').textContent  = c.msg;
    document.getElementById('campoMotivo').style.display  = c.motivo ? 'block' : 'none';
    document.getElementById('btnConfirmar').className     = c.btnClass;
    document.getElementById('btnConfirmar').textContent   = c.btnTxt;
    document.getElementById('modalHeader').className      = c.headerClass;

    new bootstrap.Modal(document.getElementById('modalAcao')).show();
}

function executarAcao() {
    const motivo = document.getElementById('inputMotivo').value;
    window.location.href = `action.php?acao=${acaoAtual}&id=${idAtual}&motivo=${encodeURIComponent(motivo)}`;
}
</script>

<?php require_once("../../layout/footer.php"); ?>