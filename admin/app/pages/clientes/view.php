<?php
require_once("../../config/database.php");
require_once("../../config/functions.php");
require_once("../../top/topo.php");
require_once("../../menu/menu.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    // Dados do cliente
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id AND estabelecimento_id = :estab_id");
    $stmt->execute(['id' => $id, 'estab_id' => $estabelecimento_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        header("Location: index.php");
        exit;
    }

    // Histórico de atendimentos
    $stmt = $pdo->prepare("SELECT a.*, u.nome as profissional_nome, 
                                  (SELECT GROUP_CONCAT(s.nome SEPARATOR ', ') 
                                   FROM agendamento_servicos ags 
                                   JOIN servicos s ON s.id = ags.servico_id 
                                   WHERE ags.agendamento_id = a.id) as servicos
                           FROM agendamentos a
                           JOIN profissionais p ON p.id = a.profissional_id
                           JOIN usuarios u ON u.id = p.usuario_id
                           WHERE a.cliente_id = :cliente_id 
                           ORDER BY a.data_inicio DESC");
    $stmt->execute(['cliente_id' => $id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Perfil do Cliente</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="index.php" class="btn btn-default">Voltar</a>
                    <a href="form.php?id=<?php echo $id; ?>" class="btn btn-primary">Editar</a>
                    <button type="button" class="btn btn-warning"
                            onclick="confirmarAcao('bloquear', '<?php echo $id; ?>', '<?php echo addslashes($cliente['nome']); ?>')">
                        <i class="bi bi-slash-circle"></i> Bloquear
                    </button>
                    <button type="button" class="btn btn-danger"
                            onclick="confirmarAcao('excluir', '<?php echo $id; ?>', '<?php echo addslashes($cliente['nome']); ?>')">
                        <i class="bi bi-trash"></i> Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile text-center">
                            <h3 class="profile-username text-center"><?php echo sanitize($cliente['nome']); ?></h3>
                            <p class="text-muted text-center"><?php echo sanitize($cliente['email']); ?></p>
                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b>Telefone</b> <a class="float-end text-decoration-none"><?php echo sanitize($cliente['telefone']); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>CPF</b> <a class="float-end text-decoration-none"><?php echo sanitize($cliente['cpf']); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Nascimento</b> <a class="float-end text-decoration-none"><?php echo formatDate($cliente['data_nascimento']); ?></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Observações</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted"><?php echo nl2br(sanitize($cliente['observacoes'] ?? 'Sem observações.')); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Histórico de Atendimentos</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Profissional</th>
                                        <th>Serviços</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($historico)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">Nenhum atendimento registrado.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($historico as $item): ?>
                                            <tr>
                                                <td><?php echo formatDateTime($item['data_inicio']); ?></td>
                                                <td><?php echo sanitize($item['profissional_nome']); ?></td>
                                                <td><?php echo sanitize($item['servicos']); ?></td>
                                                <td><?php echo formatMoney($item['valor_total_centavos']); ?></td>
                                                <td><?php echo formatStatus($item['status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
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
                    <input type="text" id="inputMotivo" class="form-control" 
                           placeholder="Ex: Cliente problemático, duplicado...">
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
    const modal = document.getElementById;
    if (acao === 'bloquear') {
        document.getElementById('modalTitulo').textContent = 'Bloquear Cliente';
        document.getElementById('modalMensagem').textContent = `Bloquear "${nome}"? O cliente não poderá mais acessar o estabelecimento, mas o histórico será mantido.`;
        document.getElementById('campoMotivo').style.display = 'block';
        document.getElementById('btnConfirmar').className = 'btn btn-warning';
        document.getElementById('btnConfirmar').textContent = 'Bloquear';
        document.getElementById('modalHeader').className = 'modal-header bg-warning';
    } else {
        document.getElementById('modalTitulo').textContent = 'Excluir Cliente';
        document.getElementById('modalMensagem').textContent = `Excluir "${nome}"? Se o cliente tiver registros, será bloqueado automaticamente. Se não tiver, será excluído definitivamente.`;
        document.getElementById('campoMotivo').style.display = 'none';
        document.getElementById('btnConfirmar').className = 'btn btn-danger';
        document.getElementById('btnConfirmar').textContent = 'Excluir';
        document.getElementById('modalHeader').className = 'modal-header bg-danger text-white';
    }
    new bootstrap.Modal(document.getElementById('modalAcao')).show();
}

function executarAcao() {
    const motivo = document.getElementById('inputMotivo').value;
    window.location.href = `action.php?acao=${acaoAtual}&id=${idAtual}&motivo=${encodeURIComponent(motivo)}`;
}
</script>

<?php require_once("../../layout/footer.php"); ?>
