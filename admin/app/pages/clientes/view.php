<?php
require_once("../../top/topo.php");
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

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

<?php require_once("../../layout/footer.php"); ?>
