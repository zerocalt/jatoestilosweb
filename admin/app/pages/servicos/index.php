<?php
require_once("../../top/topo.php");
$active_menu = 'servicos';
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$estabelecimento_id = $_SESSION['estabelecimento_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM servicos WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL ORDER BY nome ASC");
    $stmt->execute(['estab_id' => $estabelecimento_id]);
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar serviços: " . $e->getMessage());
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Serviços</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Serviço</a>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Catálogo de Serviços</h3>
                </div>
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
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Nenhum serviço cadastrado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($servicos as $servico): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo sanitize($servico['nome']); ?></td>
                                        <td><?php echo $servico['duracao_minutos']; ?> min</td>
                                        <td><?php echo formatMoney($servico['valor_centavos']); ?></td>
                                        <td>
                                            <?php if ($servico['ativo']): ?>
                                                <span class="badge bg-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="form.php?id=<?php echo $servico['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once("../../layout/footer.php"); ?>
