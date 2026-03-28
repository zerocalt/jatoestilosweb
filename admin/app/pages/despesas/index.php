<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$status_filtro    = $_GET['status']    ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

// Mensagem de retorno
$msg_retorno = $msg_tipo = '';
if (!empty($_GET['msg'])) {
    $nome = htmlspecialchars($_GET['nome'] ?? '');
    [$msg_retorno, $msg_tipo] = match($_GET['msg']) {
        'excluido' => ["<strong>{$nome}</strong> excluída com sucesso.", 'success'],
        'pago'     => ["<strong>{$nome}</strong> marcada como paga.", 'success'],
        'cancelado'=> ["<strong>{$nome}</strong> cancelada.", 'warning'],
        default    => ['', 'info']
    };
}

$query  = "SELECT * FROM despesas WHERE estabelecimento_id = :estab_id AND status != 'cancelado'";
$params = ['estab_id' => $estabelecimento_id];

if ($status_filtro)    { $query .= " AND status = :status";   $params['status'] = $status_filtro; }
if ($categoria_filtro) { $query .= " AND categoria = :cat";   $params['cat']    = $categoria_filtro; }

$query .= " ORDER BY FIELD(status,'atrasado','pendente','pago'), vencimento ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais rápidos
$total_pendente = $total_pago = $total_atrasado = 0;
foreach ($despesas as $d) {
    if ($d['status'] === 'pendente') $total_pendente += $d['valor_centavos'];
    if ($d['status'] === 'pago')     $total_pago     += $d['valor_centavos'];
    if ($d['status'] === 'atrasado') $total_atrasado += $d['valor_centavos'];
}

$categorias = ['aluguel','energia','agua','internet','telefone','materiais','salarios','outros'];

require_once("../../top/topo.php");
$active_menu = 'despesas';
require_once("../../menu/menu.php");
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Despesas</h3></div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Despesa</a>
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

        <!-- Totais -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="small-box text-bg-warning">
                    <div class="inner"><h3><?php echo formatMoney($total_pendente); ?></h3><p>Pendente</p></div>
                    <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M8 3.5a.5.5 0 0 1 .5.5v4.25l3.5 2.1a.5.5 0 0 1-.5.85l-3.75-2.25A.5.5 0 0 1 7.5 8.5V4a.5.5 0 0 1 .5-.5z"/>
                        <path d="M8 16A8 8 0 1 1 8 0a8 8 0 0 1 0 16zm0-1A7 7 0 1 0 8 1a7 7 0 0 0 0 14z"/>
                    </svg>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box text-bg-danger">
                    <div class="inner"><h3><?php echo formatMoney($total_atrasado); ?></h3><p>Atrasado</p></div>
                    <svg class="small-box-icon" fill="white" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M7.001 4a1 1 0 1 1 2 0l-.35 4.5a.65.65 0 0 1-1.3 0L7.001 4zm.999 7a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                        <path d="M8 16A8 8 0 1 1 8 0a8 8 0 0 1 0 16zM8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1z"/>
                    </svg>
                </div>
            </div>
            <div class="col-md-4">
                <div class="small-box text-bg-success">
                    <div class="inner"><h3><?php echo formatMoney($total_pago); ?></h3><p>Pago este mês</p></div>
                    <svg class="small-box-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14z"/>
                        <path fill="white" d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="pendente"  <?php echo $status_filtro=='pendente'  ? 'selected':'' ?>>Pendente</option>
                            <option value="pago"      <?php echo $status_filtro=='pago'      ? 'selected':'' ?>>Pago</option>
                            <option value="atrasado"  <?php echo $status_filtro=='atrasado'  ? 'selected':'' ?>>Atrasado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Categoria</label>
                        <select name="categoria" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo $categoria_filtro==$cat?'selected':'' ?>><?php echo ucfirst($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($status_filtro || $categoria_filtro): ?>
                        <div class="col-md-2">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">Limpar filtros</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Despesa</th>
                            <th>Tipo / Cat.</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($despesas)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma despesa encontrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($despesas as $d): ?>
                                <?php
                                $vencida = $d['status'] === 'atrasado' ||
                                           ($d['status'] === 'pendente' && $d['vencimento'] && $d['vencimento'] < date('Y-m-d'));
                                ?>
                                <tr class="<?php echo $vencida ? 'table-danger' : ''; ?>">
                                    <td>
                                        <span class="fw-bold"><?php echo htmlspecialchars($d['nome']); ?></span>
                                        <?php if ($d['recorrencia'] && $d['recorrencia'] !== 'nenhuma'): ?>
                                            <span class="badge bg-info ms-1" title="Recorrente"><?php echo ucfirst($d['recorrencia']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $d['tipo']==='fixa' ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($d['tipo']); ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo ucfirst($d['categoria']); ?></small>
                                    </td>
                                    <td><?php echo formatDate($d['vencimento']); ?></td>
                                    <td class="fw-bold"><?php echo formatMoney($d['valor_centavos']); ?></td>
                                    <td>
                                        <?php $badge = match($d['status']) { 'pago'=>'bg-success', 'atrasado'=>'bg-danger', default=>'bg-warning text-dark' }; ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($d['status']); ?></span>
                                        <?php if ($d['pago_em']): ?>
                                            <br><small class="text-muted">em <?php echo formatDate($d['pago_em']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="form.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="bi bi-pencil"></i></a>

                                        <?php if ($d['status'] !== 'pago'): ?>
                                            <button class="btn btn-sm btn-success" title="Marcar como Pago"
                                                    onclick="confirmarAcao('pagar','<?php echo $d['id']; ?>','<?php echo addslashes($d['nome']); ?>')">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php endif; ?>

                                        <button class="btn btn-sm btn-danger" title="Excluir"
                                                onclick="confirmarAcao('excluir','<?php echo $d['id']; ?>','<?php echo addslashes($d['nome']); ?>')">
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
        pagar:   { titulo:'Confirmar Pagamento', msg:`Marcar "${nome}" como paga hoje?`,           btnClass:'btn btn-success', btnTxt:'Confirmar Pagamento', hClass:'modal-header bg-success text-white' },
        excluir: { titulo:'Excluir Despesa',     msg:`Excluir "${nome}" definitivamente?`,         btnClass:'btn btn-danger',  btnTxt:'Excluir',            hClass:'modal-header bg-danger text-white'  },
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
