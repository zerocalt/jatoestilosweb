<?php
require_once("../../top/topo.php");
$active_menu = 'clientes';
require_once("../../menu/menu.php");
require_once("../../config/database.php");
require_once("../../config/functions.php");

$search = $_GET['search'] ?? '';
$estabelecimento_id = $_SESSION['estabelecimento_id'];

$query = "SELECT * FROM clientes WHERE estabelecimento_id = :estab_id AND deleted_at IS NULL";
$params = ['estab_id' => $estabelecimento_id];

if (!empty($search)) {
    $query .= " AND (nome LIKE :search OR telefone LIKE :search OR email LIKE :search OR cpf LIKE :search)";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY nome ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar clientes: " . $e->getMessage());
}
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
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Listagem de Clientes</h3>
                    <div class="card-tools">
                        <form action="index.php" method="get" class="input-group input-group-sm" style="width: 250px;">
                            <input type="text" name="search" class="form-control" placeholder="Buscar..." value="<?php echo sanitize($search); ?>">
                            <button type="submit" class="btn btn-default">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped align-middle">
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
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Nenhum cliente encontrado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo sanitize($cliente['nome']); ?></td>
                                        <td><?php echo sanitize($cliente['telefone']); ?></td>
                                        <td><?php echo sanitize($cliente['email']); ?></td>
                                        <td><?php echo sanitize($cliente['cpf']); ?></td>
                                        <td><?php echo formatDate($cliente['data_nascimento']); ?></td>
                                        <td class="text-end">
                                            <a href="view.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-info" title="Ver Perfil"><i class="bi bi-eye"></i></a>
                                            <a href="form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="bi bi-pencil"></i></a>
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
